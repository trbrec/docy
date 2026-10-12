<?php
define( 'ABSPATH', __DIR__ );
class WP_Error { public function __construct( public $code, ...$args ) {} }
function add_action( $hook, $callback, ...$args ) { $GLOBALS['hooks'][$hook] = $callback; }
function add_filter( $hook, $callback, ...$args ) { $GLOBALS['hooks'][$hook] = $callback; }
function remove_action( $hook, $callback ) { $GLOBALS['removed'][$hook] = $callback; }
require __DIR__ . '/../integrations/portal-mu-plugins/trb-release-deploy-guard.php';
$GLOBALS['hooks']['after_setup_theme']();
if ( ( $GLOBALS['removed']['trb_docy_auto_deploy'] ?? '' ) !== 'trb_docy_run_deploy_safety_net' ) throw new RuntimeException( 'Legacy updater remains active.' );
$request = new class { public $route = '/trb/v1/deploy'; public function get_route() { return $this->route; } };
$result = $GLOBALS['hooks']['rest_pre_dispatch']( null, null, $request );
if ( ! $result instanceof WP_Error || $result->code !== 'trb_ssh_release_required' ) throw new RuntimeException( 'Legacy deploy endpoint remains active.' );
$request->route = '/trb/v1/demo-health';
if ( $GLOBALS['hooks']['rest_pre_dispatch']( 'preserved', null, $request ) !== 'preserved' ) throw new RuntimeException( 'Unrelated API changed.' );
echo "Old automatic updater and legacy deployment endpoint blocked while unrelated APIs remain available.\n";
