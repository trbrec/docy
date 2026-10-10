<?php
/** CRM owns its runtime. A portal installer may never replace divergent modules. */
function trb_crm_module_source_guard( array $targets ) {
    if ( ! $targets ) throw new RuntimeException( 'CRM source manifest unavailable.' );
    foreach ( $targets as $installed => $candidate ) {
        if ( ! is_file( $installed ) || ! is_file( $candidate ) || is_link( $installed ) || is_link( $candidate ) ) throw new RuntimeException( 'CRM canonical module unavailable.' );
        $current = hash_file( 'sha256', $installed ); $expected = hash_file( 'sha256', $candidate );
        if ( ! is_string( $current ) || ! is_string( $expected ) || ! hash_equals( $expected, $current ) ) throw new RuntimeException( 'CRM canonical source differs; review the CRM revision first.' );
    }
}
