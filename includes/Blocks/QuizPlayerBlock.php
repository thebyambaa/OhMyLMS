<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Embed the shared quiz player in lessons and ordinary WordPress content.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Blocks;

use OhMyLMS\Assessment\PreviewPlayer;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\Quiz\Submission;

defined( 'ABSPATH' ) || exit;

/** Native interactive block; the previous player URL remains a compatible form fallback. */
final class QuizPlayerBlock {
	/**
	 * Instance.
	 *
	 * @var mixed
	 */
	private static $instance = 0;
	/**
	 * Inline quiz.
	 *
	 * @var mixed
	 */
	private static $inline_quiz = 0;

	/** Register block, editor metadata endpoint and player navigation. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'document' ), 20 );
		add_filter( 'wp_redirect', array( __CLASS__, 'redirect' ), 10 );
		add_filter( 'ohmylms_quiz_interactivity_context', array( __CLASS__, 'player_context' ) );
		add_action( 'template_redirect', array( __CLASS__, 'prevent_cache' ), 0 );
		add_shortcode( 'ohmylms_quiz', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * [ohmylms_quiz quiz="ID"]: the same player as the block, anywhere shortcodes work. It
	 * renders the registered block itself, so every placement shares one implementation.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string Player markup.
	 */
	public static function shortcode( $attributes ) {
		$attributes = shortcode_atts( array( 'quiz' => 0 ), (array) $attributes, 'ohmylms_quiz' );
		$quiz_id    = absint( $attributes['quiz'] );
		if ( ! $quiz_id ) {
			return '';
		}
		return do_blocks( '<!-- wp:ohmylms/quiz-player ' . wp_json_encode( array( 'quizId' => $quiz_id ) ) . ' /-->' );
	}

	/** Does the page being viewed place the player with [ohmylms_quiz]? */
	private static function has_player_shortcode() {
		$post = get_post();
		return $post instanceof \WP_Post && has_shortcode( $post->post_content, 'ohmylms_quiz' );
	}

	/** Keep personalized quiz pages out of page caches before headers are sent. */
	public static function prevent_cache() {
		if ( has_block( 'ohmylms/quiz-player' ) || self::has_player_shortcode() ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}

	/** Register the built editor entry and server-rendered block. */
	public static function register() {
		$asset_file = OHMYLMS_DIR . '/assets/dist/admin/quiz-player-block.asset.php';
		if ( ! is_file( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_register_script( 'ohmylms-quiz-player-block', plugins_url( 'assets/dist/admin/quiz-player-block.js', OHMYLMS_FILE ), $asset['dependencies'], $asset['version'], true );
		register_block_type( OHMYLMS_DIR . '/assets/src/blocks/quiz-player', array( 'render_callback' => array( __CLASS__, 'render' ) ) );
	}

	/**
	 * Same-origin player URL; it carries no grading data or launch credentials.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return string Player URL.
	 */
	public static function url( $quiz_id ) {
		return add_query_arg( 'ohmylms_quiz_embed', absint( $quiz_id ), home_url( '/' ) );
	}

	/**
	 * Read the navigation-only player ID without accepting array parameters.
	 *
	 * @return int Requested quiz ID.
	 */
	private static function request_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only player navigation; existing form handlers validate mutation nonces.
		$value = isset( $_GET['ohmylms_quiz_embed'] ) ? wp_unslash( $_GET['ohmylms_quiz_embed'] ) : 0;
		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Render a native player only for a published quiz after checking learner access.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Block markup.
	 */
	public static function render( $attributes ) {
		$quiz_id = absint( $attributes['quizId'] ?? 0 );
		if ( OHMYLMS_QUIZ_CPT !== get_post_type( $quiz_id ) || 'publish' !== get_post_status( $quiz_id ) ) {
			return '<p>' . esc_html__( 'This quiz is unavailable.', 'ohmylms' ) . '</p>';
		}
		\OhMyLMS\Extensions\Interactivity::enqueue( 'ohmylms/quiz' );
		\OhMyLMS\Quiz\PlayerTemplates::enqueue( \OhMyLMS\Quiz\PlayerTemplates::DEFAULT_TEMPLATE );
		// Loaded up front: the review appears after a submit refreshes only this region, which cannot add stylesheets.
		wp_enqueue_style( 'ohmylms-answer-review', plugins_url( 'assets/css/answer-review.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
		\OhMyLMS\Assessment\Delivery::require_script();
		wp_enqueue_style( 'ohmylms-quiz-inline', plugins_url( 'assets/css/quiz-player-inline.css', OHMYLMS_FILE ), array( 'ohmylms-player-templates' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/quiz-player-inline.css' ) );
		$context = array_merge(
			self::inline_context( $quiz_id ),
			array(
				'quizId'     => $quiz_id,
				'submitting' => false,
				'error'      => '',
			)
		);
		$region  = 'ohmylms-quiz-player-' . ++self::$instance;
		ob_start();
		echo '<div ' . get_block_wrapper_attributes( array( 'class' => 'ohmylms-page ohmylms-inline-quiz' ) ) . ' data-wp-interactive="ohmylms/quiz" data-wp-router-region="' . esc_attr( $region ) . '" ' . wp_interactivity_data_wp_context( $context ) . '>';
		echo '<p role="alert" data-wp-text="context.error" data-wp-bind--hidden="!context.error" hidden></p>';
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Log in to take this quiz.', 'ohmylms' ) . '</p><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'ohmylms' ) . '</a>';
		} else {
			$access = Submission::access( $quiz_id, get_current_user_id() );
			if ( is_wp_error( $access ) ) {
				echo '<p>' . esc_html( $access->get_error_message() ) . '</p>';
			} else {
				$previous_post     = $GLOBALS['post'] ?? null;
				self::$inline_quiz = $quiz_id;
				try {
					$GLOBALS['post'] = get_post( $quiz_id );
					setup_postdata( $GLOBALS['post'] );
					self::player( $quiz_id, $access );
				} finally {
					self::$inline_quiz = 0;
					$GLOBALS['post']   = $previous_post;
					if ( $previous_post ) {
						setup_postdata( $previous_post );
					}
				}
			}
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Authenticated inline transport configuration, containing no answers.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array Player context.
	 */
	private static function inline_context( $quiz_id ) {
		return array(
			'inlineEndpoint'  => rest_url( 'ohmylms/v1/quiz-player/quizzes/' . $quiz_id . '/attempt' ),
			'restNonce'       => wp_create_nonce( 'wp_rest' ),
			'submissionError' => __( 'Could not update the quiz. Your answers remain here. Please retry.', 'ohmylms' ),
		);
	}

	/** Register a metadata-only picker, restricted to content authors. */
	public static function register_routes() {
		register_rest_route(
			'ohmylms/v1',
			'/quiz-player/quizzes/(?P<id>\d+)/attempt',
			array(
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return is_user_logged_in(); },
				'callback'            => array( __CLASS__, 'mutate' ),
				'args'                => array(
					'id'              => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'operation'       => array(
						'type'     => 'string',
						'enum'     => array( 'start', 'submit', 'exit', 'timeout' ),
						'required' => true,
					),
					'quiz_attempt_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
				),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/quiz-player/quizzes',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function () {
					return AccessPolicy::can_author();
				},
				'callback'            => array( __CLASS__, 'choices' ),
				'args'                => array(
					'search'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'include' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			)
		);
	}

	/**
	 * Delegate inline mutations to the same locked, server-authoritative quiz engine.
	 * WordPress REST cookie authentication validates the X-WP-Nonce header.
	 *
	 * @param \WP_REST_Request $request Authenticated request.
	 * @return \WP_REST_Response|\WP_Error Result without redirects or answer keys.
	 */
	public static function mutate( $request ) {
		$quiz_id = absint( $request['id'] );
		if ( OHMYLMS_QUIZ_CPT !== get_post_type( $quiz_id ) || 'publish' !== get_post_status( $quiz_id ) ) {
			return new \WP_Error( 'quiz_unavailable', __( 'This quiz is unavailable.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$operation = $request['operation'];
		if ( ! in_array( $operation, array( 'start', 'submit', 'exit', 'timeout' ), true ) ) {
			return new \WP_Error( 'quiz_operation', __( 'Invalid quiz operation.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( 'start' === $operation ) {
			$result = Submission::start( $quiz_id, get_current_user_id() );
		} else {
			$attempt_id = absint( $request['quiz_attempt_id'] );
			$attempts   = $request['attempt'] ?? array();
			if ( ! is_array( $attempts ) ) {
				return new \WP_Error( 'quiz_answers', __( 'Invalid answers.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			$answers = $attempts[ $attempt_id ]['quiz_question'] ?? array();
			if ( ! is_array( $answers ) ) {
				return new \WP_Error( 'quiz_answers', __( 'Invalid answers.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			$result = Submission::submit( $quiz_id, $attempt_id, get_current_user_id(), $answers, $operation );
		}
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'saved' => true ) );
	}

	/**
	 * Return quiz titles and author preview links without question content.
	 *
	 * @param \WP_REST_Request $request Picker request.
	 * @return \WP_REST_Response Metadata response.
	 */
	public static function choices( $request ) {
		$args = array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'numberposts' => 20,
			'orderby'     => 'title',
			'order'       => 'ASC',
			's'           => $request['search'] ?? '',
		);
		if ( ! empty( $request['include'] ) ) {
			$args['post__in'] = array( absint( $request['include'] ) );
		}
		$items = array();
		foreach ( get_posts( $args ) as $post ) {
			$can_edit = AccessPolicy::can_edit_quiz( $post->ID );
			if ( 'publish' !== $post->post_status && ! $can_edit ) {
				continue;
			}
			$items[] = array(
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'status'     => $post->post_status,
				'previewUrl' => $can_edit ? PreviewPlayer::url( $post->ID ) : '',
			);
		}
		return rest_ensure_response( $items );
	}

	/**
	 * Keep normal start, submit and exit redirects inside the embedded player.
	 *
	 * @param string $location Existing redirect destination.
	 * @return string Redirect destination.
	 */
	public static function redirect( $location ) {
		$quiz_id = self::request_id();
		return $quiz_id && OHMYLMS_QUIZ_CPT === get_post_type( $quiz_id ) && get_permalink( $quiz_id ) === $location ? self::url( $quiz_id ) : $location;
	}

	/**
	 * Preserve embedded navigation after the shared AJAX timeout submission.
	 *
	 * @param array $context Existing player context.
	 * @return array Player context.
	 */
	public static function player_context( $context ) {
		if ( self::$inline_quiz && self::$inline_quiz === (int) ( $context['quizId'] ?? 0 ) ) {
			return array_merge( $context, self::inline_context( self::$inline_quiz ) );
		}
		$quiz_id = self::request_id();
		if ( $quiz_id && $quiz_id === (int) ( $context['quizId'] ?? 0 ) ) {
			$context['returnUrl'] = self::url( $quiz_id );
		}
		return $context;
	}

	/** Serve a player document after the unchanged form handlers process submissions. */
	public static function document() {
		$quiz_id = self::request_id();
		if ( ! $quiz_id ) {
			return;
		}
		nocache_headers();
		if ( OHMYLMS_QUIZ_CPT !== get_post_type( $quiz_id ) || 'publish' !== get_post_status( $quiz_id ) ) {
			wp_die( esc_html__( 'This quiz is unavailable.', 'ohmylms' ), '', array( 'response' => 404 ) );
		}
		show_admin_bar( false );
		$GLOBALS['post'] = get_post( $quiz_id );
		setup_postdata( $GLOBALS['post'] );
		// Register scripts before rendering the shared template, so head styles and footer modules are available.
		wp_enqueue_scripts();
		remove_action( 'wp_head', 'wp_enqueue_scripts', 1 );
		wp_enqueue_style( 'ohmylms-frontend' );
		wp_enqueue_script( 'ohmylms-frontend' );
		wp_enqueue_style( 'ohmylms-quiz-embed', plugins_url( 'assets/css/quiz-player-embed.css', OHMYLMS_FILE ), array( 'ohmylms-frontend' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/quiz-player-embed.css' ) );
		ob_start();
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Log in to take this quiz.', 'ohmylms' ) . '</p><a class="ohmylms-button" target="_top" href="' . esc_url( wp_login_url( self::url( $quiz_id ) ) ) . '">' . esc_html__( 'Log in', 'ohmylms' ) . '</a>';
		} else {
			$access = Submission::access( $quiz_id, get_current_user_id() );
			if ( is_wp_error( $access ) ) {
				echo '<p>' . esc_html( $access->get_error_message() ) . '</p>';
			} else {
				self::player( $quiz_id, $access );
			}
		}
		$body = ob_get_clean();
		?><!doctype html>
		<html <?php language_attributes(); ?>>
		<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php echo esc_html( get_the_title( $quiz_id ) ); ?></title><?php wp_head(); ?></head>
		<body <?php body_class( 'ohmylms-page ohmylms-quiz-embed' ); ?>>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared quiz template and local status markup escape their fields.
		echo $body;
		wp_footer();
		?>
		</body></html>
		<?php
		exit;
	}

	/**
	 * Render the existing active player or an explicit attempt start form.
	 *
	 * @param int $quiz_id Authorized quiz ID.
	 * @param int $course_id Authorized course ID.
	 */
	private static function player( $quiz_id, $course_id ) {
		$quiz    = ohmylms_get_quiz( $quiz_id );
		$attempt = $quiz->get_quiz_attempt( get_current_user_id() );
		if ( $attempt ) {
			ohmylms_get_template( 'single-lesson/quiz-form.php' );
			return;
		}
		$attempts = $quiz->get_all_quiz_attempts( get_current_user_id(), $course_id );
		usort(
			$attempts,
			static function ( $first, $second ) {
				return (int) $second['quiz_attempt_id'] <=> (int) $first['quiz_attempt_id'];
			}
		);
		$latest    = $attempts[0] ?? null;
		$remaining = $quiz->count_total_attempt( get_current_user_id(), $course_id ) < $quiz->get_take_attempts();
		// The same score and answer review as the lesson quiz page; empty when the quiz keeps its answers private.
		$review = \OhMyLMS\Quiz\AnswerReview::render( $quiz, $attempts );
		?>
		<div class="ohmylms-quiz-embed-start">
			<h2><?php echo esc_html( $quiz->get_name() ); ?></h2>
			<?php if ( $latest ) : ?>
				<p role="status"><?php esc_html_e( 'Your last attempt has been submitted.', 'ohmylms' ); ?></p>
				<?php
				$feedback = ! \OhMyLMS\Assessment\Schema::ready() || \OhMyLMS\Assessment\AssessmentSettings::feedback_visible( $latest['quiz_attempt_id'] );
				if ( $feedback && '' !== $review ) {
					// The review below opens with the score.
				} elseif ( $feedback ) {
					$basis = \OhMyLMS\Assessment\AttemptReport::basis( $latest['quiz_attempt_id'], $quiz );
					// translators: 1: Earned quiz marks, 2: Total available marks.
					echo '<p>' . esc_html( sprintf( __( 'Score: %1$s / %2$s', 'ohmylms' ), \OhMyLMS\Assessment\Scoring::display( $latest['total_achieved_marks'] ?? 0 ), \OhMyLMS\Assessment\Scoring::display( $basis['max'] ) ) ) . '</p>';
				} else {
					echo '<p>' . esc_html__( 'Results have not been released yet.', 'ohmylms' ) . '</p>';
				}
				?>
			<?php endif; ?>
			<?php if ( $remaining ) : ?>
				<?php echo \OhMyLMS\Quiz\Intro::render( $quiz ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the intro. ?>
			<p><?php esc_html_e( 'Start a new quiz attempt. Your results are saved to your account.', 'ohmylms' ); ?></p>
			<form method="post" action="<?php echo esc_url( self::url( $quiz_id ) ); ?>" 
				<?php
				if ( self::$inline_quiz ) {
					echo 'data-wp-on--submit="actions.startInline"'; }
				?>
				>
				<input type="hidden" name="action" value="quiz-action">
				<input type="hidden" name="ohmylms_quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
				<?php wp_nonce_field( 'save_quiz_attempt', 'save-quiz-attempt-nonce' ); ?>
				<button type="submit" class="ohmylms-button" data-wp-bind--disabled="context.submitting"><?php esc_html_e( 'Start quiz', 'ohmylms' ); ?></button>
			</form>
			<?php else : ?>
				<p><?php esc_html_e( 'No attempts remaining.', 'ohmylms' ); ?></p>
			<?php endif; ?>
			<?php if ( ! self::$inline_quiz ) : ?>
			<p><a target="_top" href="<?php echo esc_url( get_permalink( $quiz_id ) ); ?>"><?php esc_html_e( 'View quiz results', 'ohmylms' ); ?></a></p>
			<?php endif; ?>
		<?php echo $review; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the review template. ?>
		</div>
		<?php
	}
}
