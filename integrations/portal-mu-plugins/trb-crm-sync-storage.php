<?php
/** Verified database writes shared by the signed portal receivers. */
if ( ! defined( 'ABSPATH' ) ) exit;

function trb_crm_sync_atomic( $scope, array $cache_ids, callable $operation ) {
    global $wpdb;
    static $active = false;
    if ( $active ) return new WP_Error( 'trb_crm_nested_transaction', 'Operazione già in corso. Riprova tra poco.', array( 'status' => 409 ) );
    $lock = 'trb-sync:' . substr( hash( 'sha256', $wpdb->prefix . ':' . $scope ), 0, 48 );
    if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) ) {
        return new WP_Error( 'trb_crm_busy', 'Aggiornamento già in corso. Riprova tra poco.', array( 'status' => 409 ) );
    }
    $started = false;
    $active = true;
    $previous_touched = $GLOBALS['trb_crm_sync_storage_touched'] ?? null;
    $GLOBALS['trb_crm_sync_storage_touched'] = array();
    $prior_errors = $wpdb->suppress_errors( true );
    $purge = static function() use ( $cache_ids ) {
        foreach ( $GLOBALS['trb_crm_sync_storage_touched'] ?? array() as $type => $ids ) $cache_ids[$type] = array_unique( array_merge( $cache_ids[$type] ?? array(), array_keys( $ids ) ) );
        foreach ( $cache_ids as $type => $ids ) foreach ( $ids as $id ) {
            wp_cache_delete( $id, $type . '_meta' );
            if ( 'user' === $type ) clean_user_cache( $id ); else clean_post_cache( $id );
        }
        wp_cache_delete( 'alloptions', 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    };
    try {
        foreach ( array( $wpdb->usermeta, $wpdb->postmeta, $wpdb->options, $wpdb->posts, $wpdb->users ) as $table ) {
            $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
            if ( 'InnoDB' !== $engine ) throw new RuntimeException( 'Non-transactional storage.' );
        }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) throw new RuntimeException( 'Transaction unavailable.' );
        $started = true;
        $purge();
        $result = $operation();
        if ( is_wp_error( $result ) ) { $wpdb->query( 'ROLLBACK' ); $started = false; return $result; }
        if ( false === $wpdb->query( 'COMMIT' ) ) throw new RuntimeException( 'Commit unconfirmed.' );
        $started = false;
        return $result;
    } catch ( Throwable $error ) {
        if ( $started ) $wpdb->query( 'ROLLBACK' );
        do_action( 'trb_crm_sync_storage_failure', $scope, $error );
        return new WP_Error( 'trb_crm_storage_failed', 'Salvataggio non confermato. Riprova senza creare una nuova pratica.', array( 'status' => 503 ) );
    } finally {
        $purge();
        $wpdb->suppress_errors( $prior_errors );
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        $active = false;
        if ( null === $previous_touched ) unset( $GLOBALS['trb_crm_sync_storage_touched'] ); else $GLOBALS['trb_crm_sync_storage_touched'] = $previous_touched;
    }
}

function trb_crm_sync_write_meta( $type, $id, $key, $value, $delete = false ) {
    global $wpdb;
    if ( ! in_array( $type, array( 'post', 'user' ), true ) ) throw new InvalidArgumentException( 'Invalid metadata type.' );
    $GLOBALS['trb_crm_sync_storage_touched'][$type][$id] = true;
    $table = 'post' === $type ? $wpdb->postmeta : $wpdb->usermeta;
    $column = 'post' === $type ? 'post_id' : 'user_id';
    if ( $delete ) delete_metadata( $type, $id, $key );
    else update_metadata( $type, $id, $key, wp_slash( $value ) );
    $values = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE {$column}=%d AND meta_key=%s", $id, $key ) );
    if ( $wpdb->last_error || ( $delete ? count( $values ) !== 0 : ! $values ) ) throw new RuntimeException( 'Metadata persistence failed.' );
    if ( ! $delete ) foreach ( $values as $stored ) if ( (string) maybe_serialize( $value ) !== (string) $stored ) throw new RuntimeException( 'Metadata readback mismatch: ' . $key );
}

function trb_crm_sync_write_option( $key, $value, $delete = false ) {
    global $wpdb;
    if ( $delete ) delete_option( $key ); else update_option( $key, $value, false );
    $stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $key ) );
    wp_cache_delete( $key, 'options' );
    if ( $wpdb->last_error || ( $delete ? null !== $stored : null === $stored || (string) maybe_serialize( $value ) !== (string) $stored ) ) throw new RuntimeException( 'Option persistence failed.' );
}

/** Decode legacy serialized metadata without ever constructing PHP objects. */
function trb_crm_sync_decode_serialized( $value ) {
    if ( ! is_string( $value ) || ! is_serialized( $value ) ) return $value;
    $decoded = @unserialize( trim( $value ), array( 'allowed_classes' => false ) );
    $safe = static function( $item, $depth = 0 ) use ( &$safe ) {
        if ( $depth > 32 || is_object( $item ) || is_resource( $item ) ) return null;
        if ( is_array( $item ) ) foreach ( $item as $key => $child ) $item[$key] = $safe( $child, $depth + 1 );
        return $item;
    };
    return $safe( $decoded );
}
