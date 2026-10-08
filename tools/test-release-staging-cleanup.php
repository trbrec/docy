<?php
/** Regression tests for the exact global release-staging cleanup. */

define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$test_uploads   = sys_get_temp_dir() . '/trb-release-staging-' . bin2hex( random_bytes( 6 ) );
$test_options   = array();
$test_scheduled = array();
$test_transients = array();

function wp_upload_dir() {
	global $test_uploads;
	return array( 'basedir' => $test_uploads );
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_current_user_id() {
	return 99;
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_mkdir_p( $directory ) {
	return is_dir( $directory ) || mkdir( $directory, 0755, true );
}

function wp_delete_file( $path ) {
	return is_file( $path ) ? unlink( $path ) : false;
}

function update_option( $key, $value, $autoload = null ) {
	global $test_options;
	$test_options[ $key ] = $value;
	return true;
}

function get_transient( $key ) {
	global $test_transients;
	return $test_transients[ $key ] ?? false;
}

function set_transient( $key, $value, $expiration ) {
	global $test_transients;
	$test_transients[ $key ] = $value;
	return true;
}

function wp_next_scheduled( $hook ) {
	return false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	global $test_scheduled;
	$test_scheduled = compact( 'timestamp', 'recurrence', 'hook' );
	return true;
}

function add_action( $hook, $callback, $priority = 10 ) {
	return true;
}

function staging_assert( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
}

function staging_remove_tree( $path ) {
	if ( ! file_exists( $path ) ) return;
	if ( is_file( $path ) || is_link( $path ) ) {
		unlink( $path );
		return;
	}
	foreach ( scandir( $path ) ?: array() as $entry ) {
		if ( '.' === $entry || '..' === $entry ) continue;
		staging_remove_tree( $path . DIRECTORY_SEPARATOR . $entry );
	}
	rmdir( $path );
}

$portal = file_get_contents( dirname( __DIR__ ) . '/inc/trb-artist-portal.php' );
$start  = strpos( $portal, 'function trb_portal_release_staging_base' );
$end    = strpos( $portal, 'function trb_portal_release_max_file_bytes', $start );
if ( false === $start || false === $end ) throw new RuntimeException( 'Release staging cleanup not found.' );
eval( substr( $portal, $start, $end - $start ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged

try {
	$base            = $test_uploads . '/trb-release-staging';
	$expired_session = $base . '/10/11111111-1111-4111-8111-111111111111';
	$recent_session  = $base . '/20/22222222-2222-4222-8222-222222222222';
	$invalid_session = $base . '/30/not-a-session';
	$invalid_user    = $base . '/not-a-user/33333333-3333-4333-8333-333333333333';
	foreach ( array( $expired_session, $recent_session, $invalid_session, $invalid_user ) as $directory ) mkdir( $directory, 0755, true );

	file_put_contents( $expired_session . '/f0.part', str_repeat( 'a', 1024 ) );
	file_put_contents( $expired_session . '/f0.json', '{}' );
	file_put_contents( $recent_session . '/f0.part', str_repeat( 'b', 2048 ) );
	file_put_contents( $invalid_session . '/keep.part', 'keep' );
	file_put_contents( $invalid_user . '/keep.part', 'keep' );
	touch( $expired_session, time() - 2 * DAY_IN_SECONDS );
	touch( $recent_session, time() );
	touch( $invalid_session, time() - 2 * DAY_IN_SECONDS );
	touch( $invalid_user, time() - 2 * DAY_IN_SECONDS );
	clearstatcache();

	$summary = trb_portal_cleanup_expired_release_staging_all();
	staging_assert( ! is_dir( $expired_session ), 'Expired staging session was not removed.' );
	staging_assert( ! is_dir( $base . '/10' ), 'Empty numeric user root was not removed.' );
	staging_assert( is_dir( $recent_session ), 'Recent staging session was removed.' );
	staging_assert( is_dir( $invalid_session ), 'Invalid session path was removed.' );
	staging_assert( is_dir( $invalid_user ), 'Non-numeric user path was removed.' );
	staging_assert( 1 === $summary['sessions'], 'Cleanup did not report exactly one removed session.' );
	staging_assert( 2 === $summary['files'], 'Cleanup file count is not based on successful deletion.' );
	staging_assert( 1026 === $summary['bytes'], 'Cleanup byte count is incorrect.' );
	staging_assert( $summary === $test_options['trb_release_staging_cleanup_last'], 'Cleanup result was not persisted.' );

	trb_portal_schedule_release_staging_cleanup();
	staging_assert( 'hourly' === $test_scheduled['recurrence'], 'Cleanup is not scheduled hourly.' );
	staging_assert( 'trb_portal_cleanup_release_staging_event' === $test_scheduled['hook'], 'Cleanup hook is incorrect.' );

	echo "release staging cleanup regressions: ok\n";
} finally {
	staging_remove_tree( $test_uploads );
}
