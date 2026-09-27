<?php
/** Read-only check of a reported demo upload. Prints no names, emails or file paths. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$revision = $argv[1] ?? '';
if ( ! preg_match( '/^[a-f0-9]{40}$/D', $revision ) || trim( (string) @file_get_contents( dirname( __DIR__ ) . '/.trb-deployed-sha' ) ) !== $revision ) exit( 2 );
$_SERVER['HTTP_HOST'] = 'artist.trbrec.com';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS'] = 'on';
define( 'DISABLE_WP_CRON', true );
require dirname( __DIR__, 4 ) . '/wp-load.php';
if ( rtrim( home_url(), '/' ) !== 'https://artist.trbrec.com' ) throw new RuntimeException( 'Unexpected site' );
add_filter( 'pre_wp_mail', static function() { return false; }, PHP_INT_MAX );

$matches = array();
foreach ( get_users( array( 'search' => '*Arianna*', 'search_columns' => array( 'display_name', 'user_login', 'user_email' ), 'number' => 100 ) ) as $user ) {
	$label = $user->display_name . ' ' . $user->first_name . ' ' . $user->last_name;
	if ( stripos( $label, 'Ruggeri' ) === false ) continue;
	$requests = get_posts( array( 'post_type' => 'trb_request', 'post_status' => array( 'private', 'publish', 'pending', 'draft' ), 'author' => $user->ID, 'posts_per_page' => 10, 'meta_query' => array( array( 'key' => '_trb_demo_payload', 'compare' => 'EXISTS' ) ) ) );
	$recent = array();
	foreach ( $requests as $request ) {
		$payload = get_post_meta( $request->ID, '_trb_demo_payload', true );
		if ( ! is_array( $payload ) || strtotime( $payload['submitted_at'] ?? '' ) < strtotime( '2026-09-26 00:00:00 UTC' ) ) continue;
		$recent[] = array( 'submitted_at' => $payload['submitted_at'] ?? '', 'status' => sanitize_key( $payload['status'] ?? '' ) );
	}
	$matches[] = array( 'recent_demo_statuses' => $recent, 'last_submission_at' => (int) get_user_meta( $user->ID, '_trb_demo_last_submission', true ) ?: null, 'lock_age_seconds' => ( $locked = (int) get_user_meta( $user->ID, '_trb_demo_submission_lock', true ) ) ? max( 0, time() - $locked ) : null );
}
echo 'DEMO_UPLOAD_LIVE ' . wp_json_encode( array(
	'checked_at' => gmdate( 'c' ), 'candidate_count' => count( $matches ), 'candidates' => $matches,
	'php_limits' => array( 'upload_max_filesize' => ini_get( 'upload_max_filesize' ), 'post_max_size' => ini_get( 'post_max_size' ), 'max_execution_time' => ini_get( 'max_execution_time' ) ),
	'health' => function_exists( 'trb_demo_health_payload' ) ? array_intersect_key( trb_demo_health_payload(), array_flip( array( 'status', 'ready', 'checked_at' ) ) ) : null,
), JSON_UNESCAPED_SLASHES ) . "\n";
