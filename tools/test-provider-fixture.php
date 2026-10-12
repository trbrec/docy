<?php
/** Actual exclusive writes and cleanup, independent of WordPress/providers. */
declare(strict_types=1);
require __DIR__ . '/qa-provider-cleanup.php';
$root = sys_get_temp_dir() . '/trb-provider-fixture-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $root, 0700 ) ) throw new RuntimeException( 'Fixture directory unavailable.' );
$path = $root . '/synthetic.txt';
function fixture_check( bool $ok, string $message ): void { if ( ! $ok ) throw new RuntimeException( $message ); }
try {
    file_put_contents( $path, 'existing material' );
    try { trb_qa_provider_local_fixture( $path, 'synthetic bytes' ); throw new LogicException( 'Collision accepted.' ); }
    catch ( RuntimeException $expected ) {}
    fixture_check( file_get_contents( $path ) === 'existing material', 'Collision must preserve existing bytes.' );
    unlink( $path );
    $fixture = trb_qa_provider_local_fixture( $path, 'synthetic bytes' );
    fixture_check( file_get_contents( $path ) === 'synthetic bytes', 'Real fixture readback.' );
    file_put_contents( $path, 'concurrent material' );
    fixture_check( ! trb_qa_provider_remove_local_fixture( $fixture ) && file_get_contents( $path ) === 'concurrent material', 'Changed material must be retained.' );
    file_put_contents( $path, 'synthetic bytes' );
    fixture_check( trb_qa_provider_remove_local_fixture( $fixture ) && ! file_exists( $path ), 'Only unchanged owned fixture removed.' );
    fixture_check( trb_qa_provider_remove_local_fixture( $fixture ), 'Repeated cleanup is harmless.' );
    if ( PHP_OS_FAMILY !== 'Windows' ) {
        $target = $root . '/target.txt'; file_put_contents( $target, 'protected material' ); symlink( $target, $path );
        try { trb_qa_provider_local_fixture( $path, 'synthetic bytes' ); throw new LogicException( 'Symlink accepted.' ); }
        catch ( RuntimeException $expected ) {}
        fixture_check( ! trb_qa_provider_remove_local_fixture( $fixture ) && file_get_contents( $target ) === 'protected material', 'Symlink target preserved.' );
        unlink( $path ); unlink( $target );
    }
    echo "Synthetic provider fixture collisions, concurrent changes and cleanup verified.\n";
} finally { foreach ( [ $path, $root . '/target.txt' ] as $owned ) if ( is_file( $owned ) || is_link( $owned ) ) unlink( $owned ); rmdir( $root ); }
