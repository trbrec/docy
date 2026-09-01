<?php
/** Regression tests for the exact server-side release draft normalizer. */

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_json_encode( $value ) {
	return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

$portal = file_get_contents( dirname( __DIR__ ) . '/inc/trb-artist-portal.php' );
$start  = strpos( $portal, 'function trb_portal_normalize_release_draft_pairs' );
$end    = strpos( $portal, '/** One-time reversible migration', $start );
if ( false === $start || false === $end ) {
	throw new RuntimeException( 'Release draft normalizer not found.' );
}
eval( substr( $portal, $start, $end - $start ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged

function draft_pair( $name, $value ) {
	return array( $name, $value );
}

function draft_credit( $track, $index, $name, $role ) {
	return array(
		draft_pair( "trb_tracks[$track][credits][credits][$index][name]", $name ),
		draft_pair( "trb_tracks[$track][credits][credits][$index][role]", $role ),
	);
}

function draft_assert( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
}

function draft_roles( $pairs, $track, $index ) {
	$name = "trb_tracks[$track][credits][credits][$index][roles_json]";
	foreach ( $pairs as $pair ) {
		if ( $name === $pair[0] ) return json_decode( $pair[1], true );
	}
	return null;
}

$id_zero = array_merge(
	array( draft_pair( 'trb_release_type', 'single' ), draft_pair( 'trb_tracks[0][title]', 'Brano' ) ),
	draft_credit( 0, 0, 'Partecipante A', 'Producer' ),
	draft_credit( 0, 1, 'Partecipante B', 'Vocalist' ),
	draft_credit( 0, 9100, 'Partecipante A', 'Mixing Engineer' ),
	draft_credit( 0, 9200, 'Partecipante B', 'Piano' )
);
$normalized = trb_portal_normalize_release_draft_pairs( $id_zero, $report );
draft_assert( 4 === $report['legacy_technical'] && 0 === $report['unmapped_technical'], 'ID-Zero legacy roles were not mapped.' );
draft_assert( array( 'Producer', 'Mixing Engineer' ) === draft_roles( $normalized, 0, 0 ), 'ID-Zero first credit lost a role.' );
draft_assert( array( 'Vocalist', 'Piano' ) === draft_roles( $normalized, 0, 1 ), 'ID-Zero second credit lost a role.' );
draft_assert( ! preg_match( '/\[(?:9100|9200)\]/', wp_json_encode( $normalized ) ), 'ID-Zero sparse indexes remained in the canonical draft.' );

$second_pass = trb_portal_normalize_release_draft_pairs( $normalized, $second_report );
draft_assert( 0 === $second_report['legacy_technical'] && wp_json_encode( $normalized ) === wp_json_encode( $second_pass ), 'Canonical normalization is not idempotent.' );

$genesestri = array_merge(
	draft_credit( 0, 0, 'Partecipante', 'Producer' ),
	draft_credit( 0, 9100, 'Partecipante', 'Composer' ),
	draft_credit( 0, 9101, 'Partecipante', 'Writer' ),
	draft_credit( 0, 9102, 'Partecipante', 'Arranger' ),
	draft_credit( 0, 9103, 'Partecipante', 'Piano' )
);
$genesestri_clean = trb_portal_normalize_release_draft_pairs( $genesestri, $genesestri_report );
draft_assert( 8 === $genesestri_report['legacy_technical'] && 0 === $genesestri_report['unmapped_technical'], 'genesestri legacy roles were not mapped.' );
draft_assert( array( 'Producer', 'Composer', 'Writer', 'Arranger', 'Piano' ) === draft_roles( $genesestri_clean, 0, 0 ), 'genesestri roles were not preserved.' );

$repeated = array_merge(
	draft_credit( 0, 0, 'Stesso nome', 'Producer' ),
	draft_credit( 0, 1, 'Stesso nome', 'Vocalist' ),
	draft_credit( 0, 9100, 'Stesso nome', 'Mixing Engineer' ),
	draft_credit( 0, 9200, 'Stesso nome', 'Piano' )
);
$repeated_clean = trb_portal_normalize_release_draft_pairs( $repeated, $repeated_report );
draft_assert( array( 'Producer', 'Mixing Engineer' ) === draft_roles( $repeated_clean, 0, 0 ), 'Repeated-name first row received the wrong roles.' );
draft_assert( array( 'Vocalist', 'Piano' ) === draft_roles( $repeated_clean, 0, 1 ), 'Repeated-name second row received the wrong roles.' );

$ambiguous = array_merge( draft_credit( 0, 0, 'Stesso nome', 'Producer' ), draft_credit( 0, 1, 'Stesso nome', 'Vocalist' ), draft_credit( 0, 9300, 'Stesso nome', 'Piano' ) );
$ambiguous_clean = trb_portal_normalize_release_draft_pairs( $ambiguous, $ambiguous_report );
draft_assert( 1 === $ambiguous_report['unmapped_technical'], 'Ambiguous role was silently assigned to the wrong person.' );
draft_assert( false !== strpos( wp_json_encode( $ambiguous_clean ), '[9300]' ), 'Ambiguous legacy data was not kept recoverable.' );

echo "release draft PHP normalizer regressions: ok\n";
