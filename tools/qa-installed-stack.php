<?php
/** Builds a synthetic installation beside the live site; never copies live rows. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
$phase = $argv[1] ?? ''; $work = $argv[2] ?? '';
set_exception_handler( static function( $error ) use ( $phase ) {
    $detail = array( 'class' => get_class( $error ), 'file' => basename( $error->getFile() ), 'line' => $error->getLine() );
    if ( isset( $GLOBALS['qa_stack_result'] ) ) $GLOBALS['qa_stack_result']['fatal'] = $detail;
    else echo json_encode( array( 'phase' => $phase, 'completed' => false, 'fatal' => $detail ) ) . "\n";
    exit( 1 );
} );
if ( ! in_array( $phase, array( 'prepare', 'install', 'activate', 'verify', 'cleanup', 'cleanup-stale' ), true ) || ! preg_match( '#^/tmp/trb-portal-stack\.[a-zA-Z0-9]{8}$#D', $work ) || realpath( $work ) !== $work || is_link( $work ) ) exit( 2 );
$root = $work . '/wordpress'; $settings_file = $work . '/qa-settings.json';
if ( 'prepare' === $phase || 'cleanup' === $phase || 'cleanup-stale' === $phase ) {
    define( 'SHORTINIT', true );
    require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
    global $wpdb;
    $cleanup_fixture = static function( $settings_file ) use ( $wpdb ) {
        if ( ! is_file( $settings_file ) || is_link( $settings_file ) ) return 0;
        $settings = json_decode( file_get_contents( $settings_file ), true, 16, JSON_THROW_ON_ERROR );
        $prefix = $settings['prefix'];
        if ( ! preg_match( '/^trbqa_[a-f0-9]{16}_$/D', $prefix ) || $prefix === $wpdb->prefix ) throw new RuntimeException( 'Unsafe QA cleanup prefix.' );
        $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
        if ( in_array( $prefix . 'users', $tables, true ) ) {
            $foreign_users = $wpdb->get_var( "SELECT COUNT(*) FROM `{$prefix}users` WHERE user_email NOT LIKE '%@example.invalid'" );
            if ( null === $foreign_users || (int) $foreign_users > 0 ) throw new RuntimeException( 'Unexpected users in QA tables.' );
        }
        $outside_reference = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME LIKE %s AND TABLE_NAME NOT LIKE %s', $wpdb->esc_like( $prefix ) . '%', $wpdb->esc_like( $prefix ) . '%' ) );
        if ( (int) $outside_reference > 0 ) throw new RuntimeException( 'QA tables referenced outside the fixture.' );
        $wpdb->query( 'SET SESSION FOREIGN_KEY_CHECKS=0' );
        try { foreach ( $tables as $table ) {
            if ( ! str_starts_with( $table, $prefix ) || ! preg_match( '/^[a-zA-Z0-9_]+$/D', $table ) ) throw new RuntimeException( 'Unsafe QA table.' );
            if ( false === $wpdb->query( 'DROP TABLE `' . $table . '`' ) ) throw new RuntimeException( 'QA cleanup failed.' );
        } } finally { $wpdb->query( 'SET SESSION FOREIGN_KEY_CHECKS=1' ); }
        return count( $tables );
    };
    if ( 'cleanup' === $phase ) {
        echo json_encode( array( 'synthetic_tables_removed' => $cleanup_fixture( $settings_file ) ) ) . "\n";
        exit;
    }
    if ( 'cleanup-stale' === $phase ) {
        $removed = 0; $table_count = 0;
        foreach ( glob( '/tmp/trb-portal-stack.*', GLOB_ONLYDIR ) as $stale ) {
            if ( $stale === $work || ! preg_match( '#^/tmp/trb-portal-stack\.[a-zA-Z0-9]{8}$#D', $stale ) || realpath( $stale ) !== $stale || is_link( $stale ) || filemtime( $stale ) > time() - 600 ) continue;
            if ( function_exists( 'posix_geteuid' ) && fileowner( $stale ) !== posix_geteuid() ) continue;
            if ( ! is_file( $stale . '/qa-settings.json' ) || ! is_file( $stale . '/candidate/tools/qa-installed-stack.php' ) ) continue;
            $table_count += $cleanup_fixture( $stale . '/qa-settings.json' );
            $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $stale, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
            foreach ( $entries as $entry ) {
                $ok = $entry->isDir() && ! $entry->isLink() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
                if ( ! $ok ) throw new RuntimeException( 'Stale QA workspace cleanup failed.' );
            }
            if ( ! rmdir( $stale ) ) throw new RuntimeException( 'Stale QA workspace cleanup incomplete.' );
            $removed++;
        }
        echo json_encode( array( 'synthetic_workspaces_removed' => $removed, 'synthetic_tables_removed' => $table_count ) ) . "\n";
        exit;
    }
    if ( file_exists( $root ) || file_exists( $settings_file ) ) throw new RuntimeException( 'QA workspace already initialized.' );
    $prefix = 'trbqa_' . bin2hex( random_bytes( 8 ) ) . '_';
    $plugins = @unserialize( (string) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='active_plugins'" ), array( 'allowed_classes' => false ) );
    if ( ! is_array( $plugins ) || count( $plugins ) < 10 ) throw new RuntimeException( 'Installed plugin list unavailable.' );
    mkdir( $root . '/wp-content/mu-plugins', 0700, true ); mkdir( $root . '/wp-content/plugins', 0700 ); mkdir( $root . '/wp-content/themes', 0700 );
    foreach ( array( 'wp-admin', 'wp-includes' ) as $name ) if ( ! symlink( ABSPATH . $name, $root . '/' . $name ) ) throw new RuntimeException( 'Core link failed.' );
    foreach ( glob( ABSPATH . '*.php' ) as $path ) if ( 'wp-config.php' !== basename( $path ) && ! symlink( $path, $root . '/' . basename( $path ) ) ) throw new RuntimeException( 'Core link failed.' );
    foreach ( glob( ABSPATH . 'wp-content/plugins/*', GLOB_ONLYDIR ) as $path ) if ( ! symlink( $path, $root . '/wp-content/plugins/' . basename( $path ) ) ) throw new RuntimeException( 'Plugin link failed.' );
    // The licensed plugin stays on this host; patch a private QA copy only.
    $signature_source = ABSPATH . 'wp-content/plugins/e-signature';
    $signature_target = $root . '/wp-content/plugins/e-signature';
    if ( ! is_link( $signature_target ) || ! unlink( $signature_target ) ) throw new RuntimeException( 'Signature QA shadow unavailable.' );
    $copy_tree = static function( $source, $target ) use ( &$copy_tree ) {
        if ( is_link( $source ) ) throw new RuntimeException( 'Unexpected plugin source link.' );
        if ( is_dir( $source ) ) {
            if ( ! mkdir( $target, 0700 ) ) throw new RuntimeException( 'Plugin QA directory unavailable.' );
            foreach ( new DirectoryIterator( $source ) as $entry ) if ( ! $entry->isDot() ) $copy_tree( $entry->getPathname(), $target . '/' . $entry->getFilename() );
        } elseif ( ! copy( $source, $target ) ) throw new RuntimeException( 'Plugin QA copy failed.' );
    };
    $copy_tree( $signature_source, $signature_target );
    require __DIR__ . '/signature-compatibility.php';
    foreach ( trb_signature_compatibility_manifest() as $name => $spec ) {
        $path = $signature_target . '/models/' . $name;
        $source = file_get_contents( $path );
        if ( ! hash_equals( $spec['baseline'], hash( 'sha256', $source ) ) ) throw new RuntimeException( 'Signature QA baseline changed.' );
        file_put_contents( $path, trb_signature_property_patch( $source, $spec['class'], $spec['properties'] ) );
    }
    if ( ! symlink( dirname( __DIR__ ), $root . '/wp-content/themes/docy' ) ) throw new RuntimeException( 'Candidate theme unavailable.' );
    foreach ( glob( dirname( __DIR__ ) . '/integrations/portal-mu-plugins/*.php' ) as $path ) if ( ! copy( $path, $root . '/wp-content/mu-plugins/' . basename( $path ) ) ) throw new RuntimeException( 'Candidate MU copy failed.' );
    $isolation = <<<'PHP'
<?php
add_filter('pre_wp_mail',static function(){return true;},PHP_INT_MAX);
add_filter('pre_http_request',static function(){return new WP_Error('qa_network_blocked','External HTTP disabled.');},PHP_INT_MAX);
add_filter('auto_update_plugin','__return_false');add_filter('auto_update_theme','__return_false');
add_filter('automatic_updater_disabled','__return_true');
PHP;
    file_put_contents( $root . '/wp-content/mu-plugins/00-qa-isolation.php', $isolation );
    $config = "<?php\n";
    foreach ( array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'DB_COLLATE' ) as $name ) $config .= 'define(' . var_export( $name, true ) . ',' . var_export( defined( $name ) ? constant( $name ) : '', true ) . ");\n";
    foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $name ) $config .= 'define(' . var_export( $name, true ) . ',' . var_export( bin2hex( random_bytes( 32 ) ), true ) . ");\n";
    foreach ( array( 'DISABLE_WP_CRON' => true, 'DISALLOW_FILE_MODS' => true, 'AUTOMATIC_UPDATER_DISABLED' => true, 'WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => false, 'WP_ENVIRONMENT_TYPE' => 'local', 'WP_CONTENT_DIR' => $root . '/wp-content', 'WP_PLUGIN_DIR' => $root . '/wp-content/plugins', 'WPMU_PLUGIN_DIR' => $root . '/wp-content/mu-plugins', 'WP_DEFAULT_THEME' => 'docy', 'WP_HOME' => 'http://127.0.0.1', 'WP_SITEURL' => 'http://127.0.0.1' ) as $name => $value ) $config .= 'define(' . var_export( $name, true ) . ',' . var_export( $value, true ) . ");\n";
    $config .= '$table_prefix=' . var_export( $prefix, true ) . ";\nrequire ABSPATH.'wp-settings.php';\n";
    file_put_contents( $root . '/wp-config.php', $config ); chmod( $root . '/wp-config.php', 0600 );
    file_put_contents( $settings_file, json_encode( array( 'prefix' => $prefix, 'plugins' => $plugins ), JSON_THROW_ON_ERROR ) ); chmod( $settings_file, 0600 );
    echo json_encode( array( 'prepared' => true, 'plugin_count' => count( $plugins ), 'contains_live_rows' => false ) ) . "\n";
    exit;
}
$result = array( 'phase' => $phase, 'completed' => false, 'diagnostics' => array() ); $buffer = ob_get_level(); ob_start();
$GLOBALS['qa_stack_result'] =& $result;
register_shutdown_function( static function() use ( &$result, $buffer, $phase ) {
    $error = error_get_last();
    if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR ), true ) ) $result['fatal'] = array( 'file' => basename( $error['file'] ), 'line' => $error['line'] );
    if ( 'activate' === $phase && isset( $result['activating_plugin'] ) && ! isset( $result['fatal'] ) ) $result['completed'] = in_array( $result['activating_plugin'], get_option( 'active_plugins', array() ), true );
    while ( ob_get_level() > $buffer ) ob_end_clean();
    echo json_encode( $result, JSON_UNESCAPED_SLASHES ) . "\n";
} );
set_error_handler( static function( $severity, $message, $file, $line ) use ( &$result ) {
    $key = $severity . ':' . basename( $file ) . ':' . $line;
    $result['diagnostics'][$key] = ( $result['diagnostics'][$key] ?? 0 ) + 1;
    return true;
} );
define( 'ABSPATH', $root . '/' );
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REQUEST_METHOD'] = 'GET';
if ( 'install' === $phase ) define( 'WP_INSTALLING', true );
require $root . '/wp-config.php';
$settings = json_decode( file_get_contents( $settings_file ), true, 16, JSON_THROW_ON_ERROR );
if ( $wpdb->prefix !== $settings['prefix'] || ! str_starts_with( $wpdb->prefix, 'trbqa_' ) ) throw new RuntimeException( 'QA database prefix mismatch.' );
if ( 'install' === $phase ) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    wp_install( 'Collaudo TRB sintetico', 'qa_admin', 'qa-admin@example.invalid', false, '', wp_generate_password( 32 ), 'it_IT' );
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    update_option( 'template', 'docy' ); update_option( 'stylesheet', 'docy' );
    wp_set_current_user( 1 ); $result['plugins_activated'] = array();
    foreach ( $settings['plugins'] as $plugin ) {
        // Activation callbacks may redirect and exit; isolate each lifecycle.
        $command = array( PHP_BINARY, '-d', 'display_errors=0', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=mail,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client', __FILE__, 'activate', $work, $plugin );
        for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
            $process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'file', $work . '/activation-private.log', 'a' ) ), $pipes );
            if ( ! is_resource( $process ) ) throw new RuntimeException( 'Plugin activation process unavailable.' );
            fclose( $pipes[0] ); $reply = stream_get_contents( $pipes[1] ); fclose( $pipes[1] ); $status = proc_close( $process );
            $activated = json_decode( $reply, true );
            if ( ! is_array( $activated ) || isset( $activated['fatal'] ) || isset( $activated['activating_plugin'] ) || true === ( $activated['completed'] ?? false ) ) break;
        }
        $result['activation_bootstrap_attempts'][$plugin] = min( $attempt, 3 );
        $result['plugins_activated'][$plugin] = 0 === $status && true === ( $activated['completed'] ?? false );
        if ( ! $result['plugins_activated'][$plugin] ) $result['activation_details'][$plugin] = is_array( $activated ) ? $activated : array( 'response_unconfirmed' => true );
    }
    $result['completed'] = ! in_array( false, $result['plugins_activated'], true );
} elseif ( 'activate' === $phase ) {
    $plugin = $argv[3] ?? '';
    if ( ! in_array( $plugin, $settings['plugins'], true ) ) throw new RuntimeException( 'Unexpected activation target.' );
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    wp_set_current_user( 1 );
    $result['activating_plugin'] = $plugin;
    $activated = activate_plugin( $plugin );
    $result['completed'] = ! is_wp_error( $activated );
} else {
    $result['active_plugin_count'] = count( get_option( 'active_plugins', array() ) );
    $profiles = trb_portal_profiles(); $role = $profiles['trb']['role'];
    if ( ! get_role( $role ) ) add_role( $role, 'Artista di prova', array( 'read' => true, 'trb_portal_trb' => true ) );
    $password = wp_generate_password( 32 ); $id = wp_insert_user( array( 'user_login' => 'artista_fittizio_tunisia', 'user_email' => 'qa-tunisia@example.invalid', 'user_pass' => $password, 'display_name' => 'Artista Fittizio Tunisia', 'role' => $role ) );
    if ( is_wp_error( $id ) ) throw new RuntimeException( 'Synthetic artist creation failed.' );
    if ( function_exists( 'pw_new_user_approve' ) ) pw_new_user_approve()->update_user_status( $id, 'approve' );
    $result['artist_password_authentication'] = wp_authenticate( 'artista_fittizio_tunisia', $password ) instanceof WP_User;
    wp_set_current_user( $id );
    $result['ordinary_artist'] = ! user_can( $id, 'manage_options' ) && ! trb_portal_is_release_qa_account( get_userdata( $id ) );
    $result['profile_completion_available'] = is_array( trb_portal_artist_profile_completion() );
    if ( function_exists( 'WP_E_Sig' ) ) {
        $result['signature_models'] = false;
        $model = new WP_E_Esigrole(); $signature = new WP_E_Signature();
        $result['signature_models'] = property_exists( $model, 'settings' ) && property_exists( $signature, 'joinTable' );
    }
    $result['completed'] = $result['artist_password_authentication'] && $result['ordinary_artist'] && $result['profile_completion_available'] && $result['active_plugin_count'] === count( $settings['plugins'] );
}
