<?php
require __DIR__ . '/portal-release-backup.php';
$base = sys_get_temp_dir() . '/trb-release-test-' . bin2hex( random_bytes( 8 ) );
$theme = $base . '/theme'; $candidate = $base . '/candidate'; $backup = $base . '/private/snapshot'; $revision = str_repeat( 'a', 40 );
mkdir( $theme, 0700, true ); mkdir( $candidate, 0700 );
file_put_contents( $theme . '/functions.php', '<?php /* precedente */' );
file_put_contents( $theme . '/.trb-deployed-sha', str_repeat( 'b', 40 ) . "\n" );
file_put_contents( $candidate . '/functions.php', '<?php /* nuovo */' );
file_put_contents( $candidate . '/added.php', '<?php /* aggiunto */' );
file_put_contents( $theme . '/shield (1)_colored.svg', '<svg>precedente</svg>' );
file_put_contents( $candidate . '/shield (1)_colored.svg', '<svg>nuovo</svg>' );
mkdir( $theme . '/integrations/onboarding/crm', 0700, true ); mkdir( $theme . '/tools', 0700 );
file_put_contents( $theme . '/integrations/onboarding/crm/OnboardingPcloud.php', '<?php /* archivio sostituito */' );
file_put_contents( $theme . '/tools/test-onboarding-pcloud.php', '<?php /* prova archivio sostituito */' );
try {
    foreach ( array( '/absolute.php', '../outside.php', 'dir/../outside.php', './relative.php', 'dir//file.php', 'dir/./file.php', 'bad;name.php', "bad\nname.php", 'dir\\file.php' ) as $path ) if ( trb_portal_release_path_is_safe( $path ) ) throw new RuntimeException( 'Unsafe snapshot path accepted.' );
    if ( 5 !== trb_portal_release_snapshot( $theme, $candidate, $backup, $revision ) ) throw new RuntimeException( 'Snapshot count.' );
    copy( $candidate . '/functions.php', $theme . '/functions.php' ); copy( $candidate . '/added.php', $theme . '/added.php' );
    copy( $candidate . '/shield (1)_colored.svg', $theme . '/shield (1)_colored.svg' );
    file_put_contents( $theme . '/.trb-deployed-sha', $revision . "\n" );
    file_put_contents( $theme . '/unrelated.txt', 'Creato da un altro processo.' );
    file_put_contents( $theme . '/tools/test-onboarding-pcloud.php', 'Modifica concorrente.' );
    $refused = false;
    try { trb_portal_release_prune( $theme, $candidate, $backup, $revision ); } catch ( RuntimeException $error ) { $refused = true; }
    if ( ! $refused || ! is_file( $theme . '/integrations/onboarding/crm/OnboardingPcloud.php' ) ) throw new RuntimeException( 'Prune modified files before verifying every backup.' );
    copy( $backup . '/files/tools/test-onboarding-pcloud.php', $theme . '/tools/test-onboarding-pcloud.php' );
    if ( 2 !== trb_portal_release_prune( $theme, $candidate, $backup, $revision ) || file_exists( $theme . '/integrations/onboarding/crm/OnboardingPcloud.php' ) || file_exists( $theme . '/tools/test-onboarding-pcloud.php' ) ) throw new RuntimeException( 'Reviewed obsolete module cleanup failed.' );
    file_put_contents( $theme . '/functions.php', 'Modifica concorrente.' );
    $refused = false;
    try { trb_portal_release_restore( $theme, $backup, $revision ); } catch ( RuntimeException $error ) { $refused = true; }
    if ( ! $refused || ! is_file( $theme . '/added.php' ) ) throw new RuntimeException( 'Concurrent edit must stop rollback before writes.' );
    copy( $candidate . '/functions.php', $theme . '/functions.php' );
    trb_portal_release_restore( $theme, $backup, $revision );
    if ( file_get_contents( $theme . '/integrations/onboarding/crm/OnboardingPcloud.php' ) !== '<?php /* archivio sostituito */' || file_get_contents( $theme . '/tools/test-onboarding-pcloud.php' ) !== '<?php /* prova archivio sostituito */' ) throw new RuntimeException( 'Deleted obsolete sources were not restored.' );
    if ( file_get_contents( $theme . '/functions.php' ) !== '<?php /* precedente */' || file_get_contents( $theme . '/shield (1)_colored.svg' ) !== '<svg>precedente</svg>' || is_file( $theme . '/added.php' ) || ! is_file( $theme . '/unrelated.txt' ) || trim( file_get_contents( $theme . '/.trb-deployed-sha' ) ) !== str_repeat( 'b', 40 ) ) throw new RuntimeException( 'Rollback byte preservation failed.' );
    echo "Private snapshot hashes, partial release rollback, concurrent edit refusal and unrelated file preservation verified.\n";
} finally {
    $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $entries as $entry ) $entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
    rmdir( $base );
}
