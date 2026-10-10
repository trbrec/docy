<?php
/** Verify the CRM owner's private release receipt; never publish or install CRM source. */
function trb_crm_module_source_guard( $root, $receipt_path, array $required ) {
    if ( ! $required || is_link( $root ) || realpath( $root ) !== $root || is_link( $receipt_path ) || ! is_file( $receipt_path ) || filesize( $receipt_path ) > 1048576 ) throw new RuntimeException( 'Canonical CRM receipt unavailable.' );
    $receipt = json_decode( file_get_contents( $receipt_path ), true, 16, JSON_THROW_ON_ERROR );
    if ( ( $receipt['verified'] ?? false ) !== true || ! preg_match( '/^[a-f0-9]{40}$/D', $receipt['revision'] ?? '' ) || ! is_array( $receipt['files'] ?? null ) || ! $receipt['files'] ) throw new RuntimeException( 'Canonical CRM release unconfirmed.' );
    $verified = array();
    foreach ( $receipt['files'] as $item ) {
        $path = $item['path'] ?? ''; $hash = $item['sha256'] ?? '';
        if ( ! is_string( $path ) || ! preg_match( '#^[a-zA-Z0-9_./-]+$#D', $path ) || str_starts_with( $path, '/' ) || preg_match( '#(^|/)\.\.?(/|$)#', $path ) || isset( $verified[$path] ) || ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) throw new RuntimeException( 'Canonical CRM manifest invalid.' );
        $target = $root . '/' . $path;
        for ( $parent = $target; strlen( $parent ) >= strlen( $root ); $parent = dirname( $parent ) ) if ( is_link( $parent ) ) throw new RuntimeException( 'Canonical CRM source link refused.' );
        if ( ! is_file( $target ) || ! hash_equals( $hash, hash_file( 'sha256', $target ) ) ) throw new RuntimeException( 'Canonical CRM source changed after its release.' );
        $verified[$path] = true;
    }
    foreach ( $required as $path ) if ( ! isset( $verified[$path] ) ) throw new RuntimeException( 'Required CRM module absent from the owner receipt.' );
    return $receipt['revision'];
}
