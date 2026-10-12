<?php
/** Cached readiness must include authenticated Sheets availability; no real provider calls. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error { public function __construct( private $code ) {} public function get_error_code() { return $this->code; } }
function add_action( ...$args ) {} function add_filter( ...$args ) {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_remote_post( $url, $options ) { $GLOBALS['requests'][] = array( $url, $options ); return $GLOBALS['response']; }
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function wp_next_scheduled( ...$args ) { return time() + 60; }
function has_action( ...$args ) { return 10; }
function current_user_can( ...$args ) { return false; }
function trb_portal_demo_delivery_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
require dirname( __DIR__ ) . '/inc/trb-demo-automation.php';
function check_health( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$settings = array( 'spreadsheet_id'=>'synthetic', 'spreadsheet_tab'=>'Synthetic', 'sheet_webhook_url'=>'https://example.invalid/exec', 'sheet_webhook_secret'=>str_repeat( 's', 64 ), 'webdav_endpoint'=>'https://example.invalid/', 'pcloud_user'=>'synthetic', 'pcloud_pass'=>'synthetic', 'openai_key'=>'synthetic' );
$GLOBALS['requests'] = array();
$GLOBALS['response'] = array( 'status'=>200, 'body'=>json_encode( array( 'success'=>true, 'protocol'=>'trb-demo-sheet-health-v1', 'sheet_available'=>true, 'read_only'=>true ) ) );
check_health( 'operational' === trb_demo_health_sheet_probe( $settings )['status'], 'Strict authenticated provider receipt accepted.' );
$envelope = json_decode( $GLOBALS['requests'][0][1]['body'], true );
$payload = base64_decode( $envelope['payload_base64'], true );
check_health( array( 'action'=>'health' ) === json_decode( $payload, true ), 'Only a read-only action is sent; no artist data.' );
check_health( hash_equals( hash_hmac( 'sha256', $payload, $settings['sheet_webhook_secret'] ), $envelope['signature'] ), 'Sign the exact transmitted payload.' );
foreach ( array( array( 'success'=>false, 'error'=>'unauthorized' ), array( 'success'=>'true' ), array( 'success'=>true, 'protocol'=>'old' ), array( 'success'=>true, 'protocol'=>'trb-demo-sheet-health-v1', 'sheet_available'=>true, 'read_only'=>false ) ) as $body ) {
    $GLOBALS['response']['body'] = json_encode( $body );
    check_health( 'error' === trb_demo_health_sheet_probe( $settings )['status'], 'HTTP 200 cannot hide an invalid provider receipt.' );
}
$GLOBALS['response'] = new WP_Error( 'http_request_failed' );
check_health( 'HTTP_REQUEST_FAILED' === trb_demo_health_sheet_probe( $settings )['error_code'], 'Transport error remains observable.' );
$before = count( $GLOBALS['requests'] );
check_health( 'not_configured' === trb_demo_health_sheet_probe( array() )['status'] && $before === count( $GLOBALS['requests'] ), 'Missing configuration cannot trigger a provider call.' );
$GLOBALS['options'] = array( 'trb_demo_automation_settings'=>$settings, 'trb_demo_operational_health'=>array( 'checked_at_ts'=>time(), 'integrations'=>array( 'pcloud'=>array( 'status'=>'operational' ), 'openai'=>array( 'status'=>'operational' ) ), 'queue'=>array( 'counts'=>array(), 'problems'=>array() ) ) );
check_health( false === trb_demo_health_payload()['ready'], 'An older snapshot without Sheets proof is not ready.' );
$GLOBALS['options']['trb_demo_operational_health']['integrations']['spreadsheet'] = array( 'status'=>'error', 'error_code'=>'UNAUTHORIZED' );
check_health( false === trb_demo_health_payload()['ready'], 'Authenticated Sheets failure prevents a healthy overall status.' );
$GLOBALS['options']['trb_demo_operational_health']['integrations']['spreadsheet']['status'] = 'operational';
check_health( true === trb_demo_health_payload()['ready'], 'All configured providers, fresh evidence and an empty queue are ready.' );
check_health( $before === count( $GLOBALS['requests'] ), 'Public cached health never makes external requests.' );
echo "Demo readiness: signed read-only probe, strict receipts, errors and all-provider cached readiness passed.\n";
