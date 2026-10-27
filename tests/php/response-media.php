<?php
/** Standalone: recorded responses are accepted only when the file really is audio/video. Run: php tests/php/response-media.php */
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/includes/Assessment/ResponseMedia.php';

use OhMyLMS\Assessment\ResponseMedia;

$webm = "\x1A\x45\xDF\xA3" . str_repeat( "\0", 12 );
$checks = array(
	'webm accepted'       => ResponseMedia::looks_like( $webm, 'webm' ),
	'webm named ogg'      => ! ResponseMedia::looks_like( $webm, 'ogg' ),
	'ogg accepted'        => ResponseMedia::looks_like( 'OggS' . str_repeat( "\0", 12 ), 'oga' ),
	'wav accepted'        => ResponseMedia::looks_like( 'RIFF' . "\0\0\0\0" . 'WAVE' . "\0\0\0\0", 'wav' ),
	'riff not wave'       => ! ResponseMedia::looks_like( 'RIFF' . "\0\0\0\0" . 'AVI ' . "\0\0\0\0", 'wav' ),
	'mp3 id3 accepted'    => ResponseMedia::looks_like( 'ID3' . str_repeat( "\0", 13 ), 'mp3' ),
	'mp3 frame accepted'  => ResponseMedia::looks_like( "\xFF\xFB" . str_repeat( "\0", 14 ), 'mp3' ),
	'mp4 accepted'        => ResponseMedia::looks_like( "\0\0\0\x18" . 'ftypisom' . str_repeat( "\0", 4 ), 'mp4' ),
	'script as webm'      => ! ResponseMedia::looks_like( '<?php echo 1; ?>' , 'webm' ),
	'html as mp3'         => ! ResponseMedia::looks_like( '<html><body>x</b', 'mp3' ),
	'unknown extension'   => ! ResponseMedia::looks_like( $webm, 'php' ),
);
$failed = array_keys( array_filter( $checks, static fn( $ok ) => ! $ok ) );
if ( $failed ) {
	fwrite( STDERR, 'FAILED: ' . implode( ', ', $failed ) . "\n" );
	exit( 1 );
}
echo count( $checks ) . " response-media checks passed.\n";
