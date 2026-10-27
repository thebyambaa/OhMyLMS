<?php
/**
 * The template for displaying lesson's quiz
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-quiz.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( empty( $ohmylms_player_preview ) && \OhMyLMS\Extensions\Layouts::render( 'quiz', get_the_ID(), ohmylms_get_course_by_content_id( get_the_ID() ) ) ) {
	return;
}

$quiz_start = isset( $_GET['quiz'] ) && 'start' == $_GET['quiz'] ? true : false;
$quiz       = ohmylms_get_quiz( get_the_ID() );
$questions  = array_values(
	array_filter(
		$quiz->get_questions(),
		static function ( $q ) {
			return \OhMyLMS\Extensions\Registry::get( 'question', $q['settings']['type'] ?? '' );
		}
	)
);
$attempt    = $quiz->get_quiz_attempt( get_current_user_id() );
$settings   = $quiz->get_settings();
if ( ! empty( $ohmylms_player_preview ) ) {
	$questions = $ohmylms_player_preview['questions'];
	$settings  = $ohmylms_player_preview['settings'];
	$attempt   = array( 'id' => 0 );
}
// Versioned attempts render the frozen items issued at start, in their stored order.
$attempt_context = ! empty( $attempt['id'] ) && \OhMyLMS\Assessment\Schema::ready() ? \OhMyLMS\Assessment\AttemptItems::context( $attempt['id'] ) : null;
if ( $attempt_context ) {
	$questions                = \OhMyLMS\Assessment\AttemptItems::delivery( $attempt['id'] );
	$revision                 = \OhMyLMS\Assessment\RevisionPublisher::revision( $attempt_context['revision_id'] );
	$settings                 = array_merge( is_array( $settings ) ? $settings : array(), $revision ? $revision['settings'] : array() );
	$settings['presentation'] = $revision['settings']['presentation'] ?? 'standard';
	$remaining_seconds        = \OhMyLMS\Assessment\Deadlines::remaining( $attempt_context );
	\OhMyLMS\Assessment\Delivery::require_script();
	$is_timer = null !== $remaining_seconds;
	$timer    = $is_timer ? $remaining_seconds / 60 : 0;
} else {
	$timer = $quiz->get_timer();
	if ( 0 < $timer && ! empty( $attempt['start_date'] ) ) {
		$timer = max( 0, ( $timer * 60 - ( current_time( 'timestamp' ) - strtotime( $attempt['start_date'] ) ) ) / 60 );
	}
	$is_timer = $quiz->get_timer() > 0 ? true : false;
}

if ( ! empty( $ohmylms_player_preview['single_question'] ) ) {
	$is_timer = false;
	$timer    = 0;
}

$paper_content      = $settings['paper_content'] ?? ( $attempt_context ? array() : \OhMyLMS\Quiz\PaperPresentation::content( get_the_ID() ) );
$paper_presentation = 'paper' === ( $settings['presentation'] ?? '' );
$paper_settings     = $paper_presentation ? \OhMyLMS\Exams\PaperSettings::sanitize( $settings['paper'] ?? array() ) : null;
$player_template    = \OhMyLMS\Quiz\PlayerTemplates::resolve( $settings['player_template'] ?? \OhMyLMS\Quiz\PlayerTemplates::DEFAULT_TEMPLATE );
$player_template    = \OhMyLMS\Student\PlayerContext::quiz_template( $player_template );
// The assessment kind's profile can fix the design and the layout (an exam always uses the exam dashboard).
$assessment_profile = isset( $ohmylms_player_preview['profile_kind'] ) ? \OhMyLMS\Assessment\Profiles::get( $ohmylms_player_preview['profile_kind'] ) : \OhMyLMS\Assessment\Profiles::of( get_the_ID() );
if ( '' !== $assessment_profile['player_template'] ) {
	$player_template = \OhMyLMS\Quiz\PlayerTemplates::resolve( $assessment_profile['player_template'] );
}
if ( $paper_presentation ) {
	$player_template = 'paper';
}
\OhMyLMS\Quiz\PlayerTemplates::enqueue( $player_template );
$quiz_layout = is_array( $settings ) && isset( $settings['layout'] ) ? $settings['layout'] : 'one_question_per_page';
if ( ! in_array( $quiz_layout, array( 'one_question_per_page', 'all_questions_in_one_page', 'number_of_questions_per_page' ), true ) ) {
	$quiz_layout = 'one_question_per_page';
}
if ( '' !== $assessment_profile['layout'] ) {
	$quiz_layout = $assessment_profile['layout'];
}
if ( $paper_presentation || ! empty( $ohmylms_player_preview['single_question'] ) ) {
	$quiz_layout = 'all_questions_in_one_page';
}
$layout_class             = '';
$questions_per_group      = '';
$totalGroups              = '';
$supported_question_count = 0;
$supported_question_types = apply_filters( 'ohmylms_supported_question_types', array_keys( \OhMyLMS\Extensions\Registry::all( 'question' ) ) );
foreach ( $questions as $index => $question ) {
	$supported_question_types = array_keys( \OhMyLMS\Extensions\Registry::all( 'question' ) );
	$supported_question_types = apply_filters( 'ohmylms_supported_question_types', $supported_question_types );
	if ( in_array( $question['settings']['type'], $supported_question_types ) ) {
		++$supported_question_count;
	}
}

// Frozen exam papers may carry page breaks (sections that start a new page). With all
// questions on one page they split the paper into pages; with grouped pages they also
// start a new group. One question per page already breaks everywhere.
$page_starts     = array();
$has_page_breaks = false;
foreach ( $questions as $question ) {
	if ( ! empty( $question['page'] ) && 1 < $question['page'] ) {
		$has_page_breaks = true; }
}
if ( ! $paper_presentation && $has_page_breaks && 'all_questions_in_one_page' === $quiz_layout ) {
	$quiz_layout                      = 'number_of_questions_per_page';
	$settings['question_in_one_page'] = PHP_INT_MAX;
}

if ( 'all_questions_in_one_page' === $quiz_layout ) {
	$layout_class = 'ohmylms-all-questions';

} elseif ( 'number_of_questions_per_page' === $quiz_layout ) {
	$layout_class        = 'ohmylms-grouped-questions';
	$questions_per_group = max( 1, (int) ( $settings['question_in_one_page'] ?? 1 ) ); // Get the number of questions per group.
	$ordinal             = 0;
	$group_start         = 0;
	$previous_page       = null;
	foreach ( $questions as $question ) {
		if ( ! in_array( $question['settings']['type'], apply_filters( 'ohmylms_supported_question_types', array_keys( \OhMyLMS\Extensions\Registry::all( 'question' ) ) ), true ) ) {
			continue; }
		$page = (int) ( $question['page'] ?? 0 );
		if ( 0 === $ordinal || $ordinal - $group_start >= $questions_per_group || ( null !== $previous_page && $page !== $previous_page ) ) {
			$page_starts[ $ordinal ] = count( $page_starts ) + 1;
			$group_start             = $ordinal;
		}
		$previous_page = $page;
		++$ordinal;
	}
	$totalGroups = count( $page_starts ); // Calculate the total number of groups.

} else {
	$layout_class = 'ohmylms-one-question-per-page';
}

if ( ! $attempt_context && isset( $settings['randomize_questions'] ) && $settings['randomize_questions'] && is_array( $questions ) ) {
	shuffle( $questions );
}

// Keep supported questions contiguous so grouped layouts and add-ons share the same page indices.
$questions                = array_values(
	array_filter(
		$questions,
		static function ( $question ) use ( $supported_question_types ) {
			return in_array( $question['settings']['type'], $supported_question_types, true );
		}
	)
);
$supported_question_count = count( $questions );
// Paper View shows the editor's paper; the questions follow its order so the player's numbers match it.
$paper_student = $paper_presentation ? \OhMyLMS\Quiz\PaperPresentation::student( get_the_ID(), (array) $settings, $ohmylms_player_preview['paper_items'] ?? null ) : null;
if ( $paper_student ) {
	$paper_order = array_flip( \OhMyLMS\Quiz\PaperPresentation::order( $paper_student['items'] ) );
	usort(
		$questions,
		static function ( $a, $b ) use ( $paper_order ) {
			return ( $paper_order[ (int) $a['id'] ] ?? PHP_INT_MAX ) <=> ( $paper_order[ (int) $b['id'] ] ?? PHP_INT_MAX );
		}
	);
}
if ( 'number_of_questions_per_page' === $quiz_layout ) {
	$totalGroups = count( $page_starts );
}
ob_start();


?>

<input type="hidden" class="ohmylms_quiz_id" value="<?php echo esc_attr( get_the_ID() ); ?>">
<input type="hidden" class="quiz_attempt_id" value="<?php echo esc_attr( $attempt['id'] ); ?>">

<section class="ohmylms-quiz ohmylms-player-template-<?php echo esc_attr( $player_template ); ?> <?php echo esc_attr( $layout_class ); ?><?php echo $paper_presentation ? ' ohmylms-paper-form is-font-' . esc_attr( $paper_settings['font'] ) : ''; ?>" data-player-template="<?php echo esc_attr( $player_template ); ?>"
<?php
if ( $attempt_context ) {
	?>
	data-attempt-engine="versioned" data-autosave="<?php echo esc_url( rest_url( 'ohmylms/v1/attempts/' . (int) $attempt['id'] . '/responses' ) ); ?>" data-deadline="<?php echo esc_attr( $attempt_context['deadline_at'] ? gmdate( 'c', strtotime( $attempt_context['deadline_at'] . ' UTC' ) ) : '' ); ?>"<?php } ?>>
	<?php
	\OhMyLMS\Quiz\PlayerTemplates::part(
		'header',
		$player_template,
		array(
			'quiz_name'  => $quiz->get_name(),
			'is_preview' => ! empty( $ohmylms_player_preview ),
		)
	);
	?>
	<p role="alert" class="ohmylms-inline-error" data-wp-text="context.error" data-wp-bind--hidden="!context.error" hidden></p>

	<div class="ohmylms-quiz-alert" >
		<div class="quiz-alert-inner">
			<div class="quiz-alert-wrapper">
				<div class="quiz-alert-body">
					<div class="icon">
						<svg width="24" height="24" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="#FFC048" d="M12 0A11.996 11.996 0 000 12a11.996 11.996 0 0012 12 11.995 11.995 0 0012-12A11.994 11.994 0 0012 0zm0 20.643a1.813 1.813 0 11-.008-3.626A1.813 1.813 0 0112 20.643zm2.194-14.937l-.684 9.231a.252.252 0 01-.251.235H10.74a.252.252 0 01-.252-.235l-.683-9.23a2.199 2.199 0 114.388 0z"/></svg>
					</div>

					<div class="title-area">
						<h4>
							<?php echo esc_html__( 'Sure to exit the quiz?', 'ohmylms' ); ?>
						</h4>
						<p>
							<?php echo esc_html__( 'Your progress will be lost if you leave the quiz.', 'ohmylms' ); ?>
						</p>
					</div>
				</div>

				<div class="quiz-alert-footer">
					<button class="ohmylms-button quiz-alert-cancel" tabindex="0">
						<?php echo esc_html__( 'Cancel', 'ohmylms' ); ?>
					</button>

					<form action="" method="post">
						<input type="hidden" name="action" value="<?php echo esc_attr( empty( $ohmylms_player_preview ) ? 'ohmylms-quiz-exit-submission' : 'ohmylms-quiz-preview-exit' ); ?>">
						<input type="hidden" name="ohmylms_quiz_id" value="<?php echo esc_attr( get_the_ID() ); ?>">
						<input type="hidden" name="quiz_attempt_id" value="<?php echo esc_attr( $attempt['id'] ); ?>">
						<?php
						if ( empty( $ohmylms_player_preview ) ) {
							wp_nonce_field( 'save_quiz_exit_submit', 'save-quiz-exit-submit-nonce' ); } else {
							?>
							<input type="hidden" name="preview_session" value="<?php echo esc_attr( $ohmylms_player_preview['session'] ); ?>">
													<?php } ?>

						<button type="submit" class="ohmylms-button quiz-alert-ok" tabindex="0">
							<?php echo esc_html__( 'Exit', 'ohmylms' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<?php
	if ( ! empty( $ohmylms_player_preview['result'] ) ) {
		$preview_result = $ohmylms_player_preview['result'];
		?>
		<div class="ohmylms-container" role="status">
			<div class="ohmylms-quiz-result">
				<h2><?php esc_html_e( 'Preview result', 'ohmylms' ); ?></h2>
				<?php if ( empty( $preview_result['entries'] ) ) { ?>
					<p>
					<?php
					echo esc_html(
						sprintf( /* translators: %1$s: text value; %2$s: text value. */
							__( 'Score: %1$s / %2$s', 'ohmylms' ),
							$preview_result['earned'],
							$preview_result['total']
						)
					);
					?>
					</p>
				<?php } ?>
				<?php
				if ( $preview_result['pending'] ) {
					?>
					<p><?php esc_html_e( 'Some answers require manual grading.', 'ohmylms' ); ?></p><?php } ?>
				<?php
				foreach ( $preview_result['errors'] as $error ) {
					?>
					<p><?php echo esc_html( $error ); ?></p><?php } ?>
				<?php
				if ( ! empty( $preview_result['entries'] ) ) {
					if ( ! empty( $preview_result['hidden'] ) ) {
						?>
						<p class="ohmylms-answer-review-note"><?php esc_html_e( 'Learners will not see the correct answers for this quiz. You see them because you are previewing it.', 'ohmylms' ); ?></p>
						<?php
					}
					ohmylms_get_template(
						'single-lesson/quiz-review.php',
						array(
							'entries' => $preview_result['entries'],
							'summary' => $preview_result['summary'],
							'intro'   => __( 'Your preview answers next to the correct answers. Nothing is saved.', 'ohmylms' ),
							'anchor'  => 'ohmylms-answer-review-preview',
						)
					);
				}
				?>
				<a class="ohmylms-button" href="<?php echo esc_url( \OhMyLMS\Assessment\PreviewPlayer::url( get_the_ID() ) ); ?>"><?php esc_html_e( 'Try again', 'ohmylms' ); ?></a>
			</div>
		</div>
	<?php } ?>
	<?php if ( empty( $ohmylms_player_preview['result'] ) ) { ?>
		<?php if ( $is_timer ) { ?>
		<div class="ohmylms-quiz-timeup-text">
			<?php
				printf(
					/* translators: %s: text value. */
					__( 'Your quiz submission time has expired. <a href="%s">Please try again.</a>', 'ohmylms' ),
					get_the_permalink()
				);
			?>
		</div>

		<div class="ohmylms-quiz-timer">
			<div class="ohmylms-container">
				<div class="ohmylms-timer-wrapper">
					<div class="ohmylms-timer">
						<span class="clock">
							<?php include OHMYLMS_DIR . '/assets/images/icon/clock-icon.php'; ?>
							<span class="timer-display" data-timer="<?php echo esc_attr( $timer ); ?>">0.00</span>
						</span>

						<span class="progress-outer">
							<span class="progress-inner" style="width: 100%;"></span>
						</span>
					</div>

					<!-- <div class="point">1 Point</div> -->
				</div>
			</div>
		</div>
	<?php } ?>

	<!-- <div class="ohmylms-quiz-timer">
		<div class="ohmylms-container">
			<div class="ohmylms-quiz-result">
				use "failed" class with the "success-title" class if quiz is failed then remove this comment
				<p class="success-title">
					<?php // echo __('You passed the quiz', 'ohmylms');. ?>
				</p>

				<div class="score-box">
					<span class="percentage">
						Score: <strong>50%</strong>
					</span>
					<span class="correct-answer">
						Correct:
						<strong>3/4</strong>
					</span>
				</div>
			</div>
		</div>
	</div> -->

		<?php
		$player_total_pages = max( 1, 'all_questions_in_one_page' === $quiz_layout ? 1 : ( 'number_of_questions_per_page' === $quiz_layout ? $totalGroups : $supported_question_count ) );
		$player_sections    = array();
		foreach ( $questions as $section_index => $section_question ) {
			$player_sections[ $section_index + 1 ] = (string) ( $section_question['section'] ?? '' );
		}
		\OhMyLMS\Quiz\PlayerTemplates::part(
			'progress',
			$player_template,
			array(
				'total_pages'        => $player_total_pages,
				'layout'             => $quiz_layout,
				'sections'           => $player_sections,
				'paper_presentation' => $paper_presentation,
				'question_count'     => $supported_question_count,
			)
		);
		?>
	<form action="" method="post">
		<?php
		ohmylms_render_slot(
			'student.quiz.before',
			array(
				'quizId'    => get_the_ID(),
				'attemptId' => (int) $attempt['id'],
			)
		);
		?>
		<div class="ohmylms-quiz-form">
			<?php if ( $paper_student ) { ?>
				<script type="application/json" class="ohmylms-paper-student-data"><?php echo wp_json_encode( $paper_student, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ); ?></script>
			<?php } ?>
			<input type="hidden" name="quiz_attempt_id" value="<?php echo esc_attr( $attempt['id'] ); ?>">
			<div class="ohmylms-container">
				<div class="ohmylms-quiz-form-wrapper">
					<?php
					if ( ! $questions ) {
						?>
						<p><?php esc_html_e( 'This quiz has no supported questions yet.', 'ohmylms' ); ?></p><?php } ?>
					<!-- add "wrong-answered" class with the "ohmylms-quiz-box" class if quiz is failed and then remove this comment -->

					<?php
					$count = 0;
					foreach ( $questions as $index => $question ) {
						$get_question             = empty( $question['frozen'] ) ? ohmylms_get_question( $question['id'] ) : null;
						$question_image           = $get_question ? $get_question->get_image_url() : ( $question['image_src'] ?? '' );
						$question_video           = $get_question ? wp_get_attachment_url( $get_question->get_video_id() ) : ( $question['video_src'] ?? '' );
						$supported_question_types = array_keys( \OhMyLMS\Extensions\Registry::all( 'question' ) );
						$supported_question_types = apply_filters( 'ohmylms_supported_question_types', $supported_question_types );

						if ( ! in_array( $question['settings']['type'], $supported_question_types ) ) {
							continue;
						}

						++$count;
						if ( $paper_presentation ) {
							echo wp_kses_post( $paper_content[ $question['id'] ] ?? '' );
						}


						// Check if layout grouped question and we're at the start of a new group.
						if ( 'number_of_questions_per_page' === $quiz_layout && isset( $page_starts[ $count - 1 ] ) ) {
							// Close the previous group div if it's not the first group.
							if ( 1 < $count ) {
								echo '</div>';
							}
							// Start a new group div.
							$groupNumber = $page_starts[ $count - 1 ];
							echo "<div class='ohmylms-question-group question-group-{$groupNumber} " . ( 1 == $groupNumber ? 'active' : '' ) . "'>";
						}
						?>

						<?php
						if ( ! empty( $question['section'] ) && ( $question['section'] !== ( $previous_section ?? null ) ) ) {
							$previous_section = $question['section'];
							?>
							<h2 class="ohmylms-quiz-section-title"><?php echo esc_html( $question['section'] ); ?></h2>
						<?php } ?>
						<div data-question-id="<?php echo esc_attr( $question['id'] ); ?>" class="ohmylms-quiz-box question-<?php echo esc_attr( $count ); ?> <?php echo esc_attr( ( 'one_question_per_page' === $quiz_layout && 1 == $count ) ? 'active' : '' ); ?>">
							<?php
							\OhMyLMS\Quiz\PlayerTemplates::part(
								'question-meta',
								$player_template,
								array(
									'ordinal'        => $ohmylms_player_preview['ordinal'] ?? $count,
									'question_count' => $ohmylms_player_preview['question_count'] ?? $supported_question_count,
									'marks'          => max( 0, (float) ( $question['settings']['score']['value'] ?? 0 ) ),
									'section'        => (string) ( $question['section'] ?? '' ),
									'paper'          => $paper_settings,
								)
							);
							?>

							<div class="question-box">
								<p class="the-question question-type-<?php echo esc_attr( $question['settings']['type'] ); ?>">
									<?php echo \OhMyLMS\Assessment\InlineBlanks::render( $question, $attempt ); ?>

									<?php if ( ! empty( $question['settings']['required'] ) && $question['settings']['required'] ) { ?>
										<span class="required">*</span>
									<?php } ?>

									<input type="hidden" class="is-required" value= "<?php echo esc_attr( ! empty( $question['settings']['required'] ) ? $question['settings']['required'] : '' ); ?>" question-type="<?php echo esc_attr( $question['settings']['type'] ); ?>" />
								</p>

								<?php if ( ! empty( $question['description'] ) && ( $question['settings']['type'] ?? '' ) !== 'structured' ) { ?>
									<div class="ohmylms-question-block-content"><?php echo apply_filters( 'the_content', $question['description'] ); ?></div>
								<?php } ?>
								<?php if ( ! empty( $question_image ) ) { ?>
									<img src="<?php echo esc_url( $question_image ); ?>" alt="question image" class="question-image">
								<?php } ?>

								<?php
								$video = $question_video;
								if ( $video ) {
									?>
									<video class="question-video" controls controlsList="nodownload nopictureinpicture">
										<source src="<?php echo esc_url( $video ); ?>" type="video/mp4">
									</video>
								<?php } ?>
							</div>

							<?php

							if ( empty( $question['frozen'] ) && isset( $question['settings']['randomize'] ) && $question['settings']['randomize'] && is_array( $question['questions'] ) ) {
								shuffle( $question['questions'] );
							}

							if ( $paper_presentation && 'multiple-choice' === $question['settings']['type'] ) {
								echo '<p>' . esc_html__( 'Select all that apply.', 'ohmylms' ) . '</p>';
							}
							\OhMyLMS\Extensions\QuestionTypes::render( $question, $attempt );
							?>
							<?php if ( ! empty( $question['settings']['required'] ) ) : ?>
								<span class="required-question"><?php esc_html_e( 'The question must be answered', 'ohmylms' ); ?></span>
							<?php endif; ?>
						</div>

						<?php
						// Checked if layout grouped question and close the last group div after the last question.
						if ( 'number_of_questions_per_page' === $quiz_layout && $count === $supported_question_count ) {
							echo '</div>';
						}
					}
					if ( $paper_presentation ) {
						echo wp_kses_post( $paper_content['end'] ?? '' );
					}
					?>

				</div>
			</div>
		</div>

		<div class="ohmylms-quiz-footer">
			<div class="ohmylms-container">
				<div class="ohmylms-footer-wrapper">
					<div class="ohmylms-quiz-footer-left">
						<?php \OhMyLMS\Quiz\PlayerTemplates::part( 'footer', $player_template, array() ); ?>
						<?php if ( empty( $ohmylms_player_preview ) && ! empty( ohmylms_get_next_content_permalink( get_the_ID() ) ) ) { ?>
							<a href="<?php echo esc_url( ohmylms_get_next_content_permalink( get_the_ID() ) ); ?>" class="skiptop-next">
								<?php echo esc_html__( 'Skip to Next Lesson', 'ohmylms' ); ?>
							</a>
						<?php } ?>
					</div>

					<div class="ohmylms-quiz-footer-right">
						<input type="hidden" name="action" value="<?php echo esc_attr( empty( $ohmylms_player_preview ) ? 'ohmylms-quiz-submission' : 'ohmylms-quiz-preview-submit' ); ?>">
						<input type="hidden" name="ohmylms_quiz_id" value="<?php echo esc_attr( get_the_ID() ); ?>">
						<?php
						if ( empty( $ohmylms_player_preview ) ) {
							wp_nonce_field( 'save_quiz_submit', 'save-quiz-submit-nonce' ); } else {
							?>
							<input type="hidden" name="preview_session" value="<?php echo esc_attr( $ohmylms_player_preview['session'] ); ?>">
													<?php } ?>

						<?php
						if ( 'all_questions_in_one_page' === $quiz_layout ) {
							?>
								<button type="submit" class="ohmylms-button quiz-submit">
								<?php echo esc_html__( 'Submit ', 'ohmylms' ); ?>
								</button>
								<?php

						} elseif ( 'number_of_questions_per_page' === $quiz_layout ) {
							// ------start grouped questions------
							$layout_class = 'ohmylms-grouped-questions';

							if ( 1 < $totalGroups ) {
								?>
									<button type="button" class="ohmylms-button ohmylms-previous-quiz-group outline" current-group="1" previous-group="" total-group="<?php echo esc_attr( $totalGroups ); ?>" disabled >
									<?php echo esc_html__( 'Previous', 'ohmylms' ); ?>
									</button>
									<?php
							}

							if ( 1 < $totalGroups ) {
								?>
									<button type="button" class="ohmylms-button ohmylms-next-quiz-group" current-group="1" next-group="2" total-group="<?php echo esc_attr( $totalGroups ); ?>">
									<?php echo esc_html__( 'Next', 'ohmylms' ); ?>
									</button>

									<button type="submit" class="ohmylms-button quiz-submit" style="display: none">
									<?php echo esc_html__( 'Submit ', 'ohmylms' ); ?>
									</button>
									<?php
							} else {
								?>
									<button type="submit" class="ohmylms-button quiz-submit">
									<?php echo esc_html__( 'Submit ', 'ohmylms' ); ?>
									</button>
									<?php
							}
							// ----end grouped questions-----

						} else {
							?>
								<button type="button" class="ohmylms-button ohmylms-previous-quiz outline" current-question="1" previous-question="" total-questions="<?php echo esc_attr( $count ); ?>" disabled>
								<?php echo esc_html__( 'Previous', 'ohmylms' ); ?>
								</button>

								<?php
								if ( 1 < $count ) {
									?>
									<button type="button" class="ohmylms-button ohmylms-next-quiz" current-question="1" next-question="2" total-questions="<?php echo esc_attr( $count ); ?>">
										<span class="ohmylms-next-quiz-label"><?php echo esc_html__( 'Skip', 'ohmylms' ); ?></span>
									</button>
									<?php
								}

								if ( 1 < $count ) {
									?>
									<button type="submit" class="ohmylms-button quiz-submit" style="display: none">
										<?php echo esc_html__( 'Submit ', 'ohmylms' ); ?>
									</button>
									<?php
								} else {
									?>
									<button type="submit" class="ohmylms-button quiz-submit">
										<?php echo esc_html__( 'Submit ', 'ohmylms' ); ?>
									</button>
									<?php
								}
						}
						?>

					</div>
				</div>
			</div>
		</div>
		<?php
		ohmylms_render_slot(
			'student.quiz.after',
			array(
				'quizId'    => get_the_ID(),
				'attemptId' => (int) $attempt['id'],
			)
		);
		?>
	</form>
	<?php } ?>
</section>
<?php
$duration = max( 0, (int) round( $timer * 60 ) );
echo \OhMyLMS\Extensions\Interactivity::quiz(
	ob_get_clean(),
	array(
		'quizId'            => (int) get_the_ID(),
		'paperPresentation' => $paper_presentation,
		'questionCount'     => $supported_question_count,
		'confirmSubmit'     => /* translators: %d: unanswered questions. */ __( '%d questions have no answer yet. Submit anyway?', 'ohmylms' ),
		'attemptId'         => (int) $attempt['id'],
		'layout'            => $quiz_layout,
		'page'              => 1,
		'perPage'           => max( 1, (int) $questions_per_group ),
		'totalPages'        => max( 1, 'all_questions_in_one_page' === $quiz_layout ? 1 : ( 'number_of_questions_per_page' === $quiz_layout ? $totalGroups : $supported_question_count ) ),
		'errors'            => (object) array(),
		'answered'          => (object) array(),
		'questionOfLabel'   => /* translators: %1$d: number; %2$d: number. */
		__( 'Question %1$d of %2$d', 'ohmylms' ),
		'skipText'          => __( 'Skip', 'ohmylms' ),
		'nextText'          => __( 'Next', 'ohmylms' ),
		'submitting'        => false,
		'exitOpen'          => false,
		'error'             => '',
		'preview'           => ! empty( $ohmylms_player_preview ),
		'timed'             => $is_timer && empty( $ohmylms_player_preview['result'] ),
		'duration'          => $duration,
		'remaining'         => $duration,
		'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
		'expiryNonce'       => wp_create_nonce( 'quiz_exit_submission' ),
		'submissionError'   => __( 'Submission failed. Your answers remain on this page. Please press Submit to retry.', 'ohmylms' ),
	)
); // phpcs:ignore WordPress.Security.EscapeOutput -- templates escape their own fields.
