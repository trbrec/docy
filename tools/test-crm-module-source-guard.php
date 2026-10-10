<?php
require __DIR__ . '/crm-module-source-guard.php';
$directory = sys_get_temp_dir() . '/trb-canonical-guard-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $directory, 0700 ) ) throw new RuntimeException( 'Fixture unavailable.' );
$root = realpath( $directory ); $installed = $root . '/installed.php'; $receipt = $root . '/receipt.json';
try {
    file_put_contents( $installed, '<?php // reviewed private CRM revision' );
    $record = array( 'revision' => str_repeat( 'a', 40 ), 'verified' => true, 'files' => array( array( 'path' => 'installed.php', 'sha256' => hash_file( 'sha256', $installed ) ) ) );
    $save = static function( $value ) use ( $receipt ) { file_put_contents( $receipt, json_encode( $value, JSON_THROW_ON_ERROR ) ); };
    $save( $record );
    if ( trb_crm_module_source_guard( $root, $receipt, array( 'installed.php' ) ) !== str_repeat( 'a', 40 ) ) throw new RuntimeException( 'Owner receipt not accepted.' );
    file_put_contents( $installed, '<?php // newer private CRM correction' );
    $before = file_get_contents( $installed ); $checks = 1;
    $reject = static function( $operation ) use ( $installed, $before, &$checks ) {
        $refused = false; try { $operation(); } catch ( RuntimeException $expected ) { $refused = true; }
        if ( ! $refused || file_get_contents( $installed ) !== $before ) throw new RuntimeException( 'Unsafe receipt accepted or CRM source modified.' );
        $checks++;
    };
    $reject( static fn() => trb_crm_module_source_guard( $root, $receipt, array( 'installed.php' ) ) );
    $reject( static fn() => trb_crm_module_source_guard( $root, $root . '/missing.json', array( 'installed.php' ) ) );
    $record['revision'] = str_repeat( 'b', 40 ); $record['files'][0]['sha256'] = hash_file( 'sha256', $installed ); $save( $record );
    trb_crm_module_source_guard( $root, $receipt, array( 'installed.php' ) ); $checks++;
    $reject( static fn() => trb_crm_module_source_guard( $root, $receipt, array( 'missing.php' ) ) );
    $reject( static fn() => trb_crm_module_source_guard( $root, $receipt, array() ) );
    $invalid = $record; $invalid['verified'] = false; $save( $invalid );
    $reject( static fn() => trb_crm_module_source_guard( $root, $receipt, array( 'installed.php' ) ) );
    $invalid = $record; $invalid['files'][] = $record['files'][0]; $save( $invalid );
    $reject( static fn() => trb_crm_module_source_guard( $root, $receipt, array( 'installed.php' ) ) );
    $invalid = $record; $invalid['files'][0]['path'] = '../outside.php'; $save( $invalid );
    $reject( static fn() => trb_crm_module_source_guard( $root, $receipt, array( 'installed.php' ) ) );
    foreach ( array( 'deploy-onboarding.php', 'deploy-giulia-support.php' ) as $installer ) {
        $source = file_get_contents( __DIR__ . '/' . $installer );
        if ( ! str_contains( $source, 'trb_crm_module_source_guard(' ) ) throw new RuntimeException( 'Canonical owner receipt guard missing.' );
        $forbidden = $installer === 'deploy-giulia-support.php'
            ? array( 'Database::connection', '::install(', 'file_put_contents(', 'bin2hex(random_bytes', 'SELECT ' )
            : array( 'OnboardingWorkflowInstaller::', '$ledger->install()', '$stage($indexPath', '$stage($viewPath', '$stage($repositoryPath', 'onboarding-enabled.json' );
        foreach ( $forbidden as $operation ) if ( str_contains( $source, $operation ) ) throw new RuntimeException( 'Portal may mutate the separately owned CRM runtime.' );
    }
    echo "PASS {$checks} native private CRM owner receipt checks; source changes, missing modules and unsafe manifests refused without modifying CRM.\n";
} finally { foreach ( array( $installed, $receipt ) as $file ) if ( is_file( $file ) ) unlink( $file ); rmdir( $directory ); }
