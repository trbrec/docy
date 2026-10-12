<?php
/** Read-only source and database metadata inventory; never starts application workers. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
if ( in_array( '--crm-schema', $argv, true ) ) {
    ini_set( 'display_errors', '0' );
    try {
        require '/home/customer/www/crm.trbrec.com/public_html/app/Core.php';
        \TrbCrm\Env::load( '/home/customer/www/crm.trbrec.com/public_html/.env' );
        $db = \TrbCrm\Database::connection();
        $db->exec( 'SET SESSION TRANSACTION READ ONLY' );
        $db->beginTransaction();
        $schema = array( 'read_only' => true, 'contains_rows' => false, 'tables' => array() );
        $tables = $db->query( "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME" )->fetchAll( PDO::FETCH_COLUMN );
        foreach ( $tables as $table ) {
            if ( ! preg_match( '/^[a-zA-Z0-9_]+$/D', $table ) ) throw new RuntimeException( 'Unexpected table identifier.' );
            $definition = $db->query( 'SHOW CREATE TABLE `' . $table . '`' )->fetch( PDO::FETCH_NUM );
            // Table structure only: omit sequence positions, triggers, routines and all records.
            $schema['tables'][ $table ] = preg_replace( '/\sAUTO_INCREMENT=\d+\b/', '', $definition[1] );
        }
        $db->rollBack();
        echo json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
    } catch ( Throwable $error ) {
        if ( isset( $db ) && $db->inTransaction() ) $db->rollBack();
        fwrite( STDERR, "Read-only CRM structure inventory could not complete.\n" );
        exit( 1 );
    }
    exit;
}
if ( in_array( '--log-summary', $argv, true ) ) {
    $summary = array( 'read_only' => true, 'contains_messages' => false, 'logs' => array() );
    foreach ( array( 'portal' => '/home/customer/www/artist.trbrec.com/public_html/php_errorlog', 'crm' => '/home/customer/www/crm.trbrec.com/public_html/php_errorlog' ) as $label => $path ) {
        $entry = array( 'available' => is_file( $path ), 'bytes' => is_file( $path ) ? filesize( $path ) : 0, 'scanned_bytes' => 0, 'groups' => array(), 'group_dates_utc' => array() );
        if ( $entry['available'] ) {
            $handle = fopen( $path, 'rb' );
            if ( false === $handle ) throw new RuntimeException( 'Log summary unavailable.' );
            $start = max( 0, $entry['bytes'] - 8 * 1024 * 1024 );
            fseek( $handle, $start );
            if ( $start ) fgets( $handle );
            $offset = ftell( $handle );
            while ( false !== ( $line = fgets( $handle ) ) ) {
                if ( ! preg_match( '~PHP (Warning|Notice|Deprecated|Fatal error|Parse error|Recoverable fatal error):~', $line, $severity ) ) continue;
                $component = 'unclassified';
                if ( preg_match( '~ in [^\r\n]*(/(?:wp-content|app)/[a-zA-Z0-9_./-]+\.php) on line (\d+)~', $line, $location ) ) $component = $location[1] . ':' . $location[2];
                $category = 'other';
                foreach ( array( 'Undefined array key', 'Undefined variable', 'Permission denied', 'Failed to open stream', 'Allowed memory size', 'Uncaught', 'SQLSTATE', 'Deprecated', 'Maximum execution time' ) as $candidate ) if ( stripos( $line, $candidate ) !== false ) { $category = $candidate; break; }
                // Never return log text: it may contain artist data, tokens or provider responses.
                $key = $severity[1] . '|' . $component . '|' . $category;
                if ( isset( $entry['groups'][ $key ] ) || count( $entry['groups'] ) < 200 ) {
                    $entry['groups'][ $key ] = ( $entry['groups'][ $key ] ?? 0 ) + 1;
                    if ( preg_match( '/^\[([0-9]{2}-[A-Za-z]{3}-[0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2} (?:UTC|Europe\/Rome))\]/', $line, $date ) && false !== ( $timestamp = strtotime( $date[1] ) ) ) {
                        $iso = gmdate( 'c', $timestamp );
                        $dates = $entry['group_dates_utc'][$key] ?? array( 'first' => $iso, 'last' => $iso );
                        $entry['group_dates_utc'][$key] = array( 'first' => min( $dates['first'], $iso ), 'last' => max( $dates['last'], $iso ) );
                    }
                }
            }
            $entry['scanned_bytes'] = ftell( $handle ) - $offset;
            fclose( $handle );
            arsort( $entry['groups'] );
        }
        $summary['logs'][ $label ] = $entry;
    }
    echo json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
    exit;
}
if ( in_array( '--crm-shape', $argv, true ) ) {
    ini_set( 'display_errors', '0' );
    try {
        // Core declares connection helpers only. Do not construct SubmissionRepository:
        // its constructor runs migrations and may queue production work.
        require '/home/customer/www/crm.trbrec.com/public_html/app/Core.php';
        \TrbCrm\Env::load( '/home/customer/www/crm.trbrec.com/public_html/.env' );
        $db = \TrbCrm\Database::connection();
        $db->exec( 'SET SESSION TRANSACTION READ ONLY' );
        $db->beginTransaction();
        $shape = array( 'read_only' => true, 'table_engines' => array() );
        foreach ( array( 'contacts', 'submissions', 'contracts', 'onboarding_practices' ) as $table ) {
            $statement = $db->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?' );
            $statement->execute( array( $table ) ); $shape['table_engines'][$table] = $statement->fetchColumn() ?: null;
        }
        $shape['protected_submission_723_exists'] = 1 === (int) $db->query( 'SELECT COUNT(*) FROM submissions WHERE id=723' )->fetchColumn();
        $statement = $db->prepare( 'SELECT COUNT(*) FROM contacts WHERE email_normalized=?' );
        $statement->execute( array( 'a.tognassi@gmail.com' ) );
        $shape['protected_andrea_contact_exists'] = (int) $statement->fetchColumn() > 0;
        // Count explicit historical test markers, never infer QA from an artist's name.
        $testPredicate = "(s.public_id LIKE 'TEST-%' OR s.source_tab='CRM_TEST_PERMANENT')";
        $shape['marked_test_submissions_excluding_723'] = (int) $db->query( "SELECT COUNT(*) FROM submissions s WHERE {$testPredicate} AND s.id<>723" )->fetchColumn();
        $shape['marked_test_contracts_excluding_723'] = (int) $db->query( "SELECT COUNT(*) FROM contracts c JOIN submissions s ON s.id=c.submission_id WHERE {$testPredicate} AND s.id<>723" )->fetchColumn();
        if ( $shape['table_engines']['onboarding_practices'] !== null ) $shape['marked_test_practices_excluding_723'] = (int) $db->query( "SELECT COUNT(*) FROM onboarding_practices p JOIN contracts c ON c.id=p.contract_id JOIN submissions s ON s.id=c.submission_id WHERE {$testPredicate} AND s.id<>723" )->fetchColumn();
        $db->rollBack();
        echo json_encode( $shape, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) . "\n";
    } catch ( Throwable $error ) {
        if ( isset( $db ) && $db->inTransaction() ) $db->rollBack();
        fwrite( STDERR, "Read-only CRM metadata inventory could not complete.\n" );
        exit( 1 );
    }
    exit;
}
if ( in_array( '--database-shape', $argv, true ) ) {
    define( 'SHORTINIT', true );
    define( 'DISABLE_WP_CRON', true );
    require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
    global $wpdb;
    $shape = array( 'read_only' => true, 'table_engines' => array() );
    foreach ( array( 'usermeta' => $wpdb->usermeta, 'options' => $wpdb->options, 'posts' => $wpdb->posts, 'postmeta' => $wpdb->postmeta, 'connector_outbox' => $wpdb->prefix . 'trb_crm_sync_outbox' ) as $label => $table ) $shape['table_engines'][ $label ] = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
    $active = @unserialize( (string) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='active_plugins'" ), array( 'allowed_classes' => false ) );
    $shape['active_plugins'] = array();
    foreach ( is_array( $active ) ? $active : array() as $plugin ) {
        if ( ! is_string( $plugin ) || ! preg_match( '~^[a-zA-Z0-9_-]+/[a-zA-Z0-9_.-]+\.php$~D', $plugin ) ) continue;
        $path = '/home/customer/www/artist.trbrec.com/public_html/wp-content/plugins/' . $plugin;
        if ( ! is_file( $path ) || is_link( $path ) ) continue;
        $header = file_get_contents( $path, false, null, 0, 8192 );
        $version = preg_match( '/^[ \t\/*#]*Version:\s*([0-9A-Za-z_.+-]{1,40})/mi', $header, $match ) ? $match[1] : null;
        $shape['active_plugins'][$plugin] = array( 'version' => $version, 'sha256' => hash_file( 'sha256', $path ) );
    }
    $shape['fixture_198_exists'] = 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID=198 AND user_login=%s AND user_email=%s", 'trb_audit_20261010', 'portal-audit-20261010@example.invalid' ) );
    $shape['protected_andrea_account_exists'] = 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email=%s", 'a.tognassi@gmail.com' ) );
    $shape['fixture_12351_exists'] = 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID=12351 AND post_author=198 AND post_type='trb_release' AND post_title=%s", 'AUDIT TEST 20261010 — synthetic release, no distribution' ) );
    echo json_encode( $shape, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) . "\n";
    exit;
}
$source_names = array( 'ActivationFlow.php', 'CandidateContractReview.php', 'CandidateFollowupPolicy.php', 'Controller.php', 'Core.php', 'MailRecovery.php', 'MaterialArchivePolicy.php', 'OnboardingAdmin.php', 'OnboardingContractCatalog.php', 'OnboardingContractWorkflow.php', 'OnboardingDrive.php', 'OnboardingEntry.php', 'OnboardingIdentity.php', 'OnboardingIntake.php', 'OnboardingLedger.php', 'OnboardingMail.php', 'OnboardingPcloud.php', 'OnboardingPolicy.php', 'OnboardingRuntime.php', 'OnboardingService.php', 'OnboardingTransport.php', 'OnboardingWorkflowInstaller.php', 'PcloudDemoStorage.php', 'ProposalMailCatalog.php', 'ProposalMailCopy.php', 'SubmissionRepository.php', 'SymphonicSheet.php', 'View.php', 'bootstrap.php', 'routes.php' );
if ( in_array( '--sources', $argv, true ) ) {
    $source_paths = array();
    foreach ( $source_names as $name ) $source_paths[ 'crm/app/' . $name ] = '/home/customer/www/crm.trbrec.com/public_html/app/' . $name;
    foreach ( array( 'index.php', 'pcloud-material.php', 'download-folder-handler.php', '.htaccess', 'app-20260831-r27.js', 'app-20260828-v7.css', 'release-review-r2.css', 'config/routes.php' ) as $name ) $source_paths[ 'crm/' . $name ] = '/home/customer/www/crm.trbrec.com/public_html/' . $name;
    preg_match_all( '~(/assets/[a-zA-Z0-9_-]+\.(?:js|css|cmd))~', file_get_contents( '/home/customer/www/crm.trbrec.com/public_html/app/View.php' ), $assets );
    foreach ( array_unique( $assets[1] ) as $asset ) $source_paths[ 'crm' . $asset ] = '/home/customer/www/crm.trbrec.com/public_html' . $asset;
    foreach ( array( 'trb-crm-sync.php', 'trb-login-cache-guard.php', 'trb-z-crm-release-sync-r26.php' ) as $name ) $source_paths[ 'portal/mu-plugins/' . $name ] = '/home/customer/www/artist.trbrec.com/public_html/wp-content/mu-plugins/' . $name;
    foreach ( array( 'Model.php', 'Esigrole.php', 'User.php', 'Signature.php' ) as $name ) $source_paths[ 'portal/signature-models/' . $name ] = '/home/customer/www/artist.trbrec.com/public_html/wp-content/plugins/e-signature/models/' . $name;
    $compatibility_names = array( 'General.php', 'Esign_core_load.php', 'e-signature.php', 'Document.php', 'Common.php', 'Addon.php', 'esig-dan-admin.php', 'esig-pdf-admin.php', 'esig-at-admin.php', 'esig-active-campaign-admin.php', 'esig-ds-admin.php', 'esig-aams-admin.php', 'esig-reminders-admin.php', 'esig-logo-branding-admin.php', 'esig-add-custom-message.php', 'esig-usr-admin.php', 'esig-url-admin.php', 'esig-assign-signer-order-admin.php', 'esig-assign-approval-signer-admin.php', 'esig-pdf-to-email-admin.php', 'esig-sad.php', 'esig-auto-user-register-admin.php', 'Site_Tools_Client.php' );
    $plugin_root = '/home/customer/www/artist.trbrec.com/public_html/wp-content/plugins/';
    foreach ( array( 'e-signature', 'e-signature-business-add-ons', 'sg-cachepress' ) as $plugin ) {
        $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_root . $plugin, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $entries as $entry ) if ( $entry->isFile() && ! $entry->isLink() && in_array( $entry->getBasename(), $compatibility_names, true ) ) {
            $relative = str_replace( '\\', '/', substr( $entry->getPathname(), strlen( $plugin_root ) ) );
            $source_paths['portal/plugin-compatibility/' . $relative] = $entry->getPathname();
        }
    }
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
