<?php
/** Real HTTP and MySQL failure, contention and recovery cases for article votes. */
defined( 'ABSPATH' ) || exit;
$qaRatingPost = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Synthetic rating QA' ) );
$qaRatingDraft = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Synthetic private QA' ) );
$qaRatingMeta = '_docy_article_rating';
$qaRatingRead = static function() use ( $wpdb, $qaRatingPost, $qaRatingMeta ) {
    return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s", $qaRatingPost, $qaRatingMeta ) );
};
$qaRatingSend = static function( $overrides = array(), $cookie = '' ) use ( $qaRatingPost ) {
    // Both roles are exercised; this fixture nonce belongs to the actual native artist session.
    return qa_http( array_replace( array( 'action' => 'docy_submit_article_rating', 'post_id' => (string) $qaRatingPost, 'rating' => '5', 'nonce' => wp_create_nonce( 'docy_article_rating_nonce' ) ), $overrides ), false, false, $cookie );
};
// Parent and HTTP process must derive the nonce for the same ordinary artist.
$qaRatingOriginalUser = get_current_user_id();
wp_set_current_user( $qaUser );
try {
    foreach ( array( array( 'nonce[0]' => 'invalid', 'nonce' => '' ), array( 'rating' => '-2' ), array( 'rating' => '1.5' ), array( 'rating' => '6' ), array( 'rating' => '', 'rating[0]' => '5' ), array( 'post_id' => (string) $qaRatingDraft ), array( 'post_id' => (string) $qaPage ), array( 'post_id' => '-' . $qaRatingPost ), array( 'post_id' => '', 'post_id[0]' => (string) $qaRatingPost ) ) as $qaRatingBad ) {
        qa_check( false === $qaRatingSend( $qaRatingBad )['data']['success'], 'Malformed or unavailable article vote was accepted.' );
        qa_check( null === $qaRatingRead(), 'Rejected vote created metadata.' );
    }
    $wpdb->query( "CREATE TRIGGER qa_reject_rating_insert BEFORE INSERT ON {$wpdb->postmeta} FOR EACH ROW BEGIN IF NEW.meta_key='_docy_article_rating' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA vote insert failure'; END IF; END" );
    try {
        qa_check( false === $qaRatingSend()['data']['success'], 'A real vote INSERT failure produced success.' );
        qa_check( null === $qaRatingRead(), 'Failed initial vote persisted data.' );
    } finally { $wpdb->query( 'DROP TRIGGER qa_reject_rating_insert' ); }
    $qaRatingFirst = $qaRatingSend()['data'];
    qa_check( true === $qaRatingFirst['success'] && 1 === $qaRatingFirst['data']['votes'] && 5 === $qaRatingFirst['data']['avg_rating'], 'Vote could not retry after INSERT recovery.' );
    $qaRatingPrevious = $qaRatingRead();
    qa_check( false === $qaRatingSend( array(), 'docy_article_rated_' . $qaRatingPost . '=1' )['data']['success'], 'An existing browser vote cookie was ignored.' );
    qa_check( $qaRatingPrevious === $qaRatingRead(), 'A repeated cookie vote changed totals.' );
    $wpdb->query( "CREATE TRIGGER qa_reject_rating_update BEFORE UPDATE ON {$wpdb->postmeta} FOR EACH ROW BEGIN IF NEW.meta_key='_docy_article_rating' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected QA vote update failure'; END IF; END" );
    try {
        qa_check( false === $qaRatingSend( array( 'rating' => '1' ) )['data']['success'], 'A real vote UPDATE failure produced success.' );
        qa_check( $qaRatingPrevious === $qaRatingRead(), 'Failed vote UPDATE lost previous totals.' );
    } finally { $wpdb->query( 'DROP TRIGGER qa_reject_rating_update' ); }
    $qaRatingLock = 'docy-rating-' . substr( hash( 'sha256', DB_NAME . '|' . $wpdb->postmeta . '|' . $qaRatingPost ), 0, 48 );
    qa_check( '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $qaRatingLock ) ), 'Vote contention fixture could not acquire the lock.' );
    try {
        qa_check( false === $qaRatingSend()['data']['success'], 'A competing vote overwrote the locked state.' );
        qa_check( $qaRatingPrevious === $qaRatingRead(), 'Lock contention changed totals.' );
    } finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $qaRatingLock ) ); }
    $qaRatingSecond = $qaRatingSend( array( 'rating' => '1' ) )['data'];
    qa_check( true === $qaRatingSecond['success'] && 2 === $qaRatingSecond['data']['votes'] && 3 === $qaRatingSecond['data']['avg_rating'], 'Recovered vote did not preserve and increment prior totals.' );
    update_post_meta( $qaRatingPost, $qaRatingMeta, array( 'votes' => 2 ) );
    $qaRatingCorrupt = $qaRatingRead();
    qa_check( false === $qaRatingSend()['data']['success'], 'Incomplete existing totals were silently reset.' );
    qa_check( $qaRatingCorrupt === $qaRatingRead(), 'A rejected vote changed incomplete existing totals.' );
    require_once dirname( __DIR__, 2 ) . '/inc/csf/classes/setup.class.php';
    CSF_Setup::$enqueue = true;
    CSF_Setup::$fields = array();
    CSF_Setup::add_admin_enqueue_scripts();
    $qaSettingsAsset = wp_scripts()->registered['csf'];
    $qaSettingsFile = 'assets/js/main' . ( CSF_Setup::$premium && SCRIPT_DEBUG ? '' : '.min' ) . '.js';
    qa_check( CSF_Setup::$version . '.' . substr( md5_file( CSF_Setup::$dir . '/' . $qaSettingsFile ), 0, 12 ) === $qaSettingsAsset->ver, 'The actual enqueued settings script does not identify its changed bytes.' );
    qa_check( CSF_Setup::$version === wp_scripts()->registered['csf-plugins']->ver, 'Unchanged settings dependencies changed version unexpectedly.' );
    require_once dirname( __DIR__, 2 ) . '/inc/template-functions.php';
    update_option( 'docy_opt', array( 'article_rating_title' => 'Rate the article', 'article_rating_thank_you' => 'Thank you for rating this article!' ) );
    $qaRatingOldQuery = $GLOBALS['wp_query'];
    $GLOBALS['wp_query'] = new WP_Query( array( 'p' => $qaRatingPost ) );
    $GLOBALS['wp_query']->the_post();
    delete_post_meta( $qaRatingPost, $qaRatingMeta );
    ob_start();
    require dirname( __DIR__, 2 ) . '/template-parts/single-post/article-rating.php';
    $qaRatingMarkup = ob_get_clean();
    qa_check( str_contains( $qaRatingMarkup, 'Valuta l’articolo' ) && str_contains( $qaRatingMarkup, 'Vota 5 su 5' ) && ! str_contains( $qaRatingMarkup, 'Rate the article' ), 'Legacy English rating defaults were rendered on the Italian site.' );
    $_COOKIE[ 'docy_article_rated_' . $qaRatingPost ] = '1';
    ob_start();
    require dirname( __DIR__, 2 ) . '/template-parts/single-post/article-rating.php';
    $qaRatingMarkup = ob_get_clean();
    qa_check( str_contains( $qaRatingMarkup, 'Grazie per aver votato questo articolo!' ) && ! str_contains( $qaRatingMarkup, 'Thank you for rating' ), 'Legacy English confirmation was rendered on the Italian site.' );
    unset( $_COOKIE[ 'docy_article_rated_' . $qaRatingPost ] );
    wp_update_post( array( 'ID' => $qaRatingPost, 'post_title' => 'L\'artista "Tunisia" <script>testo</script>' ) );
    update_post_meta( $qaRatingPost, $qaRatingMeta, array( 'votes' => 2, 'total' => 6 ) );
    $GLOBALS['post'] = get_post( $qaRatingPost );
    ob_start();
    require dirname( __DIR__, 2 ) . '/template-parts/single-post/article-rating.php';
    $qaRatingMarkup = ob_get_clean();
    preg_match( '~<script type="application/ld\\+json">(.*?)</script>~s', $qaRatingMarkup, $qaRatingSchemaMatch );
    $qaRatingSchema = json_decode( $qaRatingSchemaMatch[1] ?? '', true );
    qa_check( is_array( $qaRatingSchema ) && JSON_ERROR_NONE === json_last_error(), 'Article structured data is not valid JSON for quoted Italian titles.' );
    qa_check( wp_strip_all_tags( get_the_title( $qaRatingPost ) ) === $qaRatingSchema['headline'], 'JSON article titles changed their text during escaping.' );
    qa_check( 2 === $qaRatingSchema['aggregateRating']['ratingCount'] && 3 === $qaRatingSchema['aggregateRating']['ratingValue'], 'JSON structured data lost the actual vote totals.' );
    $GLOBALS['wp_query'] = $qaRatingOldQuery;
    wp_reset_postdata();
} finally {
    $wpdb->query( 'DROP TRIGGER IF EXISTS qa_reject_rating_insert' );
    $wpdb->query( 'DROP TRIGGER IF EXISTS qa_reject_rating_update' );
    wp_set_current_user( $qaRatingOriginalUser );
    wp_delete_post( $qaRatingPost, true );
    wp_delete_post( $qaRatingDraft, true );
}
