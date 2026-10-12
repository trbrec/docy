<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
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
$private = array(
    'tools/test-legacy-inventory-bootstrap.php', 'tests/php/synthetic.php', 'integrations/site-studio/test-synthetic.php',
    'tools/fixtures-onboarding-entry-mail.php', 'tools/site-preview.test.cjs',
    'integrations/site-studio/artist-cards.test.cjs', 'integrations/site-studio/editor-browser.test.cjs',
);
foreach ( $private as $path ) {
    foreach ( array( $theme, $candidate ) as $directory ) {
        if ( ! is_dir( dirname( $directory . '/' . $path ) ) ) mkdir( dirname( $directory . '/' . $path ), 0700, true );
        file_put_contents( $directory . '/' . $path, $directory === $theme ? 'Previous synthetic test.' : 'Next private synthetic test.' );
    }
}
foreach ( array( 'tools/run-site-studio-cron.php', 'tools/qa-installed-stack.php' ) as $path ) {
    file_put_contents( $theme . '/' . $path, 'Previous runtime tooling.' );
    file_put_contents( $candidate . '/' . $path, 'Next runtime tooling.' );
}
try {
    foreach ( array( '/absolute.php', '../outside.php', 'dir/../outside.php', './relative.php', 'dir//file.php', 'dir/./file.php', 'bad;name.php', "bad\nname.php", 'dir\\file.php' ) as $path ) if ( trb_portal_release_path_is_safe( $path ) ) throw new RuntimeException( 'Unsafe snapshot path accepted.' );
    if ( 14 !== trb_portal_release_snapshot( $theme, $candidate, $backup, $revision ) ) throw new RuntimeException( 'Snapshot count.' );
    $manifest = json_decode( file_get_contents( $backup . '/manifest.json' ), true, 64, JSON_THROW_ON_ERROR );
    foreach ( $private as $path ) if ( ! isset( $manifest['previous'][$path] ) || isset( $manifest['next'][$path] ) || file_get_contents( $backup . '/files/' . $path ) !== 'Previous synthetic test.' ) throw new RuntimeException( 'Private tests were omitted from rollback or included in public next.' );
    foreach ( array( 'tools/run-site-studio-cron.php', 'tools/qa-installed-stack.php' ) as $path ) if ( ! isset( $manifest['next'][$path] ) ) throw new RuntimeException( 'Required hosting tooling omitted from next.' );
    copy( $candidate . '/functions.php', $theme . '/functions.php' ); copy( $candidate . '/added.php', $theme . '/added.php' );
    copy( $candidate . '/shield (1)_colored.svg', $theme . '/shield (1)_colored.svg' );
    file_put_contents( $theme . '/.trb-deployed-sha', $revision . "\n" );
    file_put_contents( $theme . '/unrelated.txt', 'Creato da un altro processo.' );
    file_put_contents( $theme . '/tools/test-onboarding-pcloud.php', 'Modifica concorrente.' );
    $refused = false;
    try { trb_portal_release_prune( $theme, $candidate, $backup, $revision ); } catch ( RuntimeException $error ) { $refused = true; }
    if ( ! $refused || ! is_file( $theme . '/integrations/onboarding/crm/OnboardingPcloud.php' ) ) throw new RuntimeException( 'Prune modified files before verifying every backup.' );
    copy( $backup . '/files/tools/test-onboarding-pcloud.php', $theme . '/tools/test-onboarding-pcloud.php' );
    $last = end( $private );
    file_put_contents( $theme . '/' . $last, 'Concurrent test change.' );
    $refused = false;
    try { trb_portal_release_prune( $theme, $candidate, $backup, $revision ); } catch ( RuntimeException $error ) { $refused = true; }
    if ( ! $refused || ! is_file( $theme . '/integrations/onboarding/crm/OnboardingPcloud.php' ) ) throw new RuntimeException( 'Concurrent test change allowed a partial prune.' );
    file_put_contents( $theme . '/' . $last, 'Previous synthetic test.' );
    file_put_contents( $backup . '/files/' . $last, 'Changed private backup.' );
    $refused = false;
    try { trb_portal_release_prune( $theme, $candidate, $backup, $revision ); } catch ( RuntimeException $error ) { $refused = true; }
    if ( ! $refused || ! is_file( $theme . '/integrations/onboarding/crm/OnboardingPcloud.php' ) ) throw new RuntimeException( 'Damaged backup allowed a partial prune.' );
    file_put_contents( $backup . '/files/' . $last, 'Previous synthetic test.' );
    file_put_contents( $theme . '/tools/test-created-after-snapshot.php', 'Unrecognized concurrent fixture.' );
    if ( 9 !== trb_portal_release_prune( $theme, $candidate, $backup, $revision ) || file_exists( $theme . '/integrations/onboarding/crm/OnboardingPcloud.php' ) || file_exists( $theme . '/tools/test-onboarding-pcloud.php' ) ) throw new RuntimeException( 'Reviewed obsolete and test source cleanup failed.' );
    foreach ( $private as $path ) if ( file_exists( $theme . '/' . $path ) || file_get_contents( $candidate . '/' . $path ) !== 'Next private synthetic test.' ) throw new RuntimeException( 'Public fixture survived or private candidate was modified.' );
    foreach ( array( 'tools/run-site-studio-cron.php', 'tools/qa-installed-stack.php' ) as $path ) if ( ! is_file( $theme . '/' . $path ) ) throw new RuntimeException( 'Prune removed required hosting tooling.' );
    $refused = false;
    try { trb_portal_release_assert_no_public_tests( $theme ); } catch ( RuntimeException $error ) { $refused = true; }
    if ( ! $refused || ! is_file( $theme . '/tools/test-created-after-snapshot.php' ) ) throw new RuntimeException( 'Unknown public fixture was deleted or certified.' );
    unlink( $theme . '/tools/test-created-after-snapshot.php' );
    trb_portal_release_assert_no_public_tests( $theme );
    file_put_contents( $theme . '/functions.php', 'Modifica concorrente.' );
    $refused = false;
    try { trb_portal_release_restore( $theme, $backup, $revision ); } catch ( RuntimeException $error ) { $refused = true; }
    if ( ! $refused || ! is_file( $theme . '/added.php' ) ) throw new RuntimeException( 'Concurrent edit must stop rollback before writes.' );
    copy( $candidate . '/functions.php', $theme . '/functions.php' );
    trb_portal_release_restore( $theme, $backup, $revision );
    foreach ( $private as $path ) if ( file_get_contents( $theme . '/' . $path ) !== 'Previous synthetic test.' ) throw new RuntimeException( 'Pruned test missing from full rollback.' );
    if ( file_get_contents( $theme . '/integrations/onboarding/crm/OnboardingPcloud.php' ) !== '<?php /* archivio sostituito */' || file_get_contents( $theme . '/tools/test-onboarding-pcloud.php' ) !== '<?php /* prova archivio sostituito */' ) throw new RuntimeException( 'Deleted obsolete sources were not restored.' );
    if ( file_get_contents( $theme . '/functions.php' ) !== '<?php /* precedente */' || file_get_contents( $theme . '/shield (1)_colored.svg' ) !== '<svg>precedente</svg>' || is_file( $theme . '/added.php' ) || ! is_file( $theme . '/unrelated.txt' ) || trim( file_get_contents( $theme . '/.trb-deployed-sha' ) ) !== str_repeat( 'b', 40 ) ) throw new RuntimeException( 'Rollback byte preservation failed.' );
    echo "Private candidate tests, filtered public next, hash-guarded pruning, full rollback and concurrent fixture refusal verified.\n";
} finally {
    $entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $entries as $entry ) $entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
    rmdir( $base );
}
