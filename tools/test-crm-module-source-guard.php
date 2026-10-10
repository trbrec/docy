<?php
require __DIR__ . '/crm-module-source-guard.php';
$directory = sys_get_temp_dir() . '/trb-canonical-guard-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $directory, 0700 ) ) throw new RuntimeException( 'Fixture unavailable.' );
$installed = $directory . '/installed.php'; $candidate = $directory . '/candidate.php';
try {
    file_put_contents( $installed, '<?php // reviewed CRM revision' );
    file_put_contents( $candidate, file_get_contents( $installed ) );
    trb_crm_module_source_guard( array( $installed => $candidate ) );
    file_put_contents( $installed, '<?php // newer correction from the CRM task' );
    $before = file_get_contents( $installed );
    $checks = 1;
    foreach ( array( array( $installed => $candidate ), array( $directory . '/missing.php' => $candidate ), array( $installed => $directory . '/missing.php' ), array() ) as $targets ) {
        $rejected = false;
        try { trb_crm_module_source_guard( $targets ); } catch ( RuntimeException $expected ) { $rejected = true; }
        if ( ! $rejected || file_get_contents( $installed ) !== $before ) throw new RuntimeException( 'Divergent/missing canonical source was accepted or modified.' );
        $checks++;
    }
    foreach ( array( 'deploy-onboarding.php', 'deploy-giulia-support.php' ) as $installer ) {
        $source = file_get_contents( __DIR__ . '/' . $installer );
        if ( ! str_contains( $source, 'trb_crm_module_source_guard(' ) ) throw new RuntimeException( 'Canonical source guard missing.' );
        $forbidden = $installer === 'deploy-giulia-support.php'
            ? array( 'Database::connection', '::install(', 'file_put_contents(', 'bin2hex(random_bytes', 'SELECT ' )
            : array( 'OnboardingWorkflowInstaller::', '$ledger->install()', '$stage($indexPath', '$stage($viewPath', '$stage($repositoryPath', 'onboarding-enabled.json' );
        foreach ( $forbidden as $operation ) if ( str_contains( $source, $operation ) ) throw new RuntimeException( 'Portal release may mutate the separately owned CRM runtime.' );
    }
    echo "PASS {$checks} native CRM module source checks; divergent/newer runtime is preserved.\n";
} finally { foreach ( array( $installed, $candidate ) as $file ) if ( is_file( $file ) ) unlink( $file ); rmdir( $directory ); }
