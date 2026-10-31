<?php
/**
 * The template for displaying lesson's Assignment content
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-assignment.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Assignment $assignment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$assignment    = ohmylms_get_assignment( get_the_ID() );
$is_time_limit = $assignment->get_enable_time_limit();
$time_value    = $assignment->get_time_limit();
$time_unit     = $assignment->get_time_limit_type();
if ( is_string( $time_unit ) ) {
	$time_unit = strtolower( $time_unit );
}

$get_submissions_files = $assignment->get_submission( get_current_user_id() );
$student               = new \OhMyLMS\Data\Student( get_current_user_id() );
$deadline              = false;
if ( $student ) {
	$deadline = $student->get_assignment_remaining_time( $assignment->get_id() );
}
// The learner's timer as the server enforces it (OhMyLMS\Assignment\Delivery::timer()).
$timer = \OhMyLMS\Assignment\Delivery::timer( $assignment->get_id(), get_current_user_id() );
// Convert time to minutes.
$time_in_minutes = 0;
switch ( $time_unit ) {
	case 'day':
	case 'days':
		$time_in_minutes = $time_value * 24 * 60;
		break;
	case 'week':
	case 'weeks':
		$time_in_minutes = $time_value * 7 * 24 * 60;
		break;
	case 'month':
	case 'months':
		$time_in_minutes = $time_value * 30 * 24 * 60; // Assuming 30 days in a month.
		break;
	default:
		$time_in_minutes = $time_value; // Assume minutes if no valid unit is found.
		break;
}

?>

<?php while ( have_posts() ) : ?>
	<?php the_post(); ?>

	<div class="ohmylms-lesson-content-body content-type-assignment" data-wp-interactive="ohmylms/assignment" data-wp-init="callbacks.mount">
		<h1><?php echo esc_html( $assignment->get_name() ); ?></h1>

		<ul class="ohmylms-assignment-quiz-meta">
			<?php if ( $assignment->get_enable_time_limit() && $assignment->get_time_limit_type() && $assignment->get_time_limit() ) { ?>
				<li class="duratioin">
					<strong><?php echo esc_html__( 'Duration: ', 'ohmylms' ); ?></strong>
				<?php echo esc_html( $assignment->get_time_limit() ); ?>  <?php echo esc_html( $assignment->get_time_limit_type() ); ?>
				</li>
			<?php } ?>
			
			<?php if ( $timer['enabled'] ) : ?>
				<li class="deadline"<?php echo $timer['deadline'] ? '' : ' hidden'; ?> data-wp-bind--hidden="state.deadlineHidden">
					<strong><?php echo esc_html__( 'Deadline: ', 'ohmylms' ); ?></strong>
					<span class="ohmylms-deadline-value" data-wp-text="state.deadlineLabel"><?php echo esc_html( $timer['deadline_label'] ); ?></span>
				</li>
			<?php endif; ?>

			<?php if ( ! empty( $assignment->get_total_points() ) ) { ?>
				<li class="total-marks">
					<strong><?php echo esc_html__( 'Total Marks: ', 'ohmylms' ); ?></strong>
					<?php echo esc_html( $assignment->get_total_points() ); ?>
				</li>
			<?php } ?>

			<?php if ( ! empty( $assignment->get_maximum_pass_points() ) ) { ?>
				<li class="pass-mark">
					<strong><?php echo esc_html__( 'Passing Mark: ', 'ohmylms' ); ?></strong>
					<?php echo esc_html( $assignment->get_maximum_pass_points() ); ?>
				</li>
			<?php } ?>
		</ul>

		<div class="ohmylms-wysiwyg-content" >
			<?php
				the_content();
			?>
		</div>

		<?php
		$delivery  = \OhMyLMS\Assignment\Delivery::state( $assignment->get_id(), get_current_user_id() );
		$resources = \OhMyLMS\Assignment\Delivery::read_resources( $assignment->get_id() );

		if ( $resources ) {
			?>
				<h2 class="ohmylms-assignment-section-title"><?php esc_html_e( 'Resources', 'ohmylms' ); ?></h2>
				<ul class="ohmylms-resources-list">
				<?php
				foreach ( $resources as $resource ) {
					$is_file = 'file' === $resource['type'];
					?>

						<li>
							<div class="ohmylms-single-resource-info">
								<span class="resource-icon">
								<?php include OHMYLMS_DIR . '/assets/images/icon/file-icon.php'; ?>
								</span>
								<span class="resource-name"><?php echo esc_html( $resource['name'] ); ?></span>
								<span class="resource-size"><?php echo esc_html( $resource['size'] ); ?></span>
							</div>

							<?php if ( $is_file ) : ?>
								<a href="<?php echo esc_url( $resource['url'] ); ?>" class="resource-action" download aria-label="<?php echo esc_attr( sprintf( /* translators: %s: resource name. */ __( 'Download %s', 'ohmylms' ), $resource['name'] ) ); ?>">
									<?php include OHMYLMS_DIR . '/assets/images/icon/download-icon.php'; ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( $resource['url'] ); ?>" class="resource-action" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: resource name. */ __( 'Open %s (new tab)', 'ohmylms' ), $resource['name'] ) ); ?>">
									<?php include OHMYLMS_DIR . '/assets/images/icon/download-icon.php'; ?>
								</a>
							<?php endif; ?>
						</li>
						<?php
				}
				?>
				</ul>
				<?php
		}

		if ( $delivery['prerequisites'] ) {
			?>
			<section class="ohmylms-assignment-prerequisites" aria-labelledby="ohmylms-assignment-prerequisites-title">
				<h2 id="ohmylms-assignment-prerequisites-title" class="ohmylms-assignment-section-title"><?php esc_html_e( 'Complete before you submit', 'ohmylms' ); ?></h2>
				<ul>
					<?php foreach ( $delivery['prerequisites'] as $item ) : ?>
						<li class="<?php echo esc_attr( $item['completed'] ? 'is-complete' : 'is-incomplete' ); ?>">
							<span aria-hidden="true"><?php echo $item['completed'] ? '&#10003;' : '&#9675;'; ?></span>
							<?php if ( $item['url'] ) : ?>
								<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $item['title'] ); ?>
							<?php endif; ?>
							<span class="screen-reader-text"><?php echo $item['completed'] ? esc_html__( '(completed)', 'ohmylms' ) : esc_html__( '(not completed yet)', 'ohmylms' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
			<?php
		}

		if ( ! $delivery['can_submit'] ) {
			?>
			<p class="ohmylms-assignment-blocked" id="ohmylms-assignment-blocked" role="status"><?php echo esc_html( $delivery['message'] ); ?></p>
			<?php
		}
		?>

		<?php if ( $delivery['can_submit'] ) : ?>
		<div class="ohmylms-assignment-submit" style="display: none" data-wp-style--display="state.panelDisplay">
			<div class="assignment-submit-head">
				<div class="assignment-submit-title">
					<h6><?php echo esc_html__( 'Submit Assignment', 'ohmylms' ); ?></h6>
					<p><?php echo esc_html__( 'Only your instructor will see your submission', 'ohmylms' ); ?></p>
				</div>

				
				<?php if ( $is_time_limit ) : ?>
				<div class="ohmylms-timer submission-time-limit" style="display: none;" role="timer" aria-label="<?php esc_attr_e( 'Time left', 'ohmylms' ); ?>" data-wp-style--display="state.timerDisplay">
					<span class="clock">
						<?php include OHMYLMS_DIR . '/assets/images/icon/clock-icon.php'; ?>
						<span class="timer-display" data-timer="<?php echo esc_attr( $time_in_minutes ); ?>" data-wp-text="state.timeLabel"></span>
					</span>

					<span class="progress-outer">
						<span class="progress-inner" style="width: 100%;" data-wp-style--width="state.timerWidth"></span>
					</span>
				</div>
				<?php endif; ?>
			</div>

			<form  class="ohmylms-assignment-form" enctype="multipart/form-data" data-wp-on--submit="actions.submit" data-wp-bind--aria-busy="state.busy">
				<div class="ohmylms-form-group submission-body">
					<label for="ohmylms-submission-body"><?php echo esc_html__( 'Submission Body', 'ohmylms' ); ?></label>
					<textarea name="submission-body" id="ohmylms-submission-body" placeholder="<?php esc_attr_e( 'Write something...', 'ohmylms' ); ?>" data-wp-bind--disabled="state.locked"></textarea>
				</div>


				<?php if ( $assignment->get_allow_upload_files() ) { ?>
					<div class="ohmylms-form-group submission-file" data-wp-bind--hidden="state.hasFiles">
						<label for="submission-file" class="file-upload-label">
							<?php echo esc_html__( 'File', 'ohmylms' ); ?>

							<span class="submission-tooltip">
								<svg width="14" height="15" fill="none" viewBox="0 0 14 15" xmlns="http://www.w3.org/2000/svg"><path fill="#7A8B9A" fill-rule="evenodd" d="M13.665 7.5a6.667 6.667 0 11-13.333 0 6.667 6.667 0 0113.333 0zM7 6.833c.368 0 .666.299.666.667v3.334a.667.667 0 11-1.333 0V7.5c0-.368.299-.667.667-.667zM7 5.5a.667.667 0 100-1.333A.667.667 0 007 5.5z" clip-rule="evenodd"></path></svg>

								<span class="submission-tooltip-text">
									.jpg, .jpeg, .png, .webp
									<hr/>
									.pdf, .doc, .docx, .xls, .xlsx, .csv, .zip
									<hr/>
									.mp3, .mp4
								</span>
							</span>
						</label>

						<label for="submission-file" class="ohmylms-file-upload" tabindex="0" data-wp-on--keydown="actions.pickKey">
							<input 
								id="submission-file" 
								type="file" 
								name="ohmylms-submission-file" 
								draggable="true"
								accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.mp3,.mp4,.zip"
								upload-limit="<?php echo esc_attr( $assignment->get_max_file_size_limit() ); ?>"
								aria-describedby="submission-max-file-limit submission-instructions"
								data-wp-on--change="actions.pick"
								data-wp-bind--disabled="state.locked"
							>
							<span class="ohmylms-button" role="button" aria-hidden="true">
								<?php echo esc_html__( 'Select a File', 'ohmylms' ); ?>
							</span>

							<span class="drop-file-text" id="submission-instructions">
								<?php echo esc_html__( 'Drop your files here or browse', 'ohmylms' ); ?>
							</span>
						</label>

						<span class="submission-max-file-limit" id="submission-max-file-limit">
							<?php printf( /* translators: %s: text value. */
							__( 'Max File size: %sMB', 'ohmylms' ), $assignment->get_max_file_size_limit() ); ?>
						</span>
					</div>
				<?php } ?>

				<div class="ohmylms-form-group attached-resources" style="display: none" data-wp-style--display="state.attachedDisplay">
					<?php if ( $assignment->get_allow_upload_files() ) { ?>
						<div class="attached-file">
							<label class="attached-file-label">
								<?php echo esc_html__( 'File', 'ohmylms' ); ?>
							</label>
						</div>
					<?php } ?>

					<ul class="ohmylms-resources-list" id="ohmylms-submission-file-list">
						<template data-wp-each--file="state.files" data-wp-each-key="context.file.key">
							<li>
								<div class="ohmylms-single-resource-info">
									<span class="resource-icon">
										<?php include OHMYLMS_DIR . '/assets/images/icon/file-icon.php'; ?>
									</span>
									<span class="resource-name" data-wp-text="context.file.name"></span>
									<span class="resource-size" data-wp-text="context.file.size"></span>
								</div>

								<button type="button" class="resource-action remove-resource-action" aria-label="<?php esc_attr_e( 'Remove the file', 'ohmylms' ); ?>" data-wp-on--click="actions.remove">
									<?php include OHMYLMS_DIR . '/assets/images/icon/delete-icon.php'; ?>
								</button>
							</li>
						</template>
					</ul>

					<span class="submission-max-file-limit-alert" style="display: none" role="alert" data-wp-style--display="state.sizeAlertDisplay">
						<?php printf( /* translators: %s: text value. */
						__( 'File size exceeds the allowed limit of : %sMB', 'ohmylms' ), $assignment->get_max_file_size_limit() ); ?>
					</span>
				</div>
				<input type="hidden" name="action" value="save_assignment_submission_file">
				<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $assignment->get_id() ); ?>">
				<input type="hidden" name="ohmylms_assignment_id" value="<?php echo esc_attr( $assignment->get_id() ); ?>">
				<input type="hidden" name="ohmylms_assignment_deadline" value="<?php echo esc_attr( $deadline ); ?>">
				<input type="hidden" name="course_id" value="<?php echo esc_attr( $delivery['course_id'] ); ?>">

				<div class="ohmylms-form-group submission-submit">
					<p class="assignment-submit-alert" role="alert" data-wp-style--display="state.errorDisplay">
						<svg width="16" height="16" fill="none" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#FF6F6F" d="M8 16a8 8 0 118-8 8.009 8.009 0 01-8 8zM8 1.333A6.667 6.667 0 1014.667 8 6.674 6.674 0 008 1.333z"/><path fill="#FF6F6F" d="M8 12.667A.667.667 0 017.333 12V6.667a.667.667 0 111.334 0V12a.667.667 0 01-.667.667zM8.667 4a.667.667 0 11-1.334 0 .667.667 0 011.334 0z"/></svg>
						<span data-wp-text="state.error"></span>
					</p>

					<button class="ohmylms-button" type="submit" disabled data-wp-bind--disabled="state.submitDisabled">
						<?php echo esc_html__( 'Submit Assignment', 'ohmylms' ); ?>
					</button>
				</div>
				
			</form>

		</div>
		<?php endif; ?>

		<?php ohmylms_get_template( 'single-lesson/assignment-submissions.php', array( 'assignment' => $assignment ) ); ?>

	</div>

<?php endwhile; // end of the loop. ?>
