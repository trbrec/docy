<?php
define( 'TRB_DEMO_KEY_LIBRARY_ONLY', true ); require __DIR__ . '/demo-key-alignment.php';
$root = str_replace( '\\', '/', sys_get_temp_dir() ) . '/trb-demo-key-test-' . bin2hex( random_bytes( 8 ) ); mkdir( $root, 0700 );
$settings = array( 'sheet_webhook_secret' => 'previous synthetic key', 'sheet_webhook_url' => 'https://script.google.com/macros/s/fixture/exec', 'other_service' => 'preserve this synthetic value' );
$config = array( 'private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA );
$windows = dirname( PHP_BINARY ) . '/extras/ssl/openssl.cnf'; if ( is_file( $windows ) ) $config['config'] = $windows;
try {
    $key = openssl_pkey_new( $config ); if ( ! $key ) throw new RuntimeException( 'Synthetic test key unavailable.' );
    $result = trb_demo_key_prepare( $root . '/pending', $settings, openssl_pkey_get_details( $key )['key'] );
    if ( ! openssl_private_decrypt( base64_decode( $result['sealed_new_key'], true ), $plain, $key, OPENSSL_PKCS1_OAEP_PADDING ) || ! preg_match( '/^[a-f0-9]{64}$/D', $plain ) || str_contains( json_encode( $result ), $settings['sheet_webhook_secret'] ) ) throw new RuntimeException( 'Encrypted pending key or previous-key privacy failed.' );
    $plan = json_decode( file_get_contents( $root . '/pending/rollback-and-pending.json' ), true );
    $status = trb_demo_key_pending_status( $settings, $plan, $plan['prepared_at'] + 1 );
    if ( ! $status['pending_key_ready'] || $status['new_key_sha256'] !== hash( 'sha256', $plain ) || $status['pending_key_expires_at'] !== $plan['prepared_at'] + 86400 || str_contains( json_encode( $status ), $plain ) || str_contains( json_encode( $status ), $settings['sheet_webhook_secret'] ) ) throw new RuntimeException( 'Read-only pending-key readiness or privacy failed.' );
    foreach ( array( array( $settings, $plan['prepared_at'] + 86401 ), array( array_replace( $settings, array( 'sheet_webhook_secret' => 'concurrent key' ) ), $plan['prepared_at'] ), array( array_replace( $settings, array( 'sheet_webhook_url' => 'https://script.google.com/macros/s/changed/exec' ) ), $plan['prepared_at'] ) ) as [ $current, $now ] ) {
        try { trb_demo_key_pending_status( $current, $plan, $now ); throw new LogicException( 'Unsafe pending entry accepted.' ); } catch ( RuntimeException $expected ) {}
    }
    $changed = unserialize( trb_demo_key_next_raw( serialize( $settings ), $plan, false ) );
    if ( $changed !== array_replace( $settings, array( 'sheet_webhook_secret' => $plain ) ) ) throw new RuntimeException( 'Another setting changed.' );
    $changed['other_service'] = 'concurrent unrelated change';
    $restored = unserialize( trb_demo_key_next_raw( serialize( $changed ), $plan, true ) );
    if ( $restored['other_service'] !== $changed['other_service'] || $restored['sheet_webhook_secret'] !== $settings['sheet_webhook_secret'] ) throw new RuntimeException( 'Rollback lost a concurrent unrelated setting.' );
    foreach ( array( array_replace( $settings, array( 'sheet_webhook_secret' => 'concurrent different key' ) ), array_replace( $settings, array( 'sheet_webhook_url' => 'https://script.google.com/macros/s/changed/exec' ) ) ) as $concurrent ) {
        try { trb_demo_key_next_raw( serialize( $concurrent ), $plan, false ); throw new LogicException( 'Concurrent configuration accepted.' ); } catch ( RuntimeException $expected ) {}
    }
    try { trb_demo_key_prepare( $root . '/pending', $settings, openssl_pkey_get_details( $key )['key'] ); throw new LogicException( 'Existing rollback overwritten.' ); } catch ( RuntimeException $expected ) {}
    if ( PHP_OS_FAMILY !== 'Windows' ) { symlink( $root . '/pending', $root . '/link' ); try { trb_demo_key_prepare( $root . '/link', $settings, openssl_pkey_get_details( $key )['key'] ); throw new LogicException( 'Symlink accepted.' ); } catch ( RuntimeException $expected ) {} unlink( $root . '/link' ); }
    echo "Pending key encryption, backup collisions, isolated setting replacement, concurrent edits and rollback preservation verified.\n";
} finally { if ( is_file( $root . '/pending/rollback-and-pending.json' ) ) unlink( $root . '/pending/rollback-and-pending.json' ); if ( is_dir( $root . '/pending' ) ) rmdir( $root . '/pending' ); rmdir( $root ); }
