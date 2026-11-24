<?php
/**
 * Lesson video block: page links become embeds, anything else is left to a video element.
 *
 * @package OhMyLMS
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	if ( ! function_exists( 'wp_parse_url' ) ) {
		/**
		 * Stand-in for the WordPress function.
		 *
		 * @param string $url URL.
		 * @return array|false
		 */
		function wp_parse_url( $url ) {
			return parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Unit-test stand-in for wp_parse_url().
		}
	}
	if ( ! function_exists( 'wp_parse_str' ) ) {
		/**
		 * Stand-in for the WordPress function.
		 *
		 * @param string $input  Query string.
		 * @param array  $result Parsed values.
		 */
		function wp_parse_str( $input, &$result ) {
			parse_str( $input, $result );
		}
	}
	require dirname( __DIR__, 2 ) . '/includes/Learning/LessonBlocks.php';

	$failed = false;
	$checks = 0;
	function embed_check( $expected, $url ) {
		global $checks, $failed;
		++$checks;
		$actual = \OhMyLMS\Learning\LessonBlocks::embed_url( $url );
		if ( $expected !== $actual ) {
			$failed = true;
			echo "$url gave '$actual', expected '$expected'.\n";
		}
	}

	$youtube = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';
	embed_check( $youtube, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
	embed_check( $youtube, 'https://youtube.com/watch?v=dQw4w9WgXcQ&t=10s' );
	embed_check( $youtube, 'https://youtu.be/dQw4w9WgXcQ' );
	embed_check( $youtube, 'https://www.youtube.com/shorts/dQw4w9WgXcQ' );
	embed_check( $youtube, 'https://www.youtube.com/embed/dQw4w9WgXcQ' );
	embed_check( 'https://player.vimeo.com/video/123456789', 'https://vimeo.com/123456789' );
	embed_check( '', 'https://example.com/lesson.mp4' );
	embed_check( '', 'https://www.youtube.com/watch?v=short' );
	embed_check( '', 'https://evil.example/youtube.com/watch?v=dQw4w9WgXcQ' );
	embed_check( '', 'not a url' );

	echo $failed ? "Embed checks failed.\n" : "OK: {$checks} embed checks passed.\n";
	exit( $failed ? 1 : 0 );
}
