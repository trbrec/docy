<?php
/** Read-only source inventory. Never bootstraps WordPress or the CRM. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$roots = array(
    'portal_theme' => '/home/customer/www/artist.trbrec.com/public_html/wp-content/themes/docy',
    'portal_mu_plugins' => '/home/customer/www/artist.trbrec.com/public_html/wp-content/mu-plugins',
    'crm_app' => '/home/customer/www/crm.trbrec.com/public_html/app',
    'crm_public_root' => '/home/customer/www/crm.trbrec.com/public_html',
);
$result = array( 'read_only' => true, 'php_version' => PHP_VERSION, 'roots' => array(), 'logs' => array() );
foreach ( $roots as $label => $root ) {
    $files = array();
    if ( is_dir( $root ) ) {
        $iterator = 'portal_theme' === $label
            ? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) )
            : new DirectoryIterator( $root );
        foreach ( $iterator as $file ) {
            if ( ! $file->isFile() || $file->isLink() ) continue;
            $relative = substr( $file->getPathname(), strlen( $root ) + 1 );
            if ( ! preg_match( '/\.(php|js|css|html|yml|json|zip)(?:[.-].*)?$/i', $relative ) && '.htaccess' !== $relative ) continue;
            if ( preg_match( '~(^|/)(\.git|node_modules|vendor)/~', $relative ) ) continue;
            // Only paths, lengths and digests leave the server; no file bodies.
            $files[ $relative ] = array( 'bytes' => $file->getSize(), 'sha256' => hash_file( 'sha256', $file->getPathname() ), 'modified' => gmdate( 'c', $file->getMTime() ) );
        }
    }
    ksort( $files );
    $result['roots'][ $label ] = array( 'available' => is_dir( $root ), 'files' => $files );
}
foreach ( array( 'portal' => '/home/customer/www/artist.trbrec.com/public_html/php_errorlog', 'crm' => '/home/customer/www/crm.trbrec.com/public_html/php_errorlog' ) as $label => $path ) {
    $result['logs'][ $label ] = array( 'available' => is_file( $path ), 'bytes' => is_file( $path ) ? filesize( $path ) : 0 );
}
echo json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
