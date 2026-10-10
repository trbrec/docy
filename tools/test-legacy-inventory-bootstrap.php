<?php
/** Exercise the real CLI scanner against an isolated WordPress bootstrap fixture. */
$source = file_get_contents( __DIR__ . '/audit-legacy-artist-materials.php' );
if ( false === $source ) throw new RuntimeException( 'Scanner source unavailable.' );
$sandbox = sys_get_temp_dir() . '/trb-inventory-bootstrap-' . bin2hex( random_bytes( 8 ) );
$revision = str_repeat( 'a', 40 );
$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
    if ( ! $condition ) throw new RuntimeException( $message );
    ++$checks;
};
$run = static function ( $script, $mode ) use ( $revision ) {
    $process = proc_open( array( PHP_BINARY, $script, $revision, $mode ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
    if ( ! is_resource( $process ) ) throw new RuntimeException( 'Isolated scanner runner unavailable.' );
    fclose( $pipes[0] );
    $stdout = stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
    $stderr = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
    return array( proc_close( $process ), $stdout, $stderr );
};
try {
    foreach ( array( $sandbox, "$sandbox/tools", "$sandbox/private", "$sandbox/core" ) as $directory ) {
        if ( ! mkdir( $directory, 0700 ) ) throw new RuntimeException( 'Sandbox creation failed.' );
    }
    file_put_contents( "$sandbox/.trb-deployed-sha", $revision );
    $sentinel = "<?php /* fixture core must remain untouched */\n";
    file_put_contents( "$sandbox/core/page-wp-admin.php", $sentinel );
    $bootstrap = '<?php ' .
        '$file=' . var_export( "$sandbox/core/page-wp-admin.php", true ) . ';' .
        '$path="core-bootstrap-global";$root="core-bootstrap-global";$theme="core-bootstrap-global";' .
        'function trb_demo_webdav_request(){}' .
        'function trb_demo_settings(){return ["webdav_endpoint"=>"https://example.invalid"];}' .
        'function wp_parse_url($url,$component=-1){return parse_url($url,$component);}' .
        'function trb_demo_remote_url($endpoint,$path){return $endpoint.$path;}' .
        'function wp_remote_request($url,$args){return ["response"=>["code"=>301]];}' .
        'function wp_remote_retrieve_response_code($response){return $response["response"]["code"];}' .
        'function is_wp_error($value){return false;}' .
        'function wp_json_encode($value){return json_encode($value);}' .
        'function update_option($name,$value,$autoload){file_put_contents(' . var_export( "$sandbox/private/imported-option.json", true ) . ',json_encode(["name"=>$name,"value"=>$value]));}';
    file_put_contents( "$sandbox/wp-load.php", $bootstrap );
    // Only filesystem destinations change; the scanner's logic and save closure run as shipped.
    $mapped = str_replace(
        array( '/home/customer/www/new1.trbrec.com/private/trb-site-studio', '/home/customer/www/new1.trbrec.com/public_html/wp-load.php', '/home/customer/www/artist.trbrec.com/public_html/wp-load.php' ),
        array( "$sandbox/private", "$sandbox/wp-load.php", "$sandbox/wp-load.php" ),
        $source
    );
    $script = "$sandbox/tools/audit-legacy-artist-materials.php";
    file_put_contents( $script, $mapped );
    $result = $run( $script, 'scan' );
    $assert( 0 === $result[0] && '' === $result[1] && '' === $result[2], 'Scanner did not finish cleanly.' );
    $inventory_path = "$sandbox/private/legacy-artistic-inventory.json";
    $inventory = json_decode( (string) file_get_contents( $inventory_path ), true );
    $assert( is_array( $inventory ) && '/Upload files - TRB rec' === $inventory['root'], 'Inventory was not written to the private destination.' );
    $assert( 'http_301' === ( $inventory['errors'][0]['code'] ?? '' ), 'Interrupted transport was not recorded.' );
    $assert( $sentinel === file_get_contents( "$sandbox/core/page-wp-admin.php" ), 'WordPress bootstrap clobbered the inventory destination and damaged core.' );
    clearstatcache( true, $inventory_path );
    $assert( 0600 === ( fileperms( $inventory_path ) & 0777 ), 'Inventory permissions are too broad.' );
    $assert( ! file_exists( "$inventory_path.tmp" ), 'Atomic save left a temporary file behind.' );
    $result = $run( $script, 'import' );
    $assert( 0 === $result[0] && '' === $result[1] && '' === $result[2], 'Private inventory import failed.' );
    $imported = json_decode( (string) file_get_contents( "$sandbox/private/imported-option.json" ), true );
    $assert( 'trb_studio_legacy_material_audit' === ( $imported['name'] ?? '' ), 'Import selected the wrong option.' );
    $assert( '/Upload files - TRB rec' === ( $imported['value']['root'] ?? '' ), 'Import read a bootstrap-global path instead of the inventory.' );
    $assert( ! isset( $imported['value']['queue'] ) && ! isset( $imported['value']['seen'] ), 'Import retained private crawler state.' );
    // Negative control: reproduce the original generic-global defect only inside this disposable sandbox.
    file_put_contents( "$sandbox/tools/broken-scanner.php", str_replace( '$trbLegacyInventoryFile', '$file', $mapped ) );
    $result = $run( "$sandbox/tools/broken-scanner.php", 'scan' );
    $assert( 0 === $result[0] && '' === $result[2], 'Original-defect control could not run.' );
    $assert( $sentinel !== file_get_contents( "$sandbox/core/page-wp-admin.php" ), 'Regression test did not detect the original core-overwrite defect.' );
    echo "Legacy inventory bootstrap isolation verified ($checks checks; original-defect control detected).\n";
} finally {
    if ( is_dir( $sandbox ) ) {
        $paths = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sandbox, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $paths as $path ) {
            if ( $path->isDir() && ! $path->isLink() ) rmdir( $path->getPathname() );
            else unlink( $path->getPathname() );
        }
        rmdir( $sandbox );
    }
}
