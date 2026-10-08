<?php
require __DIR__ . '/../inc/trb-pcm-full-scale.php';
function verify_pcm( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( $message ); }
function pcm_fixture( $bits, $samples, $extensible = false ) {
	$width = (int) ( $bits / 8 ); $data = '';
	foreach ( $samples as $v ) $data .= substr( pack( 'V', $v & 0xffffff ), 0, $width );
	$fmt = pack( 'vvVVvv', $extensible ? 65534 : 1, 2, 44100, 44100 * 2 * $width, 2 * $width, $bits );
	if ( $extensible ) $fmt .= pack( 'vvV', 22, $bits, 3 ) . hex2bin( '0100000000001000800000aa00389b71' );
	$body = 'WAVE' . 'JUNK' . pack( 'V', 1 ) . "x\0" . 'fmt ' . pack( 'V', strlen( $fmt ) ) . $fmt . 'data' . pack( 'V', strlen( $data ) ) . $data;
	return 'RIFF' . pack( 'V', strlen( $body ) ) . $body;
}
$path = tempnam( sys_get_temp_dir(), 'pcm-qa-' );
try {
	foreach ( array( 16, 24 ) as $bits ) foreach ( array( false, true ) as $ext ) {
		$rail = 1 << ( $bits - 1 ); $below = (int) floor( $rail * pow( 10, -0.2 / 20 ) );
		foreach ( array( array( $below, -$below ), array( $rail - 2, -$rail + 1 ), array( 0, 0 ) ) as $samples ) {
			file_put_contents( $path, pcm_fixture( $bits, $samples, $ext ) ); $r = trb_pcm_full_scale( $path );
			verify_pcm( $r['full_scale_samples'] === 0 && $r['verified'], 'Sub-zero samples falsely rejected.' );
		}
		foreach ( array( array( 0, $rail - 1 ), array( -$rail, 0 ) ) as $samples ) {
			file_put_contents( $path, pcm_fixture( $bits, $samples, $ext ) ); $r = trb_pcm_full_scale( $path );
			verify_pcm( $r['full_scale_samples'] === 1, 'Single full-scale sample missed.' );
		}
		// A rail across the read-buffer boundary must be counted once.
		$samples = array_fill( 0, 8192, 1 ); $samples[] = 0; $samples[] = -$rail;
		file_put_contents( $path, pcm_fixture( $bits, $samples, $ext ) ); $r = trb_pcm_full_scale( $path );
		verify_pcm( $r['full_scale_samples'] === 1 && $r['first_full_scale_sample'] === 8193, 'Buffer/channel alignment lost.' );
	}
	$bad = substr( pcm_fixture( 24, array( 0, 0 ) ), 0, -1 ); file_put_contents( $path, $bad );
	$rejected = false; try { trb_pcm_full_scale( $path ); } catch ( RuntimeException $e ) { $rejected = true; }
	verify_pcm( $rejected, 'Truncation falsely certified.' );
	echo "PASS exact PCM rails, -0.2dBFS, one LSB below, silence, both channels, extensible WAV and truncation\n";
} finally { unlink( $path ); }
