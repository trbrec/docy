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

/** Display labels only. International style names remain proper genre names. */
function trb_symphonic_genre_labels() {
	return array(
		'Alternative' => 'Musica alternativa', 'Arabic' => 'Musica araba', 'Audiobooks' => 'Audiolibri',
		'Brazilian' => 'Musica brasiliana', "Children's Music" => 'Musica per bambini', 'Chinese' => 'Musica cinese',
		'Christian & Gospel' => 'Musica cristiana e gospel', 'Classical' => 'Musica classica', 'Comedy' => 'Musica comica',
		'Easy Listening' => 'Musica di facile ascolto', 'Electronic' => 'Musica elettronica', 'Fitness & Workout' => 'Musica per allenamento',
		'French Pop' => 'Pop francese', 'German Folk' => 'Musica popolare tedesca', 'German Pop' => 'Pop tedesco',
		'Holiday' => 'Musica per le festività', 'Indian' => 'Musica indiana', 'Inspirational' => 'Musica ispirazionale',
		'Instrumental' => 'Musica strumentale', 'Korean' => 'Musica coreana', 'Latin' => 'Musica latinoamericana',
		'Marching Bands' => 'Bande da parata', 'Other' => 'Altro', 'Singer/Songwriter' => 'Cantautorato',
		'Soundtrack' => 'Colonne sonore', 'Spoken Word' => 'Parlato e recitazione', 'Vocal' => 'Musica vocale', 'World' => 'Musiche del mondo',
		'Chinese Alt' => 'Musica alternativa cinese', 'Indie Egyptian' => 'Indie egiziano', 'Indie Levant' => 'Indie levantino',
		'Indie Maghreb' => 'Indie maghrebino', 'Korean Indie' => 'Indie coreano', 'Turkish Alternative' => 'Musica alternativa turca',
		'Arabic Pop' => 'Pop arabo', 'Islamic' => 'Musica islamica', 'Levant' => 'Musica levantina', 'North African' => 'Musica nordafricana',
		'Acoustic Blues' => 'Blues acustico', 'Classic Blues' => 'Blues classico', 'Contemporary Blues' => 'Blues contemporaneo',
		'Electric Blues' => 'Blues elettrico', 'Traditional Blues' => 'Blues tradizionale', 'Melodic Funk' => 'Funk melodico',
		'Lullabies' => 'Ninne nanne', 'Sing-Along' => 'Canzoni da cantare insieme', 'Stories' => 'Racconti',
		'Chinese Classical' => 'Musica classica cinese', 'Chinese Flute' => 'Flauto cinese', 'Chinese Opera' => 'Opera cinese',
		'Chinese Orchestral' => 'Musica orchestrale cinese', 'Chinese Regional Folk' => 'Musica popolare regionale cinese',
		'Chinese Strings' => 'Archi cinesi', 'Taiwanese Folk' => 'Musica popolare taiwanese', 'Tibetan Native Music' => 'Musica tradizionale tibetana',
		'Christian Metal' => 'Metal cristiano', 'Christian Pop' => 'Pop cristiano', 'Christian Rap' => 'Rap cristiano',
		'Christian Rock' => 'Rock cristiano', 'Classic Christian' => 'Musica cristiana classica', 'Contemporary Gospel' => 'Gospel contemporaneo',
		'Praise & Worship' => 'Lode e adorazione', 'Traditional Gospel' => 'Gospel tradizionale',
		'Art Song' => 'Lirica da camera', 'Avant-Garde' => 'Avanguardia', 'Baroque Era' => 'Musica barocca',
		'Brass & Woodwinds' => 'Ottoni e legni', 'Cello' => 'Violoncello', 'Chamber Music' => 'Musica da camera',
		'Chant' => 'Canto intonato', 'Choral' => 'Musica corale', 'Classical Crossover' => 'Classica e contaminazioni',
		'Classical Era' => 'Classicismo', 'Contemporary Era' => 'Musica contemporanea', 'Guitar' => 'Chitarra',
		'Impressionist' => 'Impressionismo', 'Medieval Era' => 'Musica medievale', 'Minimalism' => 'Minimalismo',
		'Modern Era' => 'Musica moderna', 'Orchestral' => 'Musica orchestrale', 'Percussion' => 'Percussioni',
		'Renaissance' => 'Musica rinascimentale', 'Romantic Era' => 'Musica romantica', 'Sacred' => 'Musica sacra',
		'Solo Instrumental' => 'Strumento solista', 'Violin' => 'Violino', 'Novelty' => 'Canzoni umoristiche', 'Standup Comedy' => 'Monologhi comici',
		'Alternative Country' => 'Country alternativo', 'Contemporary Bluegrass' => 'Bluegrass contemporaneo',
		'Contemporary Country' => 'Country contemporaneo', 'Thai Country' => 'Country thailandese',
		'Traditional Bluegrass' => 'Bluegrass tradizionale', 'Traditional Country' => 'Country tradizionale',
		'Acapellas' => 'Canto a cappella', 'Maghreb Dance' => 'Dance maghrebina', 'Psychedelic' => 'Musica psichedelica',
		'Levant Electronic' => 'Musica elettronica levantina', 'Maghreb Electronic' => 'Musica elettronica maghrebina',
		'Contemporary Folk' => 'Musica popolare contemporanea', 'Iraqi Folk' => 'Musica popolare irachena',
		'Traditional Folk' => 'Musica popolare tradizionale', 'Alternative Rap' => 'Rap alternativo',
		'Chinese Hip-Hop' => 'Hip hop cinese', 'Egyptian Hip-Hop' => 'Hip hop egiziano', 'Ghanaian Drill' => 'Drill ghanese',
		'Korean Hip-Hop' => 'Hip hop coreano', 'Latin Rap' => 'Rap latinoamericano', 'Levant Hip-Hop' => 'Hip hop levantino',
		'Maghreb Hip-Hop' => 'Hip hop maghrebino', 'Russian Hip-Hop' => 'Hip hop russo',
		'South African Hip-Hop' => 'Hip hop sudafricano', 'Turkish Hip-Hop/Rap' => 'Hip hop e rap turco', 'UK Hip Hop' => 'Hip hop britannico',
		'Christmas' => 'Natale', "Christmas: Children's" => 'Natale: musica per bambini', 'Christmas: Classic' => 'Natale: grandi classici',
		'Christmas: Classical' => 'Natale: musica classica', 'Christmas: Jazz' => 'Natale: jazz', 'Christmas: Modern' => 'Natale: musica moderna',
		'Christmas: Pop' => 'Natale: pop', 'Christmas: R&B' => 'Natale: R&B', 'Christmas: Religious' => 'Natale: musica religiosa',
		'Christmas: Rock' => 'Natale: rock', 'Easter' => 'Pasqua', 'Thanksgiving' => 'Festa del Ringraziamento',
		'Devotional & Spiritual' => 'Musica devozionale e spirituale', 'Indian Classical' => 'Musica classica indiana',
		'Indian Folk' => 'Musica popolare indiana', 'Indian Pop' => 'Pop indiano', 'Regional Indian' => 'Musica regionale indiana',
		'Avant-Garde Jazz' => 'Jazz d’avanguardia', 'Contemporary Jazz' => 'Jazz contemporaneo', 'Traditional Jazz' => 'Jazz tradizionale',
		'Vocal Jazz' => 'Jazz vocale', 'Latin Jazz' => 'Jazz latinoamericano', 'Korean Traditional' => 'Musica tradizionale coreana',
		'Alternativo & Rock Latino' => 'Musica alternativa e rock latinoamericano', 'Baladas y Boleros' => 'Ballate e boleri',
		'Contemporary Latin' => 'Musica latinoamericana contemporanea', 'Raices' => 'Musica tradizionale latinoamericana',
		'Regional Mexicano' => 'Musica regionale messicana', 'Salsa y Tropical' => 'Salsa e musica tropicale', 'Urbano Latino' => 'Musica urbana latinoamericana',
		'Healing' => 'Musica per il benessere', 'Meditation' => 'Meditazione', 'Nature' => 'Natura', 'Relaxation' => 'Rilassamento', 'Travel' => 'Viaggi',
		'Alternative Pop' => 'Pop alternativo', 'Korean Folk-Pop' => 'Pop e musica popolare coreana',
		'Malaysian Pop' => 'Pop malese', 'Original Pilipino Music' => 'Musica originale filippina', 'Thai Pop' => 'Pop thailandese',
		'Contemporary R&B' => 'R&B contemporaneo', 'Modern Dancehall' => 'Dancehall moderna', 'Chinese Rock' => 'Rock cinese',
		'Korean Rock' => 'Rock coreano', 'Psychedelic Rock' => 'Rock psichedelico', 'Alternative Folk' => 'Musica popolare alternativa',
		'Contemporary Singer/Songwriter' => 'Cantautorato contemporaneo', 'New Acoustic' => 'Nuova musica acustica',
		'Foreign Cinema' => 'Cinema internazionale', 'Original Score' => 'Colonna sonora originale', 'Sound Effects' => 'Effetti sonori',
		'TV Soundtrack' => 'Colonne sonore televisive', 'Video Game' => 'Musica per videogiochi', 'Traditional Pop' => 'Pop tradizionale', 'Vocal Pop' => 'Pop vocale',
		'Caribbean' => 'Musica caraibica', 'Celtic' => 'Musica celtica', 'Celtic Folk' => 'Musica popolare celtica',
		'Contemporary Celtic' => 'Musica celtica contemporanea', 'Europe' => 'Europa', 'France' => 'Francia', 'Iberia' => 'Penisola iberica',
		'Indonesian Religious' => 'Musica religiosa indonesiana', 'Israeli' => 'Musica israeliana', 'Japan' => 'Giappone',
		'North America' => 'America settentrionale', 'Russian' => 'Musica russa', 'Russian Chanson' => 'Canzone russa',
		'South Africa' => 'Sudafrica', 'South America' => 'America meridionale', 'Traditional Celtic' => 'Musica celtica tradizionale', 'Turkish' => 'Musica turca',
	);
}

function trb_symphonic_genre_label( $name ) {
	static $labels = null;
	if ( null === $labels ) $labels = trb_symphonic_genre_labels();
	return $labels[ $name ] ?? $name;
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
