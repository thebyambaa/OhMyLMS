<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Separate exam document shell reusing frozen delivery, timing, autosave and grading.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\ExamImport\Documents;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Assessment\AssessmentSettings;

defined( 'ABSPATH' ) || exit;

/** Personalized exam and source documents are never publicly cached. */
final class Player {
	/** Register navigation, embeds and document authorization. */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'document' ), 20 );
		add_filter( 'wp_redirect', array( __CLASS__, 'redirect' ), 15 );
		add_filter( 'ohmylms_quiz_interactivity_context', array( __CLASS__, 'context' ) );
		add_shortcode( 'ohmylms_exam', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Navigation-only URL without credentials or answers.
	 *
	 * @param int $id Exam ID.
	 * @return string URL.
	 */
	public static function url( $id ) {
		return add_query_arg( 'ohmylms_exam', (int) $id, home_url( '/' ) );
	}

	/**
	 * Read scalar navigation IDs, never state-changing values.
	 *
	 * @param string $key Query field.
	 * @return int ID.
	 */
	private static function query_id( $key ) {
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
			return 0;
		}
		return absint( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- POST start verifies a separate nonce.
	}

	/**
	 * Same-origin embed for lessons and ordinary WordPress pages.
	 *
	 * @param array $attributes Shortcode fields.
	 * @return string Escaped player iframe.
	 */
	public static function shortcode( $attributes ) {
		$id = absint( $attributes['exam'] ?? 0 );
		if ( Library::TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		return '<iframe class="ohmylms-exam-embed" src="' . esc_url( self::url( $id ) ) . '" title="' . esc_attr( get_the_title( $id ) ) . '" width="100%" height="850" loading="lazy"></iframe>';
	}

	/**
	 * Keep existing submission and timeout redirects in the separate exam shell.
	 *
	 * @param string $location Redirect target.
	 * @return string Target.
	 */
	public static function redirect( $location ) {
		$id   = self::query_id( 'ohmylms_exam' );
		$data = $id ? get_post_meta( $id, Library::META, true ) : null;
		return is_array( $data ) && get_permalink( $data['quiz_id'] ) === $location ? self::url( $id ) : $location;
	}

	/**
	 * Set the same return destination for the shared deadline script.
	 *
	 * @param array $context Player context.
	 * @return array Context.
	 */
	public static function context( $context ) {
		$id = self::query_id( 'ohmylms_exam' );
		if ( $id ) {
			$context['returnUrl'] = self::url( $id );
		}
		return $context;
	}

	/**
	 * Enroll a signed-in learner only in this exam's managed private course.
	 * Existing revoked enrollment is never silently restored.
	 *
	 * @param int $id Published exam ID.
	 * @return int|\WP_Error Attempt ID.
	 */
	public static function start( $id ) {
		global $wpdb;
		if ( ! is_user_logged_in() || Library::TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return new \WP_Error( 'exam_access', __( 'Log in to start a published exam.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		return Jobs::locked(
			$id,
			static function () use ( $wpdb, $id ) {
				$data    = get_post_meta( $id, Library::META, true );
				$course  = (int) $data['course_id'];
				$student = get_current_user_id();
				if ( ! get_post_meta( $course, '_ohmylms_exam_wrapper', true ) ) {
					return new \WP_Error( 'exam_access', __( 'The exam enrollment is unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
				}
				$table    = $wpdb->prefix . 'ohmylms_user_enrollment';
				$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id,status FROM %i WHERE user_id=%d AND course_id=%d', $table, $student, $course ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live enrollment authorization under the exam lock.
				if ( $existing && 'enrolled' !== $existing['status'] ) {
					return new \WP_Error( 'exam_access', __( 'Your access to this exam has been withdrawn.', 'ohmylms' ), array( 'status' => 403 ) );
				}
				if ( ! $existing ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Create only this exam's managed enrollment under the exam lock.
					if ( ! $wpdb->insert(
						$table,
						array(
							'user_id'    => $student,
							'course_id'  => $course,
							'status'     => 'enrolled',
							'progress'   => 'running',
							'start_date' => current_time( 'mysql' ),
						)
					) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Standalone exam enrollment, scoped to the managed course.
						return new \WP_Error( 'exam_storage', __( 'Could not start the exam. Retry.', 'ohmylms' ), array( 'status' => 500 ) );
					}
					update_user_meta( $student, '_is_ohmylms_student', 'yes' );
				}
				return Submission::start( $data['quiz_id'], $student );
			}
		);
	}

	/**
	 * Check source access using job ownership or an actual exam attempt and release policy.
	 *
	 * @param int  $id Job or exam ID.
	 * @param bool $exam Exam document request.
	 * @param bool $mark_scheme Answer document request.
	 * @return array|\WP_Error Private document metadata.
	 */
	public static function source( $id, $exam, $mark_scheme ) {
		global $wpdb;
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'exam_source', __( 'Source unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$job_id = $id;
		if ( $exam ) {
			$data = get_post_meta( $id, Library::META, true );
			if ( Library::TYPE !== get_post_type( $id ) || ! is_array( $data ) ) {
				return new \WP_Error( 'exam_source', __( 'Source unavailable.', 'ohmylms' ), array( 'status' => 404 ) );
			}
			$job_id    = $data['job_id'];
			$is_author = get_current_user_id() === (int) get_post_field( 'post_author', $id ) && current_user_can( 'edit_post', $id ) && current_user_can( 'upload_files' );
			if ( ! $is_author ) {
				$attempt = $wpdb->get_row( $wpdb->prepare( "SELECT id,status FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE quiz_id=%d AND student_id=%d ORDER BY id DESC LIMIT 1", $data['quiz_id'], get_current_user_id() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Authorize source against actual learner attempt, never cache.
				if ( 'publish' !== get_post_status( $id ) || ! $attempt || is_wp_error( Submission::access( $data['quiz_id'], get_current_user_id() ) ) || ( $mark_scheme && ( 'in-progress' === $attempt['status'] || ! AssessmentSettings::feedback_visible( $attempt['id'] ) ) ) ) {
					return new \WP_Error( 'exam_source', __( 'This document has not been released.', 'ohmylms' ), array( 'status' => 403 ) );
				}
			}
		} else {
			$request       = new \WP_REST_Request();
			$request['id'] = $id;
			if ( ! Jobs::permission( $request ) ) {
				return new \WP_Error( 'exam_source', __( 'Source unavailable.', 'ohmylms' ), array( 'status' => 403 ) );
			}
		}
		$job      = get_post_meta( $job_id, Jobs::META, true );
		$document = $mark_scheme ? ( $job['mark_scheme'] ?? null ) : ( $job['document'] ?? null );
		return is_array( $document ) ? $document : new \WP_Error( 'exam_source', __( 'Source unavailable.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/** Serve a private PDF or an exam document with unchanged engine templates. */
	public static function document() {
		$source_job  = self::query_id( 'ohmylms_import_source' );
		$source_exam = self::query_id( 'ohmylms_exam_source' );
		$id          = self::query_id( 'ohmylms_exam' );
		if ( ! $source_job && ! $source_exam && ! $id ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		if ( $source_job || $source_exam ) {
			$document = self::source( $source_exam ? $source_exam : $source_job, (bool) $source_exam, (bool) self::query_id( 'mark_scheme' ) );
			$path     = is_wp_error( $document ) ? $document : Documents::path( $document['id'] );
			if ( is_wp_error( $path ) ) {
				wp_die( esc_html__( 'Source unavailable.', 'ohmylms' ), '', array( 'response' => 403 ) );
			}
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: inline; filename="exam-source.pdf"' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: SAMEORIGIN' );
			readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.Security.EscapeOutput.OutputNotEscaped -- Authorized verified PDF binary; escaping corrupts it.
			exit;
		}
		if ( Library::TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			wp_die( esc_html__( 'This exam has not been published.', 'ohmylms' ), '', array( 'response' => 404 ) );
		}
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$error = null;
		if ( isset( $_POST['ohmylms_exam_start'] ) ) {
			check_admin_referer( 'ohmylms_exam_start_' . $id );
			$result = self::start( $id );
			if ( ! is_wp_error( $result ) ) {
				wp_safe_redirect( self::url( $id ) );
				exit;
			}
			$error = $result;
		}
		$data            = get_post_meta( $id, Library::META, true );
		$quiz_id         = (int) $data['quiz_id'];
		$quiz            = ohmylms_get_quiz( $quiz_id );
		$attempt         = $quiz->get_quiz_attempt( get_current_user_id() );
		$GLOBALS['post'] = get_post( $quiz_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Shared quiz templates require the backing quiz as their post context.
		setup_postdata( $GLOBALS['post'] );
		show_admin_bar( false );
		wp_enqueue_scripts();
		remove_action( 'wp_head', 'wp_enqueue_scripts', 1 );
		wp_enqueue_style( 'ohmylms-frontend' );
		wp_enqueue_script( 'ohmylms-frontend' );
		\OhMyLMS\Assessment\MathLive::enqueue();
		wp_enqueue_style( 'ohmylms-exam-player', plugins_url( 'assets/css/exam-player.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/exam-player.css' ) );
		ob_start();
		if ( $error ) {
			echo '<p role="alert">' . esc_html( $error->get_error_message() ) . '</p>';
		}
		if ( $attempt ) {
			$access = Submission::access( $quiz_id, get_current_user_id() );
			if ( is_wp_error( $access ) ) {
				echo '<p>' . esc_html( $access->get_error_message() ) . '</p>';
			} else {
				// Exams written in the builder have no source PDF to open.
				if ( empty( $data['composed'] ) ) {
					echo '<p><a target="_blank" rel="noopener" href="' . esc_url( add_query_arg( 'ohmylms_exam_source', $id, home_url( '/' ) ) ) . '">' . esc_html__( 'Open question paper and figures', 'ohmylms' ) . '</a></p>';
				}
				ohmylms_get_template( 'single-lesson/quiz-form.php' );
			}
		} else {
			$attempts = $quiz->get_all_quiz_attempts( get_current_user_id(), $data['course_id'] );
			if ( $attempts ) {
				echo '<p role="status">' . esc_html__( 'Your exam has been submitted. Written parts await teacher review; results appear after release.', 'ohmylms' ) . '</p>';
				usort(
					$attempts,
					static function ( $left, $right ) {
						return (int) $right['quiz_attempt_id'] <=> (int) $left['quiz_attempt_id'];
					}
				);
				$latest = $attempts[0];
				if ( AssessmentSettings::feedback_visible( $latest['quiz_attempt_id'] ) ) {
					/* translators: 1: marks earned, 2: marks available in the frozen attempt. */
					echo '<p>' . esc_html( sprintf( __( 'Score: %1$s / %2$s', 'ohmylms' ), \OhMyLMS\Assessment\Scoring::display( $latest['total_achieved_marks'] ?? 0 ), \OhMyLMS\Assessment\Scoring::display( \OhMyLMS\Assessment\AttemptReport::basis( $latest['quiz_attempt_id'], $quiz )['max'] ) ) ) . '</p>';
					if ( ! is_wp_error( self::source( $id, true, true ) ) ) {
						echo '<p><a target="_blank" rel="noopener" href="' . esc_url(
							add_query_arg(
								array(
									'ohmylms_exam_source' => $id,
									'mark_scheme'         => 1,
								),
								home_url( '/' )
							)
						) . '">' . esc_html__( 'Open released mark scheme', 'ohmylms' ) . '</a></p>';
					}
				}
				$review = \OhMyLMS\Quiz\AnswerReview::render( $quiz, $attempts );
				echo $review; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared review renderer escapes fields and enforces release policy.
			} else {
				echo '<form method="post" action="' . esc_url( self::url( $id ) ) . '">';
				wp_nonce_field( 'ohmylms_exam_start_' . $id );
				echo '<button type="submit" name="ohmylms_exam_start" value="1">' . esc_html__( 'Start exam', 'ohmylms' ) . '</button></form>';
			}
		}
		$body = ob_get_clean();
		?><!doctype html>
		<html <?php language_attributes(); ?>>
		<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php echo esc_html( get_the_title( $id ) ); ?></title><?php wp_head(); ?></head>
		<body <?php body_class( 'ohmylms-page ohmylms-exam-player' ); ?>><main class="ohmylms-exam-shell<?php echo $attempt ? ' is-attempting' : ''; ?>">
		<header><p><?php esc_html_e( 'Exam', 'ohmylms' ); ?></p><h1><?php echo esc_html( get_the_title( $id ) ); ?></h1><p>
		<?php
		/* translators: 1: exam duration in minutes, 2: total marks. */
		echo esc_html( sprintf( __( '%1$d minutes · %2$s marks', 'ohmylms' ), $data['duration_minutes'], $data['total_marks'] ) );
		?>
		</p><p><?php echo nl2br( esc_html( $data['instructions'] ) ); ?></p></header>
		<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Locally escaped markup and shared engine templates. ?>
		</main><?php wp_footer(); ?></body></html>
		<?php
		exit;
	}
}
