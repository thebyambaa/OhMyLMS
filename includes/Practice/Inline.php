<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Inline question checks inside lesson content.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\DeliveryPolicy;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Extensions\QuestionTypes;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Inline question checks inside lesson content.
 *
 * Authoring syntax is independent of the server boundary: [ohmylms_question uuid="..."]
 * or the 'question-check' activity (shortcode/block) both resolve the question's approved
 * version on the server and render learner-safe markup with a signed, per-render token.
 * Answers are graded by the server and recorded as practice evidence. Inline checks
 * never affect lesson completion.
 */
final class Inline {
	/**
	 * Register for Inline.
	 */
	public static function register() {
		add_shortcode( 'ohmylms_question', array( __CLASS__, 'shortcode' ) );
		ohmylms_register_activity(
			'question-check',
			array(
				'label'  => __( 'Question check', 'ohmylms' ),
				'render' => static function ( $data ) {
					echo self::render( (string) ( $data['uuid'] ?? '' ) );
				},
			)
		);
	}

	/**
	 * Shortcode for Inline.
	 *
	 * @param mixed $attributes Attributes.
	 */
	public static function shortcode( $attributes ) {
		// Rich editors may turn quotes into curly quotes or drop them, so find the UUID in any attribute.
		$raw  = implode( ' ', array_map( 'strval', (array) $attributes ) );
		$uuid = preg_match( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $raw, $match ) ? strtolower( $match[0] ) : '';
		return self::render( $uuid );
	}

	/**
	 * Turn inline-check shortcodes that stand alone in a lesson into question blocks.
	 *
	 * A check written as shortcode text, alone in a Shortcode block or a paragraph, becomes an
	 * OhMyLMS Question block that references the same question. Shortcodes inside a sentence and
	 * unknown questions are left as they are; the shortcode keeps working on the page either way.
	 *
	 * @param string        $content Block-editor content.
	 * @param callable|null $lookup UUID to question ID; defaults to the question bank.
	 * @return string Content with standalone checks as blocks.
	 */
	public static function blocks_from_shortcodes( $content, $lookup = null ) {
		$content = (string) $content;
		if ( false === strpos( $content, '[ohmylms_question' ) ) {
			return $content;
		}
		$lookup  = $lookup ? $lookup : array( VersionPublisher::class, 'question_by_uuid' );
		$uuid_re = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
		$convert = static function ( $matches ) use ( $lookup ) {
			$uuid = strtolower( $matches['uuid'] );
			$id   = (int) call_user_func( $lookup, $uuid );
			if ( $id < 1 ) {
				return $matches[0];
			}
			return '<!-- wp:ohmylms/shared-multiple-choice ' . wp_json_encode(
				array(
					'questionId' => $id,
					'uuid'       => $uuid,
				)
			) . ' /-->';
		};
		$check   = '\[ohmylms_question[^\]]*?(?P<uuid>' . $uuid_re . ')[^\]]*\]';
		$content = preg_replace_callback( '#<!-- wp:shortcode -->\s*' . $check . '\s*<!-- /wp:shortcode -->#i', $convert, $content );
		return preg_replace_callback( '#<!-- wp:paragraph -->\s*<p>\s*' . $check . '\s*</p>\s*<!-- /wp:paragraph -->#i', $convert, $content );
	}

	/** Version shown inline: the approved one; editors may preview an unapproved current version.
	 *
	 * @param mixed $uuid Uuid.
	 * @param mixed $preview Preview.
	 */
	private static function version_for( $uuid, &$preview ) {
		$preview     = false;
		$question_id = VersionPublisher::question_by_uuid( $uuid );
		return $question_id ? DeliveryPolicy::snapshot( $question_id, DeliveryPolicy::INLINE, $preview ) : null;
	}

	/**
	 * Render for Inline.
	 *
	 * @param mixed $uuid Uuid.
	 */
	public static function render( $uuid ) {
		if ( ! Schema::ready() || ! \OhMyLMS\Assessment\Engine::practice() || ! preg_match( '/^[0-9a-f-]{36}$/', $uuid ) ) {
			return ''; }
		$snapshot = self::version_for( $uuid, $preview );
		if ( ! $snapshot ) {
			return current_user_can( 'edit_posts' ) ? '<p class="ohmylms-inline-check-missing">' . esc_html__( 'This question check is unavailable: the question is missing, archived, exam-only or not approved yet.', 'ohmylms' ) . '</p>' : '';
		}
		$definition = \OhMyLMS\Extensions\Registry::get( 'question', $snapshot->get_type() );
		if ( ! $definition || ! empty( $definition['manual'] ) ) {
			return ''; }
		$lesson_id = (int) get_the_ID();
		$token     = self::sign( $snapshot->get_version_id(), $lesson_id );
		$snapshot  = $snapshot->instantiate( self::seed( $token ) );
		$scope     = self::scope( $token );
		$ids       = array_map(
			static function ( $option ) {
				return (int) $option['id'];
			},
			$snapshot->get_questions()
		);
		$order     = $ids;
		if ( in_array( $snapshot->get_type(), array( 'single-choice', 'multiple-choice', 'reorder', 'matching' ), true ) ) {
			shuffle( $order ); }
		$definitions = $ids;
		shuffle( $definitions );
		$view = $snapshot->student_view( $order, AttemptItems::tokens( $scope, $ids ), array( 'definitions' => $definitions ), AttemptItems::tokens( $scope, $ids, 'd' ), $scope );
		self::enqueue();
		ob_start();
		?>
		<form class="ohmylms-inline-check" data-token="<?php echo esc_attr( $token ); ?>" data-question="<?php echo esc_attr( $view['id'] ); ?>" data-uuid="<?php echo esc_attr( $uuid ); ?>" novalidate>
			<?php
			if ( $preview ) {
				?>
				<p class="ohmylms-inline-check-preview"><?php esc_html_e( 'Preview — this version is not approved, so answers are not recorded for learners.', 'ohmylms' ); ?></p><?php } ?>
			<p class="ohmylms-inline-check-question"><?php echo \OhMyLMS\Assessment\InlineBlanks::render( $view, array( 'id' => 0 ) ); ?></p>
			<?php
			if ( '' !== $view['description'] ) {
				?>
				<div class="ohmylms-inline-check-body"><?php echo apply_filters( 'the_content', $view['description'] ); ?></div><?php } ?>
			<?php
			if ( $view['image_src'] ) {
				?>
				<img class="question-image" src="<?php echo esc_url( $view['image_src'] ); ?>" alt=""><?php } ?>
			<?php QuestionTypes::render( $view, array( 'id' => 0 ) ); ?>
			<button type="submit" class="ohmylms-button ohmylms-inline-check-submit"><?php esc_html_e( 'Check answer', 'ohmylms' ); ?></button>
			<div class="ohmylms-inline-check-feedback" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/** Signed public token: version.lesson.nonce.signature (per render).
	 *
	 * @param mixed $version_id Version identifier.
	 * @param mixed $lesson_id Lesson identifier.
	 */
	public static function sign( $version_id, $lesson_id ) {
		$payload = (int) $version_id . '.' . (int) $lesson_id . '.' . wp_generate_password( 12, false, false );
		return $payload . '.' . substr( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Verify for Inline.
	 *
	 * @return array{version_id:int,lesson_id:int}|null
	 * @param mixed $token Token.
	 */
	public static function verify( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^(\d+)\.(\d+)\.([A-Za-z0-9]{12})\.([0-9a-f]{32})$/', $token, $match ) ) {
			return null; }
		$payload = $match[1] . '.' . $match[2] . '.' . $match[3];
		if ( ! hash_equals( substr( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), 0, 32 ), $match[4] ) ) {
			return null; }
		return array(
			'version_id' => (int) $match[1],
			'lesson_id'  => (int) $match[2],
			'nonce'      => $match[3],
		);
	}

	/** Numbers of a template question are fixed by the render's own token, so grading sees what was shown.
	 *
	 * @param mixed $token Token.
	 */
	public static function seed( $token ) {
		$claims = self::verify( $token );
		return $claims ? \OhMyLMS\Assessment\Template::seed_from( $claims['nonce'] ) : 0;
	}

	/**
	 * Scope for Inline.
	 *
	 * @param mixed $token Token.
	 */
	public static function scope( $token ) {
		return 'i' . substr( hash( 'sha256', (string) $token ), 0, 16 );
	}

	/**
	 * Grade an inline answer for a learner or guest. Every answer is kept; only the first
	 * answer to a question counts as first-try evidence.
	 *
	 * @param mixed $token Token.
	 * @param mixed $response Response data.
	 * @param array $owner Owner.
	 */
	public static function answer( $token, $response, array $owner ) {
		global $wpdb;
		$claims = self::verify( $token );
		if ( ! $claims ) {
			return new \WP_Error( 'ohmylms_inline_token', __( 'This question check has expired. Reload the lesson.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$version  = VersionPublisher::version( $claims['version_id'] );
		$identity = $version ? VersionPublisher::identity( (int) $version['question_id'], false ) : null;
		if ( ! $version || ! $identity || (int) $identity['secure'] ) {
			return new \WP_Error( 'ohmylms_inline_unavailable', __( 'Question unavailable.', 'ohmylms' ), array( 'status' => 410 ) ); }
		$approved = (int) $identity['approved_version_id'] === (int) $version['id'];
		if ( ! $approved ) {
			// Unapproved previews are graded for the editor but never recorded.
			if ( ! current_user_can( 'edit_post', (int) $version['question_id'] ) ) {
				return new \WP_Error( 'ohmylms_inline_unavailable', __( 'Question unavailable.', 'ohmylms' ), array( 'status' => 410 ) ); }
			$seed     = self::seed( $token );
			$snapshot = VersionPublisher::snapshot( (int) $version['id'] )->instantiate( $seed );
			$scope    = self::scope( $token );
			$item     = array(
				'id'         => 0,
				'version_id' => (int) $version['id'],
				'display'    => array( 'instance_seed' => $seed ),
			);
			$grade    = \OhMyLMS\Assessment\Grader::grade( $snapshot, AttemptItems::untokenize( $scope, $item, is_array( $response ) ? $response : array( $response ) ) );
			return is_wp_error( $grade ) ? $grade : array(
				'correct'  => $grade['correct'],
				'fraction' => $grade['fraction'],
				'preview'  => true,
				'feedback' => array(),
			);
		}
		if ( ! $claims['lesson_id'] || get_post_status( $claims['lesson_id'] ) !== 'publish' ) {
			return new \WP_Error( 'ohmylms_inline_unavailable', __( 'Question unavailable.', 'ohmylms' ), array( 'status' => 410 ) ); }
		$session  = self::session( $owner, $claims['lesson_id'], (int) $version['id'] );
		$position = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM %i WHERE session_id=%d', Schema::table( 'practice_items' ), (int) $session['id'] ) );
		$item_id  = Sessions::add_item( (int) $session['id'], (int) $version['id'], $position, self::seed( $token ) );
		$item     = null;
		foreach ( Sessions::items( $session['id'] ) as $candidate ) {
			if ( (int) $candidate['id'] === $item_id ) {
				$item = $candidate; }
		}
		return Sessions::grade_item( $session, $item, $response, 'inline', self::scope( $token ) );
	}

	/** One inline session per owner and lesson.
	 *
	 * @param array $owner Owner.
	 * @param mixed $lesson_id Lesson identifier.
	 * @param mixed $version_id Version identifier.
	 */
	private static function session( array $owner, $lesson_id, $version_id ) {
		global $wpdb;
		$table  = Schema::table( 'practice_sessions' );
		$column = ! empty( $owner['student_id'] ) ? 'student_id' : 'guest_id';
		$value  = (int) ( $owner['student_id'] ?? $owner['guest_id'] );
		$sql    = 'guest_id' === $column
			? "SELECT uuid FROM $table WHERE mode='inline' AND lesson_id=%d AND guest_id=%d AND student_id=0 ORDER BY id DESC LIMIT 1"
			: "SELECT uuid FROM $table WHERE mode='inline' AND lesson_id=%d AND student_id=%d ORDER BY id DESC LIMIT 1";
		$uuid   = $wpdb->get_var( $wpdb->prepare( $sql, (int) $lesson_id, $value ) );
		if ( ! $uuid ) {
			$mapping = SkillMap::for_version( $version_id );
			$uuid    = wp_generate_uuid4();
			$wpdb->insert(
				$table,
				array(
					'uuid'       => $uuid,
					'student_id' => (int) ( $owner['student_id'] ?? 0 ),
					'guest_id'   => (int) ( $owner['guest_id'] ?? 0 ),
					'mode'       => 'inline',
					'term_id'    => (int) ( $mapping[0]['term_id'] ?? 0 ),
					'lesson_id'  => (int) $lesson_id,
					'course_id'  => (int) ohmylms_get_course_by_content_id( $lesson_id ),
					'policy'     => '{}',
					'status'     => 'active',
					'item_limit' => 0,
					'started_at' => current_time( 'mysql', true ),
				)
			);
		}
		return Sessions::get( $uuid );
	}

	/**
	 * Enqueue for Inline.
	 */
	private static function enqueue() {
		if ( wp_script_is( 'ohmylms-inline-check', 'enqueued' ) ) {
			return; }
		wp_enqueue_script( 'ohmylms-inline-check', plugins_url( 'assets/js/inline-check.js', OHMYLMS_FILE ), array(), OHMYLMS_VERSION, true );
		wp_localize_script( 'ohmylms-inline-check', 'ohmylmsInlineCheck', self::client_config() );
		wp_enqueue_style( 'ohmylms-quiz-a11y', plugins_url( 'assets/css/quiz-a11y.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
		wp_enqueue_style( 'ohmylms-practice', plugins_url( 'assets/css/practice.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
	}

	/**
	 * Client config for Inline.
	 */
	public static function client_config() {
		return array(
			'root'        => esc_url_raw( rest_url( 'ohmylms/v1/' ) ),
			'nonce'       => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'loggedIn'    => is_user_logged_in(),
			'loginUrl'    => wp_login_url( get_permalink() ?: home_url( '/' ) ),
			'registerUrl' => get_option( 'users_can_register' ) ? wp_registration_url() : '',
			'i18n'        => array(
				'correct'     => __( 'Correct!', 'ohmylms' ),
				'incorrect'   => __( 'Not quite.', 'ohmylms' ),
				'answer'      => __( 'Correct answer:', 'ohmylms' ),
				'error'       => __( 'Could not check your answer. Please try again.', 'ohmylms' ),
				'save'        => __( 'Create an account or log in to keep your progress.', 'ohmylms' ),
				'login'       => __( 'Log in', 'ohmylms' ),
				'register'    => __( 'Create account', 'ohmylms' ),
				/* translators: %d: number of saved practice answers */
				'claim'       => __( 'Save %d practice answers from this device to your account?', 'ohmylms' ),
				'claimYes'    => __( 'Save to my account', 'ohmylms' ),
				'claimNo'     => __( 'Not now', 'ohmylms' ),
				'claimed'     => __( 'Your practice answers were saved to your account.', 'ohmylms' ),
				'claimFailed' => __( 'These answers could not be saved to this account.', 'ohmylms' ),
				'storage'     => __( 'Your browser is not saving local progress, so answers from this visit cannot be saved later.', 'ohmylms' ),
			),
		);
	}
}
