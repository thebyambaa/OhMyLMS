<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bank question player placement and authoring endpoints.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Blocks;

use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Practice\QuestionPlayer;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** A saved placement is the authority for selection, grading and skill participation. */
final class QuestionPlayerBlock {
	/** Register the block and its REST endpoints. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'prevent_cache' ), 0 );
	}

	/** Register built authoring assets and the native frontend module. */
	public static function register() {
		$asset_file = OHMYLMS_DIR . '/assets/dist/admin/question-player-block.asset.php';
		if ( ! is_file( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_register_script( 'ohmylms-question-player-block', plugins_url( 'assets/dist/admin/question-player-block.js', OHMYLMS_FILE ), $asset['dependencies'], $asset['version'], true );
		$player_version = max( filemtime( OHMYLMS_DIR . '/assets/interactivity/question-player.js' ), filemtime( OHMYLMS_DIR . '/assets/interactivity/game-player.mjs' ), filemtime( OHMYLMS_DIR . '/assets/interactivity/arcade-player.mjs' ) );
		wp_register_script_module( 'ohmylms/question-player', plugins_url( 'assets/interactivity/question-player.js', OHMYLMS_FILE ), array( 'ohmylms/interactivity', 'ohmylms/questions' ), (string) $player_version );
		register_block_type( OHMYLMS_DIR . '/assets/src/blocks/question-player', array( 'render_callback' => array( __CLASS__, 'render' ) ) );
	}

	/** Prevent personalized launches from being served from a page cache. */
	public static function prevent_cache() {
		if ( has_block( 'ohmylms/question-player' ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
		}
	}

	/**
	 * Bound and normalize reference-only attributes.
	 *
	 * @param array $attributes Saved attributes.
	 * @return array Canonical player configuration.
	 */
	public static function settings( array $attributes ) {
		$ids    = static function ( $values ) {
			return array_slice( array_values( array_unique( array_filter( array_map( 'absint', is_array( $values ) ? $values : array() ) ) ) ), 0, 30 );
		};
		$mode   = in_array( $attributes['activityMode'] ?? '', array_merge( array_keys( \OhMyLMS\Practice\ActivityModes::MODES ), array( 'flashcards', 'memory-pairs', 'hangman', 'spell-word', 'memory-recall', 'image-quiz' ) ), true ) ? $attributes['activityMode'] : 'standard';
		$graded = 'standard' === $mode && ( ! isset( $attributes['graded'] ) || true === $attributes['graded'] );
		$result = array(
			'source'        => 'random' === ( $attributes['source'] ?? '' ) ? 'random' : 'selected',
			'questionIds'   => $ids( $attributes['questionIds'] ?? array() ),
			'skillIds'      => $ids( $attributes['skillIds'] ?? array() ),
			'count'         => max( 1, min( 30, (int) ( $attributes['count'] ?? 5 ) ) ),
			'graded'        => $graded,
			'affectsSkills' => $graded && true === ( $attributes['affectsSkills'] ?? false ),
		);
		if ( 'standard' !== $mode ) {
			$result['activityMode'] = $mode;
		}
		if ( 'memory-recall' === $mode ) {
			$result['rememberCount'] = max( 1, min( 10, (int) ( $attributes['rememberCount'] ?? 2 ) ) );
			$result['studySeconds']  = max( 5, min( 120, (int) ( $attributes['studySeconds'] ?? 10 ) ) );
		}
		if ( \OhMyLMS\Practice\ActivityModes::family( $mode ) ) {
			$result['roundSeconds'] = max( 10, min( 180, (int) ( $attributes['roundSeconds'] ?? 30 ) ) );
		}
		if ( 'image-quiz' === $mode ) {
			$result['revealSeconds'] = max( 10, min( 120, (int) ( $attributes['revealSeconds'] ?? 30 ) ) );
		}
		return $result;
	}

	/**
	 * Match a saved configuration without trusting launch parameters from the client.
	 *
	 * @param array $settings Canonical saved configuration.
	 * @return string Configuration identifier.
	 */
	public static function key( array $settings ) {
		return substr( hash( 'sha256', wp_json_encode( $settings ) ), 0, 32 );
	}

	/**
	 * Locate a configuration among nested blocks of this post.
	 *
	 * @param array  $blocks Saved blocks.
	 * @param string $key Configuration identifier.
	 * @return array|null Saved settings.
	 */
	private static function find( array $blocks, $key ) {
		foreach ( $blocks as $block ) {
			if ( 'ohmylms/question-player' === $block['blockName'] ) {
				$settings = self::settings( $block['attrs'] ?? array() );
				if ( hash_equals( self::key( $settings ), $key ) ) {
					return $settings;
				}
			}
			$nested = self::find( $block['innerBlocks'] ?? array(), $key );
			if ( null !== $nested ) {
				return $nested;
			}
		}
		return null;
	}

	/**
	 * Resolve the saved placement and recheck its parent content access.
	 *
	 * @param int    $post_id Parent post ID.
	 * @param string $key Configuration identifier.
	 * @return array|\WP_Error Authorized placement.
	 */
	public static function placement( $post_id, $key ) {
		$post = get_post( $post_id );
		if ( ! $post || post_password_required( $post ) || ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) ) {
			return QuestionPlayer::error( __( 'This question player is unavailable.', 'ohmylms' ), 403 );
		}
		$settings = self::find( parse_blocks( $post->post_content ), $key );
		if ( null === $settings ) {
			return QuestionPlayer::error( __( 'Save the question player before playing it.', 'ohmylms' ), 404 );
		}
		$preview   = current_user_can( 'edit_post', $post_id );
		$course_id = (int) ohmylms_get_course_by_content_id( $post_id );
		if ( $course_id && ! $preview && ! \OhMyLMS\Learning\CourseProgram::enrollment( get_current_user_id(), $course_id ) ) {
			return QuestionPlayer::error( __( 'Enroll in this course to play these questions.', 'ohmylms' ), 403 );
		}
		return array(
			'post'     => $post,
			'settings' => $settings,
			'key'      => $key,
			'preview'  => $preview,
		);
	}

	/**
	 * Render the launch surface without starting a session or revealing private keys.
	 *
	 * @param array $attributes Saved attributes.
	 * @return string Player shell.
	 */
	public static function render( $attributes ) {
		if ( ! Schema::ready() || ! \OhMyLMS\Assessment\Engine::practice() ) {
			return '<p>' . esc_html__( 'Question players are unavailable.', 'ohmylms' ) . '</p>';
		}
		$settings = self::settings( $attributes );
		$post_id  = (int) get_the_ID();
		if ( ! is_user_logged_in() ) {
			return '<p><a href="' . esc_url( wp_login_url( get_permalink( $post_id ) ) ) . '">' . esc_html__( 'Log in to play these questions.', 'ohmylms' ) . '</a></p>';
		}
		Interactive::enqueue();
		\OhMyLMS\Extensions\Interactivity::enqueue( 'ohmylms/question-player' );
		\OhMyLMS\Extensions\Interactivity::enqueue( 'ohmylms/questions' );
		wp_enqueue_style( 'ohmylms-question-player', plugins_url( 'assets/css/question-player.css', OHMYLMS_FILE ), array( 'ohmylms-interactive' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/question-player.css' ) );
		wp_enqueue_style( 'ohmylms-game-player', plugins_url( 'assets/css/game-player.css', OHMYLMS_FILE ), array( 'ohmylms-question-player' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/game-player.css' ) );
		$config = array(
			'endpoint' => rest_url( 'ohmylms/v1/question-player/' . $post_id . '/' . self::key( $settings ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'storage'  => 'ohmylms-question-player-' . get_current_user_id() . '-' . $post_id . '-' . self::key( $settings ),
		);
		ob_start();
		?>
		<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress returns escaped block wrapper attributes. ?>
		<section <?php echo get_block_wrapper_attributes( array( 'class' => 'ohmylms-question-player' ) ); ?> data-player-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<h2><?php esc_html_e( 'Question player', 'ohmylms' ); ?></h2>
			<p><?php echo $settings['graded'] ? esc_html__( 'Graded questions', 'ohmylms' ) : esc_html__( 'Ungraded activity', 'ohmylms' ); ?> · <?php echo $settings['affectsSkills'] ? esc_html__( 'Counts toward skill performance', 'ohmylms' ) : esc_html__( 'Does not affect skill performance', 'ohmylms' ); ?></p>
			<p class="ohmylms-question-player-error" role="alert" hidden></p>
			<div class="ohmylms-question-player-stage"></div>
			<button type="button" class="ohmylms-question-player-start"><?php esc_html_e( 'Start questions', 'ohmylms' ); ?></button>
			<noscript><p><?php esc_html_e( 'Enable JavaScript to play these questions.', 'ohmylms' ); ?></p></noscript>
		</section>
		<?php
		return ob_get_clean();
	}

	/** Register author-only metadata search and authenticated player operations. */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/question-player/choices',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'choices' ),
				'permission_callback' => static function () {
					return AccessPolicy::can_author(); },
				'args'                => array(
					'kind'    => array(
						'type'    => 'string',
						'enum'    => array( 'questions', 'skills' ),
						'default' => 'questions',
					),
					'search'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'include' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/question-player/(?P<post_id>\d+)/(?P<key>[a-f0-9]{32})',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'operate' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'operation' => array(
						'type'     => 'string',
						'enum'     => array( 'start', 'resume', 'submit', 'study' ),
						'required' => true,
					),
					'session'   => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Read metadata without exposing answers or starting a learner session.
	 *
	 * @param \WP_REST_Request $request Author search request.
	 * @return array|\WP_Error Metadata choices.
	 */
	public static function choices( $request ) {
		if ( ! Schema::ready() ) {
			return QuestionPlayer::error( __( 'The question bank is unavailable.', 'ohmylms' ), 503 );
		}
		$include = array_slice( wp_parse_id_list( $request['include'] ), 0, 30 );
		$result  = array();
		if ( 'skills' === $request['kind'] ) {
			$terms = get_terms(
				array(
					'taxonomy'   => Taxonomy::NAME,
					'hide_empty' => false,
					'number'     => 30,
					'search'     => $request['search'],
					'include'    => $include,
				)
			);
			if ( is_wp_error( $terms ) ) {
				return $terms;
			}
			foreach ( $terms as $term ) {
				$result[] = array(
					'id'    => (int) $term->term_id,
					'title' => $term->name,
				);
			}
			return $result;
		}
		$posts = get_posts(
			array(
				'post_type'   => OHMYLMS_QUESTION_CPT,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => $include ? 30 : 100,
				's'           => $request['search'],
				'post__in'    => $include,
				'orderby'     => 'modified',
				'order'       => 'DESC',
			)
		);
		foreach ( $posts as $post ) {
			$snapshot = QuestionPlayer::eligible( $post->ID, get_current_user_id() );
			if ( $snapshot && ( ! \OhMyLMS\Practice\ActivityModes::family( $request['activityMode'] ?? '' ) || \OhMyLMS\Practice\ActivityModes::compatible( $request['activityMode'], $snapshot ) ) && ( 'image-quiz' !== $request['activityMode'] || \OhMyLMS\Practice\ImageActivity::compatible( $snapshot ) ) && ( ! in_array( $request['activityMode'], array( 'flashcards', 'memory-pairs', 'memory-recall' ), true ) || 'matching' === $snapshot->get_type() ) && ( ! in_array( $request['activityMode'], array( 'hangman', 'spell-word' ), true ) || \OhMyLMS\Practice\WordActivity::answers( $snapshot ) ) ) {
				$result[] = array(
					'id'    => (int) $post->ID,
					'title' => get_the_title( $post ),
				);
			}
		}
		return $result;
	}

	/**
	 * Resolve every operation from saved settings; client flags are never authoritative.
	 *
	 * @param \WP_REST_Request $request Learner request.
	 * @return array|\WP_Error Player state.
	 */
	public static function operate( $request ) {
		if ( ! Schema::ready() || ! \OhMyLMS\Assessment\Engine::practice() ) {
			return QuestionPlayer::error( __( 'Question players are unavailable.', 'ohmylms' ), 503 );
		}
		$placement = self::placement( (int) $request['post_id'], (string) $request['key'] );
		if ( is_wp_error( $placement ) ) {
			return $placement;
		}
		if ( 'start' === $request['operation'] ) {
			return QuestionPlayer::start( $placement );
		}
		return QuestionPlayer::resume(
			$placement,
			(string) $request['session'],
			'submit' === $request['operation'] ? $request['attempt'] ?? array() : null,
			'study' === $request['operation'] ? array(
				'card'     => $request['card'],
				'value'    => $request['value'] ?? '',
				'revision' => $request['revision'],
			) : null
		);
	}
}
