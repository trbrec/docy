<?php
/** Execute the production outbox writer against SQLite with a failed insert. */
namespace CrmOutboxRegression;
const TRB_CRM_CONNECTOR_SOURCE = 'artist.trbrec.com';
function trb_crm_connector_install() { return true; }
function sanitize_key( $value ) { return $value; }
function sanitize_text_field( $value ) { return $value; }
function trb_crm_connector_table() { return 'outbox'; }
function trb_crm_connector_version() { return $GLOBALS['version']; }
function update_option( ...$args ) {}
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function trb_crm_connector_clean( $value ) { return $value; }
function current_time( ...$args ) { return '2026-10-10 12:00:00'; }
class Database {
    public \PDO $db;
    public bool $fail_insert = false;
    public function __construct() {
        $this->db = new \PDO( 'sqlite::memory:', null, null, array( \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION ) );
        $this->db->exec( 'CREATE TABLE outbox(id INTEGER PRIMARY KEY, event_id TEXT, entity_type TEXT, external_id TEXT, operation TEXT, entity_version INTEGER, payload TEXT, payload_hash TEXT, status TEXT, attempts INTEGER, next_attempt_at TEXT, created_at TEXT)' );
    }
    public function prepare( $sql, ...$values ) {
        foreach ( $values as $value ) $sql = preg_replace_callback( '/%[sd]/', fn() => is_int( $value ) ? (string) $value : $this->db->quote( $value ), $sql, 1 );
        return $sql;
    }
    public function query( $sql ) { return $this->db->exec( $sql ); }
    public function insert( $table, $data, $formats ) {
        if ( $this->fail_insert ) return false;
        $sql = 'INSERT INTO ' . $table . '(' . implode( ',', array_keys( $data ) ) . ') VALUES(' . implode( ',', array_fill( 0, count( $data ), '?' ) ) . ')';
        $this->db->prepare( $sql )->execute( array_values( $data ) ); return 1;
    }
}
$source = file_get_contents( __DIR__ . '/../inc/trb-crm-connector.php' );
$start = strpos( $source, 'function trb_crm_connector_queue(' );
$end = strpos( $source, 'function trb_crm_connector_profile_saved(', $start );
eval( 'namespace CrmOutboxRegression; ' . substr( $source, $start, $end - $start ) );
function check( $ok, $message ) { if ( ! $ok ) throw new \RuntimeException( $message ); }
$wpdb = new Database(); $GLOBALS['version'] = 1;
check( trb_crm_connector_queue( 'artist', '198', array( 'name' => 'Fictional Tunisia artist' ) ), 'First event not queued' );
$wpdb->fail_insert = true; $GLOBALS['version'] = 2;
check( ! trb_crm_connector_queue( 'artist', '198', array( 'name' => 'Updated fixture' ) ), 'Failed insert reported success' );
check( $wpdb->db->query( 'SELECT status FROM outbox WHERE entity_version=1' )->fetchColumn() === 'queued', 'Failed insert discarded the previous deliverable snapshot' );
$wpdb->fail_insert = false; $GLOBALS['version'] = 3;
check( trb_crm_connector_queue( 'artist', '198', array( 'name' => 'Newer fixture' ) ), 'Replacement not queued' );
check( $wpdb->db->query( 'SELECT status FROM outbox WHERE entity_version=1' )->fetchColumn() === 'superseded', 'Successful replacement did not retire the previous snapshot' );
$GLOBALS['version'] = 2;
check( trb_crm_connector_queue( 'artist', '198', array( 'name' => 'Late older fixture' ) ), 'Late event not queued' );
check( $wpdb->db->query( 'SELECT status FROM outbox WHERE entity_version=3' )->fetchColumn() === 'queued', 'An older concurrent snapshot superseded a newer version' );
echo "CRM outbox: failed insert preserves delivery; replacement supersedes only older durable events.\n";
