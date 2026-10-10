<?php
/** Native comment ownership, storage failure, retry and deleted-readback cases. */
defined( 'ABSPATH' ) || exit;
$qaCommentOldUser = get_current_user_id();
wp_set_current_user( $qaUser );
$qaCommentNonce = wp_create_nonce( 'docy_edit_comment' );
wp_set_current_user( $qaCommentOldUser );
$qaCommentId = wp_insert_comment( array( 'comment_post_ID' => $qaPage, 'user_id' => $qaUser, 'comment_content' => 'Commento precedente', 'comment_approved' => 1 ) );
qa_check( $qaCommentId > 0, 'Native synthetic comment creation failed.' );
$qaCommentSend = static function( $overrides = array(), $anonymous = false ) use ( $qaCommentId, $qaCommentNonce ) {
    return qa_http( array_replace( array( 'action' => 'docy_edit_comment', 'comment_id' => (string) $qaCommentId, 'nonce' => $qaCommentNonce, 'comment_content' => "L'artista Tunisia — prova aggiornata" ), $overrides ), $anonymous );
};
$qaCommentRead = static function() use ( $wpdb, $qaCommentId ) { return $wpdb->get_var( $wpdb->prepare( "SELECT comment_content FROM {$wpdb->comments} WHERE comment_ID=%d", $qaCommentId ) ); };
foreach ( array( array( array( 'nonce' => 'invalid' ), 403 ), array( array( 'nonce' => '', 'nonce[0]' => 'nested' ), 403 ), array( array( 'comment_id' => '-' . $qaCommentId ), 404 ), array( array( 'comment_id' => '1.5' ), 404 ), array( array( 'comment_id' => '', 'comment_id[0]' => (string) $qaCommentId ), 404 ), array( array( 'comment_id' => '9223372036854775808' ), 404 ), array( array( 'comment_content' => '', 'comment_content[0]' => 'nested' ), 400 ) ) as [ $qaCommentOverrides, $qaCommentStatus ] ) {
    $qaCommentResponse = $qaCommentSend( $qaCommentOverrides );
    qa_check( $qaCommentStatus === $qaCommentResponse['status'] && false === $qaCommentResponse['data']['success'], 'Malformed comment request was accepted.' );
    qa_check( 'Commento precedente' === $qaCommentRead(), 'Rejected comment request changed stored text.' );
}
$qaCommentAnonymous = $qaCommentSend( array(), true );
qa_check( false === $qaCommentAnonymous['data']['success'] && 'Commento precedente' === $qaCommentRead(), 'Anonymous comment edit was accepted.' );
$qaOtherComment = wp_insert_comment( array( 'comment_post_ID' => $qaPage, 'user_id' => get_user_by( 'login', 'qa_admin' )->ID, 'comment_content' => 'Commento altrui', 'comment_approved' => 1 ) );
$qaCommentOtherResponse = $qaCommentSend( array( 'comment_id' => (string) $qaOtherComment ) );
qa_check( 403 === $qaCommentOtherResponse['status'] && false === $qaCommentOtherResponse['data']['success'] && 'Commento altrui' === get_comment( $qaOtherComment )->comment_content, 'An artist edited another user comment.' );
$wpdb->query( "CREATE TRIGGER qa_reject_comment_update BEFORE UPDATE ON {$wpdb->comments} FOR EACH ROW BEGIN IF NEW.comment_ID={$qaCommentId} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA comment update failure'; END IF; END" );
try {
    $qaCommentFailure = $qaCommentSend();
    qa_check( 500 === $qaCommentFailure['status'] && false === $qaCommentFailure['data']['success'], 'Native comment UPDATE failure produced success.' );
    qa_check( 'Commento precedente' === $qaCommentRead(), 'Failed comment UPDATE lost prior text.' );
} finally { $wpdb->query( 'DROP TRIGGER qa_reject_comment_update' ); }
$qaCommentRecovered = $qaCommentSend();
qa_check( true === $qaCommentRecovered['data']['success'] && "L'artista Tunisia — prova aggiornata" === $qaCommentRead() && $qaCommentRead() === $qaCommentRecovered['data']['data']['source'], 'Comment retry did not persist and read back the actual UTF-8 text.' );
qa_check( true === $qaCommentSend()['data']['success'], 'An already verified identical comment could not be saved.' );
$qaCommentDeleted = $qaCommentSend( array( 'comment_content' => 'Test cancellazione concorrente', 'qa_delete_after_update' => '1' ) );
qa_check( 409 === $qaCommentDeleted['status'] && false === $qaCommentDeleted['data']['success'] && null === $qaCommentRead(), 'A deleted comment was presented as a successful edit.' );
wp_delete_comment( $qaOtherComment, true );
