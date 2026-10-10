<?php
require __DIR__ . '/signature-compatibility.php';
$source = "<?php\n" . 'class WP_E_User extends WP_E_Model { public $userData; public function __construct(){ $this->table="qa"; $this->signature=null; $this->settings=null; $this->signer=null; }}';
$properties = trb_signature_compatibility_manifest()['e-signature/models/User.php']['properties'];
$patched = trb_signature_property_patch( $source, 'WP_E_User', $properties );
$spec = array( 'baseline' => hash( 'sha256', $source ), 'patched_sha256' => hash( 'sha256', $patched ), 'class' => 'WP_E_User', 'properties' => $properties );
if ( trb_signature_verified_property_patch( $source, $spec ) !== $patched || trb_signature_verified_property_patch( $patched, $spec ) !== $patched ) throw new RuntimeException( 'Reviewed original or patched source rejected.' );
$rejected = false;
try { trb_signature_verified_property_patch( $patched . '/* unrelated edit */', $spec ); } catch ( RuntimeException $error ) { $rejected = true; }
if ( ! $rejected ) throw new RuntimeException( 'Unrelated vendor edit was accepted because properties already existed.' );
if ( $patched === $source || $patched !== trb_signature_property_patch( $patched, 'WP_E_User', $properties ) ) throw new RuntimeException( 'Property patch must be idempotent.' );
class WP_E_Model {}
set_error_handler( static function( $severity, $message ) { throw new RuntimeException( $message ); } );
eval( substr( $patched, 5 ) );
$user = new WP_E_User();
if ( $user->table !== 'qa' || $user->signature !== null || ! property_exists( $user, 'userData' ) ) throw new RuntimeException( 'Existing public object API changed.' );
restore_error_handler();
try { trb_signature_property_patch( '<?php class Unrelated {}', 'WP_E_User', $properties ); throw new LogicException( 'Unexpected source accepted.' ); }
catch ( RuntimeException $expected ) { if ( $expected instanceof LogicException ) throw $expected; }
echo "Signature property declarations, constructor diagnostics, idempotence and unrelated-source rejection passed.\n";
