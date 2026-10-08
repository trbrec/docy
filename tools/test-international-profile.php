<?php
/** Exercise the production validators without bootstrapping WordPress or writing user data. */
$source = file_get_contents( __DIR__ . '/../inc/trb-artist-portal.php' );
$wanted = array( 'trb_portal_country_is_italy', 'trb_portal_validate_international_identifier', 'trb_portal_validate_profile_geography', 'trb_portal_validate_mobile', 'trb_portal_validate_tax_code', 'trb_portal_validate_identity_document_number', 'trb_portal_validate_identity_document_expiry' );
$tokens = token_get_all( $source );
foreach ( $tokens as $i => $token ) {
	if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) continue;
	$j = $i + 1;
	while ( isset( $tokens[$j] ) && is_array( $tokens[$j] ) && T_WHITESPACE === $tokens[$j][0] ) $j++;
	if ( ! isset( $tokens[$j] ) || ! is_array( $tokens[$j] ) || ! in_array( $tokens[$j][1], $wanted, true ) ) continue;
	$body = ''; $depth = 0; $opened = false;
	for ( $k = $i; $k < count( $tokens ); $k++ ) {
		$part = $tokens[$k]; $body .= is_array( $part ) ? $part[1] : $part;
		if ( '{' === $part ) { $depth++; $opened = true; }
		if ( '}' === $part && --$depth === 0 && $opened ) break;
	}
	eval( $body );
}
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function remove_accents( $value ) { return $value; }
function wp_timezone() { return new DateTimeZone( 'Europe/Rome' ); }
function wp_date( $format ) { return ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( $format ); }
function is_wp_error( $value ) { return false === $value; }
function trb_portal_lookup_postcode( $code ) { return '25038' === $code ? array( array( 'city' => 'Rovato', 'province' => 'BS' ) ) : false; }
function trb_portal_find_municipality_exact( $city, $province ) { return 'rovato' === strtolower( $city ) && in_array( $province, array( '', 'BS' ), true ) ? array( 'city' => 'Rovato', 'province' => 'BS' ) : false; }
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
foreach ( $wanted as $name ) check( function_exists( $name ), 'Production validator missing: ' . $name );
foreach ( array( array( 'Tunisia', 'Tunisi' ), array( 'تونس', 'تونس' ), array( 'Japan', '東京都' ), array( 'United Kingdom', 'London' ), array( 'Hong Kong', '香港' ) ) as $location ) {
	foreach ( array( false, true ) as $birth ) {
		$result = trb_portal_validate_profile_geography( $location[0], $location[1], '', 'SW1A 1AA', $birth );
		check( $result && $result['country'] === $location[0] && $result['city'] === $location[1] && '' === $result['province'], 'Foreign location must preserve Unicode and optional region' );
	}
}
check( false === trb_portal_validate_profile_geography( '', 'Tunisi' ), 'Country required' );
check( false === trb_portal_validate_profile_geography( 'Tunisia', ' ' ), 'City required' );
check( false === trb_portal_validate_profile_geography( 'Italia', 'Tunisi', '', '', true ), 'Italian birthplace must match Italian archive' );
check( false === trb_portal_validate_profile_geography( 'Italia', 'London', '', '25038' ), 'Italian city must match postcode' );
check( 'Rovato' === trb_portal_validate_profile_geography( 'IT', 'rovato', '', '25038' )['city'], 'Italian normalization retained' );
check( 'BS' === trb_portal_validate_profile_geography( 'Italy', 'Rovato', 'BS', '', true )['province'], 'Italian birthplace retained' );
foreach ( array( '+216 20 123 456' => '+21620123456', '00216 20 123 456' => '+21620123456', '333 0000000' => '+393330000000', '+44 (20) 1234-5678' => '+442012345678' ) as $input => $expected ) check( $expected === trb_portal_validate_mobile( $input ), 'Phone normalization: ' . $input );
foreach ( array( '+012345678', '+1234567890123456', '1234567', '<script>' ) as $input ) check( false === trb_portal_validate_mobile( $input ), 'Malformed international phone rejected' );
check( 'RSSMRA90A01H501W' === trb_portal_validate_tax_code( 'RSSMRA90A01H501W' ), 'Italian tax checksum retained' );
check( false === trb_portal_validate_tax_code( 'RSSMRA90A01H501A' ), 'Invalid Italian checksum rejected' );
check( '01 23-456.789' === trb_portal_validate_international_identifier( '01 23-456.789' ), 'Foreign tax punctuation retained' );
check( 'AB-123456789' === trb_portal_validate_identity_document_number( 'AB-123456789', 'passport' ), 'Passport number retained' );
check( '文書123456' === trb_portal_validate_identity_document_number( '文書123456', 'foreign_identity' ), 'Foreign identity Unicode retained' );
check( false === trb_portal_validate_identity_document_number( '123456789' ), 'CIE format remains enforced' );
check( false === trb_portal_validate_identity_document_number( 'AB123', 'unknown' ), 'Unknown document type rejected' );
$future = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '+15 years' )->format( 'Y-m-d' );
check( false === trb_portal_validate_identity_document_expiry( $future ), 'CIE maximum retained' );
check( $future === trb_portal_validate_identity_document_expiry( $future, 'foreign_identity' ), 'Foreign expiry may exceed 10 years' );
check( false === trb_portal_validate_identity_document_expiry( '2000-01-01', 'passport' ), 'Expired passport rejected' );
check( false === trb_portal_validate_identity_document_expiry( '2030-02-30', 'passport' ), 'Impossible date rejected' );
echo "International profile server validators verified.\n";
