<?php
/** Behavioral regression test for the production demo-delivery calculator. */

$portal = file_get_contents( dirname( __DIR__ ) . '/inc/trb-artist-portal.php' );
$start  = strpos( $portal, 'function trb_portal_demo_delivery_window()' );
$end    = strpos( $portal, 'function trb_portal_store_demo_file', $start );
if ( false === $start || false === $end ) {
	fwrite( STDERR, "Impossibile caricare il calcolatore della finestra demo.\n" );
	exit( 1 );
}
eval( substr( $portal, $start, $end - $start ) );

$timezone = trb_portal_demo_delivery_timezone();
$cases = array(
	array( '2026-08-24 08:00', '2026-08-24 11:30', 'prima dell’apertura' ),
	array( '2026-08-24 16:30', '2026-08-25 09:30', 'passaggio al giorno seguente' ),
	array( '2026-08-28 17:30', '2026-08-29 10:30', 'il sabato è lavorativo' ),
	array( '2026-08-29 17:30', '2026-08-31 10:30', 'la domenica è esclusa' ),
	array( '2026-08-30 10:00', '2026-08-31 11:30', 'invio domenicale' ),
	array( '2026-08-29 08:30', '2026-08-29 11:30', 'tre ore esatte il sabato' ),
);

foreach ( $cases as $case ) {
	$submitted = ( new DateTimeImmutable( $case[0], $timezone ) )->getTimestamp();
	$expected  = ( new DateTimeImmutable( $case[1], $timezone ) )->getTimestamp();
	$actual    = trb_portal_add_demo_working_hours( $submitted );
	if ( $actual !== $expected ) {
		fwrite( STDERR, 'FAIL ' . $case[2] . ': atteso ' . $case[1] . ', ottenuto ' . wp_date( 'Y-m-d H:i', $actual, $timezone ) . "\n" );
		exit( 1 );
	}
	echo 'PASS ' . $case[2] . "\n";
}

$delivery_cases = array(
	array( '2026-08-29 18:30', '2026-08-31 08:30', 'chiusura esatta del sabato' ),
	array( '2026-08-30 12:00', '2026-08-31 08:30', 'retry domenicale' ),
	array( '2026-08-25 07:45', '2026-08-25 08:30', 'retry prima dell’apertura' ),
	array( '2026-08-25 12:00', '2026-08-25 12:00', 'retry già nella finestra' ),
);
foreach ( $delivery_cases as $case ) {
	$attempt  = ( new DateTimeImmutable( $case[0], $timezone ) )->getTimestamp();
	$expected = ( new DateTimeImmutable( $case[1], $timezone ) )->getTimestamp();
	$actual   = trb_portal_demo_next_delivery_time( $attempt );
	if ( $actual !== $expected ) {
		fwrite( STDERR, 'FAIL ' . $case[2] . "\n" );
		exit( 1 );
	}
	echo 'PASS ' . $case[2] . "\n";
}

$total = count( $cases ) + count( $delivery_cases );
echo $total . "/" . $total . " casi superati\n";
