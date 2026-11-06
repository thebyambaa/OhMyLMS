<?php
/**
 * Mascot sanitizing without WordPress: bundled kinds, uploads and placement toggles.
 *
 * @package OhMyLMS
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	/**
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function wp_strip_all_tags( $value ) {
		return trim( preg_replace( '/<[^>]*>/', '', (string) $value ) );
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_key( $value ) {
		$value = strtolower( (string) $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value );
	}

	/**
	 * @param mixed $id Attachment ID.
	 * @return bool
	 */
	function wp_attachment_is_image( $id ) {
		return 5 === (int) $id;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}

	/**
	 * @param string $html    HTML.
	 * @param array  $allowed Allowed tags. Unused in this stub.
	 * @return string
	 */
	function wp_kses( $html, $allowed ) {
		unset( $allowed );
		return $html;
	}

	$checks = 0;
	$failed = false;

	/**
	 * @param bool   $condition Condition.
	 * @param string $message   Message.
	 */
	function mascot_check( $condition, $message ) {
		global $checks, $failed;
		++$checks;
		if ( ! $condition ) {
			$failed = true;
			echo $message . "\n";
		}
	}
}

namespace {
	require dirname( __DIR__, 2 ) . '/includes/Design/Mascot.php';

	$clean = \OhMyLMS\Design\Mascot::sanitize(
		array(
			'kind'       => 'owl',
			'name'       => '  Oyu <b>x</b>  ',
			'greeting'   => 'Сайн уу',
			'placements' => array(
				'profile' => true,
				'quiz'    => '1',
				'empty'   => false,
			),
		)
	);
	mascot_check( 'owl' === $clean['kind'], 'Owl kind was dropped.' );
	mascot_check( 'Oyu x' === $clean['name'], 'Name was not plain text.' );
	mascot_check( 'Сайн уу' === $clean['greeting'], 'Cyrillic greeting was lost.' );
	mascot_check( true === $clean['placements']['profile'] && true === $clean['placements']['quiz'] && false === $clean['placements']['empty'] && true === $clean['placements']['celebrate'], 'Placements were not read.' );

	$upload = \OhMyLMS\Design\Mascot::sanitize( array( 'kind' => 'upload', 'attachment_id' => 9 ) );
	mascot_check( 'none' === $upload['kind'] && 0 === $upload['attachment_id'], 'A non-image upload was kept.' );
	$kept = \OhMyLMS\Design\Mascot::sanitize( array( 'kind' => 'upload', 'attachment_id' => 5 ) );
	mascot_check( 'upload' === $kept['kind'] && 5 === $kept['attachment_id'], 'A real image upload was dropped.' );

	$errors = \OhMyLMS\Design\Mascot::validate( array( 'kind' => 'nope', 'name' => str_repeat( 'a', 41 ) ) );
	mascot_check( isset( $errors['mascot.kind'], $errors['mascot.name'] ), 'Invalid kind and name were accepted.' );
	mascot_check( array() === \OhMyLMS\Design\Mascot::validate( array( 'kind' => 'star', 'name' => 'Nova' ) ), 'A bundled mascot was rejected.' );

	$svg = \OhMyLMS\Design\Mascot::svg( 'robot' );
	mascot_check( false !== strpos( $svg, 'currentColor' ) && false === strpos( $svg, '#1865f2' ), 'The drawing hard-coded a brand colour.' );
	mascot_check( '' === \OhMyLMS\Design\Mascot::svg( 'upload' ), 'An upload has no bundled drawing.' );
	mascot_check( \OhMyLMS\Design\Mascot::visible( $clean, 'profile' ) && ! \OhMyLMS\Design\Mascot::visible( $clean, 'empty' ) && ! \OhMyLMS\Design\Mascot::visible( array( 'kind' => 'none', 'placements' => array( 'profile' => true ) ), 'profile' ), 'Visibility did not follow the placement and kind.' );

	echo $failed ? "Mascot checks failed.\n" : "OK: {$checks} mascot checks passed.\n";
	exit( $failed ? 1 : 0 );
}
