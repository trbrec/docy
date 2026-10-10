<?php
/** Direct device-to-private-hosting staging; never executes or publishes uploaded code. */
function trb_private_upload_receive( $metadata_ciphertext, $signature, $upload, $private_key, $signer, $tag, $incoming ) {
    if ( is_link( $incoming ) || str_replace( '\\', '/', (string) realpath( $incoming ) ) !== $incoming ) throw new RuntimeException( 'Unsafe incoming storage.' );
    $ciphertext = base64_decode( $metadata_ciphertext, true ); $signature_bytes = base64_decode( $signature, true );
    if ( ! is_string( $ciphertext ) || ! is_string( $signature_bytes ) || ! openssl_private_decrypt( $ciphertext, $plaintext, $private_key, OPENSSL_PKCS1_OAEP_PADDING ) || openssl_verify( $plaintext, $signature_bytes, $signer, OPENSSL_ALGO_SHA256 ) !== 1 ) throw new RuntimeException( 'Upload authorization invalid.' );
    $metadata = json_decode( $plaintext, true, 8, JSON_THROW_ON_ERROR );
    if ( ! is_array( $metadata ) || count( $metadata ) !== 4 || ( $metadata['tag'] ?? '' ) !== $tag || ! preg_match( '/^[a-f0-9]{64}$/D', $metadata['sha256'] ?? '' ) || ! preg_match( '/^[a-f0-9]{40}$/D', $metadata['revision'] ?? '' ) || ! is_int( $metadata['bytes'] ?? null ) || $metadata['bytes'] < 1 || $metadata['bytes'] > 10485760 ) throw new RuntimeException( 'Upload manifest invalid.' );
    $temporary = $upload['tmp_name'] ?? '';
    if ( ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK || ! is_uploaded_file( $temporary ) || filesize( $temporary ) !== $metadata['bytes'] || ! hash_equals( $metadata['sha256'], hash_file( 'sha256', $temporary ) ) ) throw new RuntimeException( 'Upload bytes differ from signed manifest.' );
    $target = $incoming . '/candidate.zip'; $receipt = $incoming . '/upload.json';
    foreach ( array( $target, $receipt, $target . '.part', $incoming . '/upload.lock' ) as $path ) if ( is_link( $path ) ) throw new RuntimeException( 'Incoming path changed to a link.' );
    $lock = fopen( $incoming . '/upload.lock', 'c' );
    if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) throw new RuntimeException( 'Incoming transfer busy.' );
    try {
        if ( is_file( $target ) ) {
            $existing = is_file( $receipt ) ? json_decode( file_get_contents( $receipt ), true, 8, JSON_THROW_ON_ERROR ) : null;
            if ( ! is_array( $existing ) || filesize( $target ) !== $metadata['bytes'] || ! hash_equals( $metadata['sha256'], hash_file( 'sha256', $target ) ) || array_intersect_key( $existing, $metadata ) !== $metadata ) throw new RuntimeException( 'Existing incoming archive differs.' );
            return array( 'completed' => true, 'duplicate' => true, 'installed' => false );
        }
        if ( file_exists( $target . '.part' ) || ! move_uploaded_file( $temporary, $target . '.part' ) || ! chmod( $target . '.part', 0600 ) || ! hash_equals( $metadata['sha256'], hash_file( 'sha256', $target . '.part' ) ) || ! rename( $target . '.part', $target ) ) throw new RuntimeException( 'Private archive staging unconfirmed.' );
        $json = json_encode( $metadata + array( 'received_at' => gmdate( 'c' ), 'verified' => true, 'installed' => false, 'release_ready' => false ), JSON_THROW_ON_ERROR );
        trb_release_file_replace( $receipt, $json, 0600 );
        return array( 'completed' => true, 'duplicate' => false, 'installed' => false );
    } finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
}
function trb_private_upload_controller_source( $work, $incoming, $token, $tag, $expires ) {
    $source = <<<'PHP'
<?php
ini_set('display_errors','0');ini_set('error_log',__WORK__.'/private-error.log');header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
if(time()>__EXPIRES__||!hash_equals(__TOKEN__,(string)($_GET['token']??''))){http_response_code(404);exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 header('Content-Type: application/json');
 try{
  if((int)($_SERVER['CONTENT_LENGTH']??0)>11000000)throw new RuntimeException('Oversized transfer.');
  require __WORK__.'/library.php';
  $result=trb_private_upload_receive((string)($_POST['metadata']??''),(string)($_POST['signature']??''),$_FILES['archive']??[],file_get_contents(__WORK__.'/recipient-private.pem'),file_get_contents(__WORK__.'/signer-public.pem'),__TAG__,__INCOMING__);
  echo json_encode($result,JSON_THROW_ON_ERROR);
 }catch(Throwable $error){http_response_code(400);echo '{"completed":false,"installed":false,"error":"transfer_not_verified"}';}
 exit;
}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="it"><meta charset="utf-8"><title>Consegna privata CRM</title><h1>Consegna privata del candidato CRM</h1><p>L’archivio resta nell’hosting privato. Questa operazione non installa codice.</p>
<form method="post" enctype="multipart/form-data" autocomplete="off"><p><label>Manifesto cifrato<textarea name="metadata" required></textarea></label></p><p><label>Firma del manifesto<textarea name="signature" required></textarea></label></p><p><label>Archivio verificato<input name="archive" type="file" accept=".zip" required></label></p><button>Consegna in archivio privato</button></form></html>
PHP;
    return str_replace( array( '__WORK__', '__INCOMING__', '__TOKEN__', '__TAG__', '__EXPIRES__' ), array( var_export( $work, true ), var_export( $incoming, true ), var_export( $token, true ), var_export( $tag, true ), (string) $expires ), $source );
}
if ( defined( 'TRB_PRIVATE_UPLOAD_LIBRARY_ONLY' ) ) return;
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Private transfer operation unconfirmed.\n" ); exit( 1 ); } );
require __DIR__ . '/release-file-transaction.php';
$phase = $argv[1] ?? ''; $tag = $argv[2] ?? '';
if ( ! in_array( $phase, array( 'prepare', 'wait', 'cleanup' ), true ) || ! preg_match( '/^[a-f0-9]{16}$/D', $tag ) || isset( $argv[3] ) ) exit( 2 );
$site = '/home/customer/www/crm.trbrec.com'; $public = $site . '/public_html'; $private = $site . '/private';
$crm_private = $private;
foreach ( array( $public, $private, $crm_private ) as $path ) if ( is_link( $path ) || realpath( $path ) !== $path ) throw new RuntimeException( 'Unsafe hosting destination.' );
$work = $private . '/qa-private-upload-' . $tag; $incoming = $crm_private . '/incoming-audit-' . $tag;
$endpoint = $public . '/trb-audit-transfer-' . $tag . '.php'; $bootstrap = $public . '/trb-audit-transfer-' . $tag . '.json';
if ( 'prepare' === $phase ) {
    foreach ( array( $work, $incoming, $endpoint, $bootstrap ) as $path ) if ( file_exists( $path ) || is_link( $path ) ) throw new RuntimeException( 'Transfer path already exists.' );
    if ( ! mkdir( $work, 0700 ) || ! mkdir( $incoming, 0700 ) ) throw new RuntimeException( 'Private transfer storage unavailable.' );
    $key = openssl_pkey_new( array( 'private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
    if ( ! $key || ! openssl_pkey_export( $key, $key_bytes ) ) throw new RuntimeException( 'Ephemeral receiver key unavailable.' );
    $recipient = openssl_pkey_get_details( $key )['key'];
    $signer = file_get_contents( __DIR__ . '/qa-browser-recipient.pem' ); $token = bin2hex( random_bytes( 32 ) );
    trb_release_file_replace( $work . '/recipient-private.pem', $key_bytes, 0600 );
    trb_release_file_replace( $work . '/signer-public.pem', $signer, 0600 );
    trb_release_file_replace( $work . '/filesystem.php', file_get_contents( __DIR__ . '/release-file-transaction.php' ), 0600 );
    trb_release_file_replace( $work . '/library.php', '<?php define("TRB_PRIVATE_UPLOAD_LIBRARY_ONLY",true); require __DIR__."/filesystem.php"; ?>' . file_get_contents( __FILE__ ), 0600 );
    $code = trb_private_upload_controller_source( $work, $incoming, $token, $tag, time() + 900 );
    $url = 'https://crm.trbrec.com/' . basename( $endpoint ) . '?token=' . $token;
    if ( ! openssl_public_encrypt( $url, $sealed, $signer, OPENSSL_PKCS1_OAEP_PADDING ) ) throw new RuntimeException( 'Transfer bootstrap encryption failed.' );
    $bootstrap_bytes = json_encode( array( 'recipient' => $recipient, 'sealed_url' => base64_encode( $sealed ) ), JSON_THROW_ON_ERROR );
    $manifest = array( 'endpoint_sha256' => hash( 'sha256', $code ), 'bootstrap_sha256' => hash( 'sha256', $bootstrap_bytes ) );
    trb_release_file_replace( $work . '/cleanup.json', json_encode( $manifest, JSON_THROW_ON_ERROR ), 0600 );
    trb_release_file_replace( $endpoint, $code, 0644 ); trb_release_file_replace( $bootstrap, $bootstrap_bytes, 0644 );
    echo "{\"prepared\":true,\"uploaded\":false,\"installed\":false}\n"; exit;
}
if ( 'wait' === $phase ) {
    $deadline = time() + 900;
    while ( ! is_file( $incoming . '/upload.json' ) && time() < $deadline ) { clearstatcache( true, $incoming . '/upload.json' ); usleep( 250000 ); }
    if ( ! is_file( $incoming . '/upload.json' ) || is_link( $incoming . '/upload.json' ) || is_link( $incoming . '/candidate.zip' ) ) throw new RuntimeException( 'Private transfer receipt absent.' );
    $receipt = json_decode( file_get_contents( $incoming . '/upload.json' ), true, 8, JSON_THROW_ON_ERROR );
    if ( ( $receipt['verified'] ?? false ) !== true || ! hash_equals( $receipt['sha256'], hash_file( 'sha256', $incoming . '/candidate.zip' ) ) ) throw new RuntimeException( 'Private transfer readback failed.' );
    echo "{\"completed\":true,\"private_archive_verified\":true,\"installed\":false}\n"; exit;
}
if ( ! is_dir( $work ) || is_link( $work ) || realpath( $work ) !== $work ) throw new RuntimeException( 'Private transfer cleanup storage unavailable.' );
$manifest = json_decode( file_get_contents( $work . '/cleanup.json' ), true, 8, JSON_THROW_ON_ERROR );
foreach ( array( $endpoint => 'endpoint_sha256', $bootstrap => 'bootstrap_sha256' ) as $path => $key ) {
    if ( is_link( $path ) || is_file( $path ) && ( ! hash_equals( $manifest[$key], hash_file( 'sha256', $path ) ) || ! unlink( $path ) ) ) throw new RuntimeException( 'Public transfer endpoint cleanup unconfirmed.' );
}
foreach ( array( 'recipient-private.pem', 'signer-public.pem', 'library.php', 'filesystem.php', 'cleanup.json' ) as $name ) {
    $path = $work . '/' . $name;
    if ( is_link( $path ) || is_file( $path ) && ! unlink( $path ) ) throw new RuntimeException( 'Ephemeral transfer material cleanup unconfirmed.' );
}
if ( ! glob( $work . '/*' ) ) rmdir( $work );
echo "{\"public_endpoint_removed\":true,\"ephemeral_keys_removed\":true,\"private_candidate_preserved\":true}\n";
