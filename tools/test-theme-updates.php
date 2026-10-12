<?php
/** Inherited updater JSON boundary. No HTTP requests, licenses or database writes. */
define( 'ABSPATH', __DIR__ . '/' );
function get_option( $name ) { return false; }
require dirname( __DIR__ ) . '/inc/classes/Docy_update_checker.php';
set_error_handler( static function( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$checks = 0;
foreach ( array(
    array( 'version' => array( 'nested' ), 'details_url' => 'https://example.invalid/details' ),
    array( 'version' => '2.0.0', 'details_url' => array( 'nested' ) ),
    array( 'version' => '2.0.0', 'details_url' => 'https://example.invalid/details', 'du' => array( 'nested' ) ),
    array( 'version' => true, 'details_url' => 'https://example.invalid/details' ),
    array( 'version' => ' ', 'details_url' => 'https://example.invalid/details' ),
    array( 'version' => '2.0.0', 'details_url' => ' ' ),
    array( 'details_url' => 'https://example.invalid/details' ),
    array( 'version' => '2.0.0' ),
) as $metadata ) {
    if ( null !== ThemeUpdate::fromJson( json_encode( $metadata ) ) ) throw new RuntimeException( 'Malformed updater metadata must be refused before reaching version comparison or WordPress rendering.' );
    ++$checks;
}
foreach ( array( '', 'not-json', 'null', '[]', '"text"' ) as $json ) {
    if ( null !== ThemeUpdate::fromJson( $json ) ) throw new RuntimeException( 'Invalid JSON or non-object metadata must be refused.' );
    ++$checks;
}
foreach ( array( array(), array( 'du' => 'synthetic-encoded-package' ), array( 'requires' => '6.0', 'unexpected' => array( 'nested' ) ) ) as $extra ) {
    $metadata = array_merge( array( 'version' => '2.0.0-beta1', 'details_url' => 'https://example.invalid/details' ), $extra );
    $update = ThemeUpdate::fromJson( json_encode( $metadata ) );
    if ( ! $update instanceof ThemeUpdate || $update->version !== $metadata['version'] || $update->details_url !== $metadata['details_url'] || $update->du !== ( $extra['du'] ?? null ) ) throw new RuntimeException( 'Valid updater metadata lost its documented values.' );
    if ( array_diff( array_keys( get_object_vars( $update ) ), array( 'version', 'details_url', 'du' ) ) ) throw new RuntimeException( 'Unknown remote fields created dynamic updater properties.' );
    if ( ! version_compare( $update->version, '1.0.0', '>' ) ) throw new RuntimeException( 'Valid metadata cannot reach the actual version comparison.' );
    ++$checks;
}
restore_error_handler();
echo "Theme updater: {$checks} malformed/valid metadata, unknown-field and version comparison assertions passed; no external requests.\n";
