<?php
/** Exercise the production validators without bootstrapping WordPress or writing user data. */
$source = file_get_contents( __DIR__ . '/../inc/trb-artist-portal.php' );
$wanted = array( 'trb_portal_download_manager_login_compat', 'trb_portal_register_download_manager_login_compat', 'trb_portal_validate_biography_upload', 'trb_portal_valid_biography_file', 'trb_portal_private_profile_file_path', 'trb_portal_validate_birth_date', 'trb_portal_canonical_account_redirect', 'trb_release_bridge_payload', 'trb_release_bridge_spreadsheet_row', 'trb_portal_country_is_italy', 'trb_portal_validate_international_identifier', 'trb_portal_validate_profile_geography', 'trb_portal_validate_mobile', 'trb_portal_validate_tax_code', 'trb_portal_validate_identity_document_number', 'trb_portal_validate_identity_document_expiry' );
$source .= "\n" . preg_replace( '/^<\?php/', '', file_get_contents( __DIR__ . '/../inc/trb-release-spreadsheet-bridge.php' ) );
$tokens = token_get_all( $source );
foreach ( $tokens as $i => $token ) {
	if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) continue;
	$j = $i + 1;
	while ( isset( $tokens[$j] ) && is_array( $tokens[$j] ) && T_WHITESPACE === $tokens[$j][0] ) $j++;
	if ( ! isset( $tokens[$j] ) || ! is_array( $tokens[$j] ) || ! in_array( $tokens[$j][1], $wanted, true ) ) continue;
	$body = ''; $depth = 0; $opened = false;
	for ( $k = $i; $k < count( $tokens ); $k++ ) {
		$part = $tokens[$k]; $body .= is_array( $part ) ? $part[1] : $part;
		if ( '{' === $part ) { $depth++; $opened = true; }
		if ( '}' === $part && --$depth === 0 && $opened ) break;
	}
	eval( $body );
}
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function remove_accents( $value ) { return $value; }
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
function wp_date( $format, $timestamp = null ) { return ( new DateTimeImmutable( $timestamp ? '@' . $timestamp : 'now', wp_timezone() ) )->format( $format ); }
function is_wp_error( $value ) { return false === $value || $value instanceof WP_Error; }
function trb_portal_lookup_postcode( $code ) { return '25038' === $code ? array( array( 'city' => 'Rovato', 'province' => 'BS' ) ) : false; }
function trb_portal_find_municipality_exact( $city, $province ) { return 'rovato' === strtolower( $city ) && in_array( $province, array( '', 'BS' ), true ) ? array( 'city' => 'Rovato', 'province' => 'BS' ) : false; }
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
foreach ( $wanted as $name ) check( function_exists( $name ), 'Production validator missing: ' . $name );
foreach ( array( array( 'Tunisia', 'Tunisi' ), array( 'تونس', 'تونس' ), array( 'Japan', '東京都' ), array( 'United Kingdom', 'London' ), array( 'Hong Kong', '香港' ) ) as $location ) {
	foreach ( array( false, true ) as $birth ) {
		$result = trb_portal_validate_profile_geography( $location[0], $location[1], '', 'SW1A 1AA', $birth );
		check( $result && $result['country'] === $location[0] && $result['city'] === $location[1] && '' === $result['province'], 'Foreign location must preserve Unicode and optional region' );
	}
}
check( false === trb_portal_validate_profile_geography( '', 'Tunisi' ), 'Country required' );
check( false === trb_portal_validate_profile_geography( 'Tunisia', ' ' ), 'City required' );
check( false === trb_portal_validate_profile_geography( 'Italia', 'Tunisi', '', '', true ), 'Italian birthplace must match Italian archive' );
check( false === trb_portal_validate_profile_geography( 'Italia', 'London', '', '25038' ), 'Italian city must match postcode' );
check( 'Rovato' === trb_portal_validate_profile_geography( 'IT', 'rovato', '', '25038' )['city'], 'Italian normalization retained' );
check( 'BS' === trb_portal_validate_profile_geography( 'Italy', 'Rovato', 'BS', '', true )['province'], 'Italian birthplace retained' );
foreach ( array( '+216 20 123 456' => '+21620123456', '00216 20 123 456' => '+21620123456', '333 0000000' => '+393330000000', '+44 (20) 1234-5678' => '+442012345678' ) as $input => $expected ) check( $expected === trb_portal_validate_mobile( $input ), 'Phone normalization: ' . $input );
foreach ( array( '+012345678', '+1234567890123456', '1234567', '<script>' ) as $input ) check( false === trb_portal_validate_mobile( $input ), 'Malformed international phone rejected' );
check( 'RSSMRA90A01H501W' === trb_portal_validate_tax_code( 'RSSMRA90A01H501W' ), 'Italian tax checksum retained' );
check( false === trb_portal_validate_tax_code( 'RSSMRA90A01H501A' ), 'Invalid Italian checksum rejected' );
check( '01 23-456.789' === trb_portal_validate_international_identifier( '01 23-456.789' ), 'Foreign tax punctuation retained' );
check( 'AB-123456789' === trb_portal_validate_identity_document_number( 'AB-123456789', 'passport' ), 'Passport number retained' );
check( '文書123456' === trb_portal_validate_identity_document_number( '文書123456', 'foreign_identity' ), 'Foreign identity Unicode retained' );
check( false === trb_portal_validate_identity_document_number( '123456789' ), 'CIE format remains enforced' );
check( false === trb_portal_validate_identity_document_number( 'AB123', 'unknown' ), 'Unknown document type rejected' );
foreach ( array( array( '123456789' ), null, true, new stdClass() ) as $input ) {
	foreach ( array( 'trb_portal_validate_mobile', 'trb_portal_validate_tax_code', 'trb_portal_validate_international_identifier', 'trb_portal_validate_identity_document_number', 'trb_portal_validate_identity_document_expiry' ) as $validator ) check( false === $validator( $input ), 'Non-string profile input rejected by ' . $validator );
	check( false === trb_portal_validate_profile_geography( $input, 'Tunisi' ), 'Non-string country rejected' );
	check( false === trb_portal_validate_profile_geography( 'Tunisia', $input ), 'Non-string city rejected' );
}
$future = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '+15 years' )->format( 'Y-m-d' );
check( false === trb_portal_validate_identity_document_expiry( $future ), 'CIE maximum retained' );
check( $future === trb_portal_validate_identity_document_expiry( $future, 'foreign_identity' ), 'Foreign expiry may exceed 10 years' );
check( false === trb_portal_validate_identity_document_expiry( '2000-01-01', 'passport' ), 'Expired passport rejected' );
check( false === trb_portal_validate_identity_document_expiry( '2030-02-30', 'passport' ), 'Impossible date rejected' );
echo "International profile server validators verified.\n";

define( 'MB_IN_BYTES', 1024 * 1024 );
foreach ( array( 'txt', 'docx', 'odt', 'rtf', 'TXT' ) as $extension ) check( '' === trb_portal_validate_biography_upload( array( 'name' => 'test.' . $extension, 'size' => 100, 'error' => UPLOAD_ERR_OK ), false ), 'Supported biography format retained' );
check( 'bio_required' === trb_portal_validate_biography_upload( array(), false ), 'Missing biography rejected before metadata writes' );
check( '' === trb_portal_validate_biography_upload( array(), true ), 'Existing biography permits a text-only profile save' );
foreach ( array( array( 'name' => 'test.pdf', 'size' => 100, 'error' => 0 ), array( 'name' => 'test.txt', 'size' => 0, 'error' => 0 ), array( 'name' => 'test.txt', 'size' => 5 * MB_IN_BYTES + 1, 'error' => 0 ), array( 'name' => 'test.txt', 'size' => 100, 'error' => UPLOAD_ERR_PARTIAL ), array( 'name' => array( 'test.txt' ), 'size' => 100, 'error' => 0 ) ) as $upload ) check( 'bio_invalid' === trb_portal_validate_biography_upload( $upload, true ), 'Invalid replacement rejected even when an older biography exists' );
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['biography_fixture_root'] ); }
function trb_portal_private_profile_files( $user_id = 0 ) { return array_merge( $GLOBALS['biography_extra'] ?? array(), array( $GLOBALS['biography_fixture'] ) ); }
$biography_sandbox = sys_get_temp_dir() . '/trb-biography-audit-' . bin2hex( random_bytes( 8 ) );
mkdir( $biography_sandbox . '/trb-artist-private', 0700, true );
$GLOBALS['biography_fixture_root'] = $biography_sandbox;
try {
	file_put_contents( $biography_sandbox . '/trb-artist-private/test.txt', 'Synthetic biography' );
	$GLOBALS['biography_fixture'] = array( 'id' => 'synthetic', 'group' => 'biography', 'name' => 'test.txt', 'path' => 'trb-artist-private/test.txt' );
	check( ! empty( trb_portal_valid_biography_file( 198 ) ), 'Existing private biography accepted' );
	$GLOBALS['biography_extra'] = array( array( 'group' => 'biography', 'name' => 'missing.txt', 'path' => 'trb-artist-private/missing.txt' ) );
	check( 'synthetic' === trb_portal_valid_biography_file( 198 )['id'], 'An obsolete missing biography hides a valid replacement' );
	$GLOBALS['biography_extra'] = array();
	$GLOBALS['biography_fixture']['path'] = 'trb-artist-private/missing.txt';
	check( array() === trb_portal_valid_biography_file( 198 ), 'Missing biography bytes cannot satisfy completion' );
	file_put_contents( $biography_sandbox . '/outside.txt', 'Synthetic outside fixture' );
	$GLOBALS['biography_fixture']['path'] = 'trb-artist-private/../outside.txt';
	check( array() === trb_portal_valid_biography_file( 198 ), 'Biography outside the private directory rejected' );
	file_put_contents( $biography_sandbox . '/trb-artist-private/empty.txt', '' );
	$GLOBALS['biography_fixture']['path'] = 'trb-artist-private/empty.txt';
	check( array() === trb_portal_valid_biography_file( 198 ), 'Empty biography cannot satisfy completion' );
} finally {
	foreach ( glob( $biography_sandbox . '/trb-artist-private/*' ) as $path ) unlink( $path );
	if ( is_file( $biography_sandbox . '/outside.txt' ) ) unlink( $biography_sandbox . '/outside.txt' );
	rmdir( $biography_sandbox . '/trb-artist-private' ); rmdir( $biography_sandbox );
}

// Calendar validity must be checked by the server, including legacy stored dates.
foreach ( array( '2030-02-30', '2024-02-30', '0000-01-01', '2030-01-01', '', array( '1990-01-01' ) ) as $input ) check( false === trb_portal_validate_birth_date( $input ), 'Impossible, future or non-scalar birth date rejected' );
foreach ( array( '1990-01-01', '2000-02-29', '2024-02-29', wp_date( 'Y-m-d' ) ) as $input ) check( $input === trb_portal_validate_birth_date( $input ), 'Valid calendar date retained' );

// Preserve password recovery parameters when a legacy redirect is canonicalized.
function wp_parse_url( $url ) { return parse_url( $url ); }
function untrailingslashit( $path ) { return rtrim( $path, '/\\' ); }
function home_url( $path = '' ) { return 'https://artist.trbrec.com' . $path; }
$redirects = array(
	'https://faq.trbrec.com/login' => 'https://artist.trbrec.com/accedi/',
	'https://faq.trbrec.com/logout?loggedout=1' => 'https://artist.trbrec.com/accedi/?loggedout=1',
	'https://faq.trbrec.com/register' => 'https://artist.trbrec.com/registrati/',
	'https://artisti.trbrec.com/area-artisti/#profilo' => 'https://artist.trbrec.com/area-artisti/#profilo',
	'https://faq.trbrec.com/wp-login.php?action=rp&key=test-only&login=fake' => 'https://artist.trbrec.com/recupera-password/?action=rp&key=test-only&login=fake',
	'https://faq.trbrec.com/wp-login.php?action=register' => 'https://artist.trbrec.com/registrati/?action=register',
	'https://example.invalid/path' => 'https://example.invalid/path',
);
foreach ( $redirects as $from => $to ) check( $to === trb_portal_canonical_account_redirect( $from ), 'Canonical redirect and reset parameters retained' );

// Real bridge functions, isolated WordPress fixtures: no network, ISRC allocation or contracts.
class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function has_filter( $hook, $callback ) { return $GLOBALS['auth_original_attached'] ?? false; }
function remove_filter( $hook, $callback, $priority ) { $GLOBALS['auth_original_attached'] = false; return true; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['auth_compat_hooks'][] = array( $hook, $callback, $priority, $args ); }
$GLOBALS['auth_compat_hooks'] = array();
trb_portal_register_download_manager_login_compat();
check( array() === $GLOBALS['auth_compat_hooks'], 'No dependency or new login hooks when Download Manager is absent' );
eval( 'namespace WPDM\\User; class Login { private static $instance; public $calls=0; public static function getInstance(){return self::$instance ?? (self::$instance=new self);} public function verifyLoginEmail($user,$login,$password){++$this->calls;if($user instanceof \\WP_Error)throw new \\RuntimeException("Plugin received a previous authentication error");return !empty($user->blocked)?new \\WP_Error("blocked_email"):$user;} }' );
$original_error = new WP_Error( 'incorrect_password' );
check( $original_error === trb_portal_download_manager_login_compat( $original_error, 'synthetic', 'not-a-password' ), 'Authentication error object is preserved exactly' );
check( 0 === \WPDM\User\Login::getInstance()->calls, 'Failed authentication does not reach the incompatible plugin check' );
$valid_user = (object) array( 'ID' => 198 );
check( $valid_user === trb_portal_download_manager_login_compat( $valid_user, 'synthetic', 'not-a-password' ), 'Valid login still reaches the original email verification' );
$blocked_user = (object) array( 'ID' => 198, 'blocked' => true );
$blocked = trb_portal_download_manager_login_compat( $blocked_user, 'synthetic', 'not-a-password' );
check( $blocked instanceof WP_Error && 'blocked_email' === $blocked->get_error_code(), 'Plugin email rejection remains enforced' );
$GLOBALS['auth_original_attached'] = 999998;
trb_portal_register_download_manager_login_compat();
trb_portal_register_download_manager_login_compat();
check( 1 === count( $GLOBALS['auth_compat_hooks'] ) && array( 'authenticate', 'trb_portal_download_manager_login_compat', 999998, 3 ) === $GLOBALS['auth_compat_hooks'][0], 'Compatibility hook retains priority and arguments and registers once' );
function get_post( $id ) { return (object) array( 'post_type' => 'trb_release', 'post_author' => 198, 'post_title' => 'SYNTHETIC AUDIT RELEASE' ); }
function get_userdata( $id ) { return (object) array( 'first_name' => 'Artista', 'last_name' => 'Fittizio', 'user_email' => 'test@example.invalid' ); }
function trb_portal_user_profile( $user ) { return 'trb'; }
function get_user_meta( $id, $key, $single = true ) { return $GLOBALS['bridge_fixture'][ $key ] ?? ''; }
function trb_release_bridge_profile_value( $id, $key, $default = '' ) { return get_user_meta( $id, '_trb_artist_' . $key ) ?: $default; }
function trb_release_bridge_normalize_contract_term( $term ) { return $term; }
function trb_release_bridge_validate_preliminary_contract( $user, $contract ) { return true; }
function get_post_meta( $id, $key, $single = true ) {
	if ( '_trb_release_state' === $key ) return 'previously_released';
	if ( '_trb_release_tracks' === $key ) return array( array( 'title' => 'Synthetic track', 'isrc' => 'IT0QA2600001', 'credits' => array() ) );
	return '';
}
function get_transient( $key ) { return false; }
function delete_transient( $key ) { return true; }
function update_post_meta( $id, $key, $value ) { return true; }
function get_post_time( $format, $gmt, $post ) { return '2026-10-10T09:00:00+00:00'; }
function trb_release_bridge_callback_url() { return 'https://example.invalid/no-transport'; }
function trb_release_value_label( $value ) { return $value; }
$base_artist = array(
	'preliminary_contract' => 'TRB-QA-AUDIT', 'contract_term' => '01/01/2026-INFINITO',
	'phone' => '+21620123456', 'artist_name' => 'AUDIT TEST', 'tax_code' => 'RSSMRA90A01H501W', 'tax_country' => 'Italia',
	'birth_date' => '1990-01-01', 'birth_country' => 'Tunisia', 'birth_place' => 'Tunisi', 'birth_province' => '',
	'document_type' => 'passport', 'document_number' => 'TEST-123456789', 'document_expiry' => '2031-01-01', 'document_no_expiry' => '',
	'street' => 'Via Test', 'street_number' => '1', 'postal_code' => '25038', 'city' => 'Rovato', 'province' => 'BS', 'country' => 'Italia',
);
$cases = array(
	array(),
	array( 'country' => 'United Kingdom', 'city' => 'London', 'street_number' => '', 'postal_code' => 'SW1A 1AA', 'province' => '', 'tax_country' => 'United Kingdom', 'tax_code' => '01 23-456.789' ),
	array( 'birth_country' => 'تونس', 'birth_place' => 'تونس', 'country' => '日本', 'city' => '東京都', 'street_number' => '', 'postal_code' => '', 'province' => '', 'tax_country' => '日本', 'tax_code' => '識別123456', 'document_type' => 'foreign_identity', 'document_number' => '文書123456', 'document_no_expiry' => '1', 'document_expiry' => '' ),
	array( 'birth_country' => 'Italia', 'birth_place' => 'Rovato', 'birth_province' => 'BS', 'document_type' => 'cie', 'document_number' => 'AB12345CD' ),
);
foreach ( $cases as $overrides ) {
	$artist = array_merge( $base_artist, $overrides );
	$GLOBALS['bridge_fixture'] = array();
	foreach ( $artist as $key => $value ) $GLOBALS['bridge_fixture'][ '_trb_artist_' . $key ] = $value;
	$payload = trb_release_bridge_payload( 123 );
	check( ! ( $payload instanceof WP_Error ), 'Valid international profile reaches contract payload' );
	check( $artist['birth_country'] === $payload['artist']['birth_country'], 'Birth country preserved in rich payload' );
	check( $artist['tax_country'] === $payload['artist']['tax_country'], 'Tax jurisdiction preserved in rich payload' );
	$row = trb_release_bridge_spreadsheet_row( $payload );
	check( $artist['country'] === $row[17] && $artist['tax_code'] === $row[9] && 'IT0QA2600001' === $row[37], 'Existing spreadsheet column positions retained' );
	check( trb_portal_country_is_italy( $artist['birth_country'] ) ? $artist['birth_place'] === $row[8] : str_contains( $row[8], $artist['birth_country'] ), 'Foreign birth country retained in contract spreadsheet' );
	check( in_array( $row[10], array( 'Passaporto', 'Documento d’identità estero', 'Carta d’identità elettronica italiana' ), true ), 'Document type is a readable contract label' );
}
$GLOBALS['bridge_fixture']['_trb_artist_birth_province'] = '';
check( trb_release_bridge_payload( 123 ) instanceof WP_Error, 'Italian birth province remains mandatory' );
$GLOBALS['bridge_fixture']['_trb_artist_birth_province'] = 'BS';
$GLOBALS['bridge_fixture']['_trb_artist_postal_code'] = '';
check( trb_release_bridge_payload( 123 ) instanceof WP_Error, 'Italian postcode remains mandatory' );

// Simulate an already initialized license SDK; portal modules still load once.
define( 'ABSPATH', __DIR__ . '/' );
function docy_fs() { return true; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function get_template_directory() { return $GLOBALS['bootstrap_fixture_dir']; }
$functions_source = file_get_contents( __DIR__ . '/../functions.php' );
$prefix = substr( $functions_source, 0, strpos( $functions_source, '// Handle null post object errors' ) );
preg_match_all( '~get_template_directory\(\)\s*\.\s*[\'\"](/inc/trb[^\'\"]+\.php)[\'\"]~', $prefix, $include_paths );
$sandbox = sys_get_temp_dir() . '/trb-bootstrap-audit-' . bin2hex( random_bytes( 8 ) );
mkdir( $sandbox . '/inc', 0700, true );
$GLOBALS['bootstrap_fixture_dir'] = $sandbox;
$GLOBALS['bootstrap_modules'] = array();
try {
	foreach ( array_unique( $include_paths[1] ) as $path ) file_put_contents( $sandbox . $path, '<?php $GLOBALS["bootstrap_modules"][]=' . var_export( $path, true ) . ';' );
	eval( substr( $prefix, 5 ) );
	check( count( array_unique( $include_paths[1] ) ) === count( $GLOBALS['bootstrap_modules'] ), 'Portal modules initialize independently of license SDK state' );
	check( count( $GLOBALS['bootstrap_modules'] ) === count( array_unique( $GLOBALS['bootstrap_modules'] ) ), 'Portal modules initialize once' );
} finally {
	foreach ( array_unique( $include_paths[1] ) as $path ) if ( is_file( $sandbox . $path ) ) unlink( $sandbox . $path );
	rmdir( $sandbox . '/inc' );
	rmdir( $sandbox );
}
echo "Portal date, canonical redirect, international contract and independent bootstrap regressions verified.\n";
