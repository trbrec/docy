<?php
function trb_theme_gettext_messages( $root ) {
$signatures = array( '__' => array(1,null,null), '_e' => array(1,null,null), 'esc_html__' => array(1,null,null), 'esc_html_e' => array(1,null,null), 'esc_attr__' => array(1,null,null), 'esc_attr_e' => array(1,null,null), '_x' => array(2,1,null), '_ex' => array(2,1,null), 'esc_html_x' => array(2,1,null), 'esc_attr_x' => array(2,1,null), '_n' => array(3,null,1), '_nx' => array(4,3,1), '_n_noop' => array(2,null,1), '_nx_noop' => array(3,2,1) );
$literal = static function( $tokens ) {
    $tokens = array_values( array_filter( $tokens, static fn( $t ) => ! is_array( $t ) || ! in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) );
    if ( count( $tokens ) !== 1 || ! is_array( $tokens[0] ) || $tokens[0][0] !== T_CONSTANT_ENCAPSED_STRING ) return null;
    $s = $tokens[0][1]; $body = substr( $s, 1, -1 );
    return $s[0] === "'" ? str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body ) : stripcslashes( $body );
};
$messages = array(); $dynamic = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
    $relative = str_replace( '\\', '/', substr( $f->getPathname(), strlen( $root ) + 1 ) );
    if ( ! $f->isFile() || $f->getExtension() !== 'php' || preg_match( '#^(tools|tests|integrations|vendor)/#', $relative ) ) continue;
    $tokens = token_get_all( file_get_contents( $f->getPathname() ) );
    foreach ( $tokens as $i => $t ) {
        if ( ! is_array( $t ) || $t[0] !== T_STRING || ! isset( $signatures[$t[1]] ) ) continue;
        $j = $i + 1; while ( isset( $tokens[$j] ) && is_array( $tokens[$j] ) && $tokens[$j][0] === T_WHITESPACE ) ++$j;
        if ( ( $tokens[$j] ?? null ) !== '(' ) continue;
        $args = array( array() ); $level = 1;
        for ( ++$j; isset( $tokens[$j] ); ++$j ) {
            $v = $tokens[$j];
            if ( $v === '(' || $v === '[' || $v === '{' ) ++$level;
            if ( $v === ')' || $v === ']' || $v === '}' ) { --$level; if ( $level === 0 ) break; }
            if ( $v === ',' && $level === 1 ) $args[] = array(); else $args[count($args)-1][] = $v;
        }
        list( $domain, $context, $plural ) = $signatures[$t[1]];
        if ( $literal( $args[$domain] ?? array() ) !== 'docy' ) continue;
        $msg = $literal( $args[0] ); $ctx = $context === null ? null : $literal( $args[$context] ?? array() ); $pl = $plural === null ? null : $literal( $args[$plural] ?? array() );
        if ( $msg === null || $context !== null && $ctx === null || $plural !== null && $pl === null ) { $dynamic[] = array( 'file'=>$relative, 'line'=>$t[2] ); continue; }
        $key = ( $ctx === null ? '' : $ctx . "\4" ) . $msg;
        if ( ! isset( $messages[$key] ) ) $messages[$key] = array( 'message'=>$msg, 'context'=>$ctx, 'plural'=>$pl, 'files'=>array() );
        $messages[$key]['files'][] = $relative;
    }
}
return array( 'messages'=>$messages, 'dynamic_calls'=>$dynamic );
}
if ( PHP_SAPI === 'cli' && realpath( $argv[0] ?? '' ) === __FILE__ ) echo json_encode( trb_theme_gettext_messages( dirname( __DIR__ ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
