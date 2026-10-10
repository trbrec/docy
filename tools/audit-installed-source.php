<?php
/** Read-only source inventory. Never bootstraps WordPress or the CRM. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$source_names = array( 'ActivationFlow.php', 'CandidateContractReview.php', 'CandidateFollowupPolicy.php', 'Controller.php', 'Core.php', 'MailRecovery.php', 'MaterialArchivePolicy.php', 'OnboardingAdmin.php', 'OnboardingContractCatalog.php', 'OnboardingContractWorkflow.php', 'OnboardingDrive.php', 'OnboardingEntry.php', 'OnboardingIdentity.php', 'OnboardingIntake.php', 'OnboardingLedger.php', 'OnboardingMail.php', 'OnboardingPcloud.php', 'OnboardingPolicy.php', 'OnboardingRuntime.php', 'OnboardingService.php', 'OnboardingTransport.php', 'OnboardingWorkflowInstaller.php', 'PcloudDemoStorage.php', 'ProposalMailCatalog.php', 'ProposalMailCopy.php', 'SubmissionRepository.php', 'SymphonicSheet.php', 'View.php', 'bootstrap.php', 'routes.php' );
if ( in_array( '--sources', $argv, true ) ) {
    $source_paths = array();
    foreach ( $source_names as $name ) $source_paths[ 'crm/app/' . $name ] = '/home/customer/www/crm.trbrec.com/public_html/app/' . $name;
    foreach ( array( 'index.php', 'pcloud-material.php', 'download-folder-handler.php', '.htaccess', 'app-20260831-r27.js', 'app-20260828-v7.css', 'release-review-r2.css', 'config/routes.php' ) as $name ) $source_paths[ 'crm/' . $name ] = '/home/customer/www/crm.trbrec.com/public_html/' . $name;
    preg_match_all( '~(/assets/[a-zA-Z0-9_-]+\.(?:js|css|cmd))~', file_get_contents( '/home/customer/www/crm.trbrec.com/public_html/app/View.php' ), $assets );
    foreach ( array_unique( $assets[1] ) as $asset ) $source_paths[ 'crm' . $asset ] = '/home/customer/www/crm.trbrec.com/public_html' . $asset;
    foreach ( array( 'trb-crm-sync.php', 'trb-login-cache-guard.php', 'trb-z-crm-release-sync-r26.php' ) as $name ) $source_paths[ 'portal/mu-plugins/' . $name ] = '/home/customer/www/artist.trbrec.com/public_html/wp-content/mu-plugins/' . $name;
    $sources = array();
    foreach ( $source_paths as $name => $path ) {
        if ( ! is_file( $path ) || is_link( $path ) ) throw new RuntimeException( 'An explicitly selected source file is unavailable.' );
        $content = file_get_contents( $path );
        // Configuration files, database rows and artist uploads are never selected.
        // Mask any inline secret literal before the code leaves this server.
        $content = preg_replace( '/-----BEGIN (?:[A-Z ]+ )?PRIVATE KEY-----.*?-----END (?:[A-Z ]+ )?PRIVATE KEY-----/s', '[REDACTED PRIVATE KEY]', $content );
        $content = preg_replace_callback( '~((?:[\'\"]|\$)?[a-z0-9_]*(?:password|passphrase|secret|access_token|refresh_token|api_key|private_key)[a-z0-9_]*(?:[\'\"])?\s*(?:=>|=)\s*)([\'\"])([^\'\"\r\n]{8,})\2~i', static function( $match ) { return $match[1] . $match[2] . '[REDACTED INLINE SECRET]' . $match[2]; }, $content, -1, $redactions );
        $sources[ $name ] = array( 'sha256' => hash_file( 'sha256', $path ), 'inline_redactions' => $redactions, 'content' => $content );
    }
    echo json_encode( array( 'read_only' => true, 'sources' => $sources ), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
    exit;
}
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
