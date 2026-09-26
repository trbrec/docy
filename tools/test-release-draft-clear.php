<?php
/** A completed release must not erase the next release's saved draft. */
$source = file_get_contents( dirname( __DIR__ ) . '/inc/trb-artist-portal.php' );
$start = strpos( $source, 'function trb_portal_clear_release_draft_if_matching(' );
$end = strpos( $source, 'function trb_portal_save_release_draft()', $start );
if ( false === $start || false === $end ) throw new RuntimeException( 'Draft clear helper missing.' );
eval( substr( $source, $start, $end - $start ) );

$drafts = array( 7 => array( 'submissionToken' => '22222222-2222-4222-8222-222222222222', 'pairs' => array( array( 'trb_release_title', 'Seconda release' ) ) ) );
function get_user_meta( $user_id, $key, $single ) { global $drafts; return $drafts[ $user_id ] ?? false; }
function delete_user_meta( $user_id, $key, $value ) { global $drafts; if ( ! isset( $drafts[ $user_id ] ) || $drafts[ $user_id ] !== $value ) return false; unset( $drafts[ $user_id ] ); return true; }
function check_draft( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
$first = '11111111-1111-4111-8111-111111111111';
$second = '22222222-2222-4222-8222-222222222222';
check_draft( ! trb_portal_clear_release_draft_if_matching( 7, $first ), 'Older completion deleted newer draft.' );
check_draft( $drafts[7]['submissionToken'] === $second, 'New draft was changed.' );
check_draft( ! trb_portal_clear_release_draft_if_matching( 7, '' ), 'Missing token cleared draft.' );
check_draft( trb_portal_clear_release_draft_if_matching( 7, $second ), 'Matching draft was not cleared.' );
check_draft( ! isset( $drafts[7] ), 'Matching draft remained.' );
echo "PASS release draft clear is scoped to its receipt\n";
