<?php
/** Genre/subgenre pairs observed in the Symphonic track editor, 2026-09-25. */
function trb_symphonic_genres() {
	static $genres = null;
	if ( null === $genres ) {
		$path = get_template_directory() . '/assets/data/symphonic-genres.json';
		$genres = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $genres ) ) $genres = array();
	}
	return $genres;
}

function trb_symphonic_genre_release( $release_id ) {
	return $release_id && 'symphonic-2026-09' === get_post_meta( $release_id, '_trb_release_genre_schema', true );
}

function trb_symphonic_genre_errors( $tracks ) {
	$catalogue = trb_symphonic_genres();
	$errors = array();
	foreach ( (array) $tracks as $index => $track ) {
		$primary = isset( $track['primary_genre'] ) ? (string) $track['primary_genre'] : '';
		$subgenre = isset( $track['secondary_genre'] ) ? (string) $track['secondary_genre'] : '';
		if ( ! isset( $catalogue[ $primary ] ) || ! in_array( $subgenre, $catalogue[ $primary ], true ) ) {
			$errors[] = 'Brano ' . ( (int) $index + 1 ) . ': seleziona un genere primario e un sottogenere valido per quel genere.';
		}
	}
	return $errors;
}
