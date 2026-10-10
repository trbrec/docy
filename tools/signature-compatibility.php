<?php
/** Explicit properties preserve the existing PHP object API on PHP 8.2+. */
function trb_signature_property_patch( $source, $class, array $properties ) {
    $anchor = '/(class\s+' . preg_quote( $class, '/' ) . '\s+extends\s+WP_E_Model\s*\{)/';
    $declarations = '';
    foreach ( $properties as $property ) {
        if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $property ) ) throw new RuntimeException( 'Invalid property manifest.' );
        if ( ! preg_match( '/\b(?:public|protected|private)\s+\$' . preg_quote( $property, '/' ) . '\b/', $source ) ) $declarations .= "\n    public \$" . $property . ';';
    }
    if ( '' === $declarations ) return $source;
    $patched = preg_replace( $anchor, '$1' . $declarations . "\n", $source, 1, $count );
    if ( 1 !== $count ) throw new RuntimeException( 'Unrecognized signature model.' );
    return $patched;
}

function trb_signature_compatibility_manifest() {
    return array(
        'Esigrole.php' => array( 'class' => 'WP_E_Esigrole', 'properties' => array( 'settings', 'user' ), 'baseline' => '01c04ebb3879413d202240914313fe3873c972b3da8a919917e6c078aac9778f' ),
        'User.php' => array( 'class' => 'WP_E_User', 'properties' => array( 'table', 'signature', 'settings', 'signer' ), 'baseline' => '959a001352e5b450a5112c5c898295b7a026d3b09b55a69c97e8524ed3a8bdaf' ),
        'Signature.php' => array( 'class' => 'WP_E_Signature', 'properties' => array( 'joinTable' ), 'baseline' => 'dece6a1f455e94605ca7ad956a7d7351c88398b8ebf2293afc23bd72a2e64599' ),
    );
}
