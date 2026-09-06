<?php
/** Exact integer PCM rail detection. No resampling, filtering or dB rounding. */
function trb_pcm_full_scale( $path ) {
	$fh = @fopen( $path, 'rb' );
	if ( ! $fh ) throw new RuntimeException( 'WAV non leggibile.' );
	try {
		$size = fstat( $fh )['size'];
		$header = fread( $fh, 12 );
		if ( strlen( $header ) !== 12 || substr( $header, 0, 4 ) !== 'RIFF' || substr( $header, 8, 4 ) !== 'WAVE' ) throw new RuntimeException( 'Contenitore PCM non supportato per la verifica esatta.' );
		$limit = 8 + unpack( 'V', substr( $header, 4, 4 ) )[1];
		if ( $limit > $size || $limit < 12 ) throw new RuntimeException( 'WAV incompleto.' );
		$fmt = null; $chunks = array();
		while ( ftell( $fh ) + 8 <= $limit ) {
			$chunk = fread( $fh, 8 ); $length = unpack( 'V', substr( $chunk, 4, 4 ) )[1]; $offset = ftell( $fh );
			if ( $offset + $length > $limit ) throw new RuntimeException( 'Chunk WAV incompleto.' );
			if ( substr( $chunk, 0, 4 ) === 'fmt ' ) {
				if ( $fmt !== null || $length < 16 ) throw new RuntimeException( 'Formato WAV ambiguo.' );
				$raw = fread( $fh, min( 40, $length ) );
				$fmt = unpack( 'vformat/vchannels/Vrate/Vbytes/vblock/vbits', substr( $raw, 0, 16 ) );
				if ( $fmt['format'] === 65534 ) {
					if ( strlen( $raw ) < 40 || unpack( 'v', substr( $raw, 16, 2 ) )[1] < 22 || substr( $raw, 24, 16 ) !== hex2bin( '0100000000001000800000aa00389b71' ) || unpack( 'v', substr( $raw, 18, 2 ) )[1] !== $fmt['bits'] ) throw new RuntimeException( 'PCM estensibile non supportato.' );
				} elseif ( $fmt['format'] !== 1 ) throw new RuntimeException( 'Audio non PCM intero.' );
				if ( ! in_array( $fmt['bits'], array( 16, 24 ), true ) || $fmt['channels'] < 1 || $fmt['channels'] > 64 || $fmt['block'] !== $fmt['channels'] * (int) ( $fmt['bits'] / 8 ) ) throw new RuntimeException( 'Allineamento PCM non valido.' );
			} elseif ( substr( $chunk, 0, 4 ) === 'data' ) $chunks[] = array( $offset, $length );
			if ( fseek( $fh, $offset + $length + ( $length % 2 ) ) !== 0 ) throw new RuntimeException( 'Lettura WAV interrotta.' );
		}
		if ( ! $fmt || ! $chunks ) throw new RuntimeException( 'Campioni PCM assenti.' );
		$width = (int) ( $fmt['bits'] / 8 ); $rail = 1 << ( $fmt['bits'] - 1 );
		$count = 0; $samples = 0; $peak = 0; $first = null;
		foreach ( $chunks as list( $offset, $length ) ) {
			if ( $length % $fmt['block'] !== 0 ) throw new RuntimeException( 'Frame PCM incompleto.' );
			fseek( $fh, $offset );
			while ( $length > 0 ) {
				$n = min( $length, $fmt['block'] * 4096 ); $buffer = fread( $fh, $n );
				if ( strlen( $buffer ) !== $n ) throw new RuntimeException( 'Campioni PCM troncati.' );
				for ( $i = 0; $i < $n; $i += $width ) {
					$v = ord( $buffer[$i] ) | ( ord( $buffer[$i+1] ) << 8 );
					if ( $width === 3 ) $v |= ord( $buffer[$i+2] ) << 16;
					if ( $v >= $rail ) $v -= $rail * 2;
					$peak = max( $peak, abs( $v ) );
					if ( $v === -$rail || $v === $rail - 1 ) { $count++; if ( $first === null ) $first = $samples; }
					$samples++;
				}
				$length -= $n;
			}
		}
		if ( ! $samples ) throw new RuntimeException( 'Audio vuoto.' );
		return array( 'method' => 'original-integer-pcm-v1', 'verified' => true, 'samples' => $samples, 'full_scale_samples' => $count, 'first_full_scale_sample' => $first, 'peak_level_dbfs' => $peak ? 20 * log10( $peak / $rail ) : null );
	} finally { fclose( $fh ); }
}
