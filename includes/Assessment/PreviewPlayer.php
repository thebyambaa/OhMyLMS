<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The shipped quiz player, with an author-only, non-recording session.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/** The shipped quiz player, with an author-only, non-recording session. */
final class PreviewPlayer {
	/**
	 * Init for Preview player.
	 */
	public static function init() {
		add_filter(
			'show_admin_bar',
			static function ( $show ) {
				return isset( $_GET['ohmylms_quiz_preview'] ) ? false : $show;
			}
		);
		add_action( 'template_redirect', array( __CLASS__, 'render' ), 1 );
		add_action(
			'wp_enqueue_scripts',
			static function () {
				if ( ! isset( $_GET['ohmylms_quiz_preview'] ) ) {
					return;
				}
				wp_enqueue_style( 'ohmylms-frontend' );
				wp_enqueue_script( 'ohmylms-frontend' );
				MathLive::enqueue();
			},
			20
		);
	}
	/**
	 * Build an authenticated preview link with an optional origin editor.
	 *
	 * @param int    $id Quiz ID.
	 * @param string $editor Origin editor identifier.
	 * @return string Preview URL.
	 */
	public static function url( $id, $editor = '' ) {
		return add_query_arg(
			array(
				'ohmylms_quiz_preview' => (int) $id,
				'_wpnonce'             => wp_create_nonce( 'ohmylms_quiz_preview_' . (int) $id ),
				'quiz_editor'          => 'gutenberg' === $editor ? 'gutenberg' : false,
			),
			home_url( '/' )
		);
	}
	/**
	 * Return to the editor that opened this preview, using local routes only.
	 *
	 * @param int    $id Quiz ID.
	 * @param string $editor Origin editor identifier.
	 * @return string Editor URL.
	 */
	public static function editor_url( $id, $editor = '' ) {
		if ( 'gutenberg' === $editor ) {
			return (string) get_edit_post_link( $id, 'raw' );
		}
		return \OhMyLMS\Admin\LegacyRoutes::url( '/quiz-edit/' . absint( $id ) );
	}
	/**
	 * Render for Preview player.
	 */
	public static function render() {
		if ( ! isset( $_GET['ohmylms_quiz_preview'] ) ) {
			return;
		}
		$id = absint( $_GET['ohmylms_quiz_preview'] );
		if ( get_post_type( $id ) !== OHMYLMS_QUIZ_CPT || ! AccessPolicy::can_edit_quiz( $id ) || ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'ohmylms_quiz_preview_' . $id ) ) {
			wp_die( esc_html__( 'You cannot preview this quiz.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		nocache_headers();
		$quiz    = ohmylms_get_quiz( $id );
		$session = sanitize_key( wp_unslash( is_string( $_POST['preview_session'] ?? null ) ? $_POST['preview_session'] : '' ) );
		$saved   = $session ? get_transient( 'ohmylms_quiz_preview_' . $session ) : false;
		if ( $session && ( ! $saved || $saved['user'] !== get_current_user_id() || $saved['quiz'] !== $id ) ) {
			wp_die( esc_html__( 'Preview expired. Open Preview again from the editor.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		if ( ! $saved ) {
			$snapshots = array();
			foreach ( $quiz->get_questions() as $question ) {
				$content = ! empty( $question['pinned_version_id'] ) && Schema::ready() ? VersionPublisher::version( $question['pinned_version_id'] ) : null;
				if ( $content && (int) $content['question_id'] !== (int) $question['id'] ) {
					$content = null;
				}
				if ( ! $content ) {
					$content = VersionPublisher::content( $question['id'] );
				}
				if ( $content ) {
					$snapshots[] = $content + array(
						'question_id'   => (int) $question['id'],
						'id'            => 0,
						'version_no'    => 0,
						'question_uuid' => '',
					);
				}
			}
			$session = strtolower( wp_generate_password( 32, false, false ) );
			$saved   = array(
				'user'      => get_current_user_id(),
				'quiz'      => $id,
				'editor'    => isset( $_GET['quiz_editor'] ) && is_string( $_GET['quiz_editor'] ) && 'gutenberg' === sanitize_key( wp_unslash( $_GET['quiz_editor'] ) ) ? 'gutenberg' : '',
				'snapshots' => $snapshots,
				'settings'  => array_merge( (array) $quiz->get_settings(), array( 'paper_content' => \OhMyLMS\Quiz\PaperPresentation::content( $id ) ) ),
			);
			set_transient( 'ohmylms_quiz_preview_' . $session, $saved, HOUR_IN_SECONDS );
		}
		$result = null;
		$items  = array();
		if ( ( $_POST['action'] ?? '' ) === 'ohmylms-quiz-preview-exit' ) {
			wp_safe_redirect( self::editor_url( $id, $saved['editor'] ?? '' ) );
			exit;
		}
		if ( ( $_POST['action'] ?? '' ) === 'ohmylms-quiz-preview-submit' ) {
			$result  = array(
				'earned'  => 0,
				'total'   => 0,
				'pending' => 0,
				'errors'  => array(),
			);
			$answers = wp_unslash( $_POST['attempt'][0]['quiz_question'] ?? array() );
			if ( ! is_array( $answers ) ) {
				$answers = array();
			}
			foreach ( $saved['snapshots'] as $data ) {
				// A template question keeps the same numbers for the whole preview session.
				$snapshot = QuestionSnapshot::from_row( $data )->instantiate( Template::seed_from( $session . ':' . (int) ( $data['question_id'] ?? 0 ) ) );
				$response = $answers[ $snapshot->get_id() ] ?? array();
				if ( 'structured' === $snapshot->get_type() ) {
					$response = Structured::map_response( $snapshot, $response, 'preview:' . $session, true );
				}
				$grade            = Grader::grade( $snapshot, $response );
				$marks            = max( 0, (float) ( $snapshot->get_settings()['score']['value'] ?? 1 ) );
				$result['total'] += $marks;
				if ( is_wp_error( $grade ) ) {
					$result['errors'][] = $grade->get_error_message();
					continue; }
				$result['earned']  += $marks * $grade['fraction'];
				$result['pending'] += (int) $grade['pending'];
				$items[]            = array(
					'snapshot' => $snapshot,
					'given'    => (array) $response,
					'grade'    => $grade,
					'marks'    => $marks,
				);
			}
		}
		if ( $result ) {
			// Editors always see the answers; the note says when learners will not.
			$result['entries'] = \OhMyLMS\Quiz\AnswerReview::preview( $items );
			$result['summary'] = \OhMyLMS\Quiz\AnswerReview::summarize( $result['entries'], array_sum( array_column( $result['entries'], 'awarded' ) ), $result['total'], (float) $quiz->get_passing_grade(), $result['pending'] ? 'in-review' : 'completed' );
			$result['hidden']  = ! \OhMyLMS\Quiz\AnswerReview::enabled( (array) $saved['settings'] );
		}
		$questions = array();
		foreach ( $saved['snapshots'] as $data ) {
			$snapshot = QuestionSnapshot::from_row( $data )->instantiate( Template::seed_from( $session . ':' . (int) ( $data['question_id'] ?? 0 ) ) );
			$ids      = array_column( $snapshot->get_questions(), 'id' );
			if ( ! empty( $snapshot->get_settings()['randomize'] ) ) {
				shuffle( $ids );
			}
			$definitions = $ids;
			shuffle( $definitions );
			$questions[] = $snapshot->student_view( $ids, array(), array( 'definitions' => $definitions ), array(), 'preview:' . $session );
		}
		$GLOBALS['post'] = get_post( $id );
		setup_postdata( $GLOBALS['post'] );
		$preview = array(
			'questions' => $questions,
			'settings'  => $saved['settings'],
			'session'   => $session,
			'result'    => $result,
		);
		show_admin_bar( false );
		?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html( $quiz->get_name() . ' — Preview' ); ?></title><?php wp_head(); ?></head><body <?php body_class( 'ohmylms-page ohmylms-default-quiz-preview' ); ?>>
		<?php
		ohmylms_get_template( 'single-lesson/quiz-form.php', array( 'ohmylms_player_preview' => $preview ) );
		wp_footer();
		?>
		</body></html>
		<?php
		exit;
	}
}
