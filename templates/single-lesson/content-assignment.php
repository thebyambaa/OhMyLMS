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
$timeValue     = $assignment->get_time_limit();
$timeUnit      = $assignment->get_time_limit_type();
if ( is_string( $timeUnit ) ) {
	$timeUnit = strtolower( $timeUnit );
}

$get_submissions_files = $assignment->get_submission( get_current_user_id() );
$student               = new \OhMyLMS\Data\Student( get_current_user_id() );
$deadline              = false;
if ( $student ) {
	$deadline = $student->get_assignment_remaining_time( $assignment->get_id() );
}
// Convert time to minutes.
$timeInMinutes = 0;
switch ( $timeUnit ) {
	case 'day':
	case 'days':
		$timeInMinutes = $timeValue * 24 * 60;
		break;
	case 'week':
	case 'weeks':
		$timeInMinutes = $timeValue * 7 * 24 * 60;
		break;
	case 'month':
	case 'months':
		$timeInMinutes = $timeValue * 30 * 24 * 60; // Assuming 30 days in a month.
		break;
	default:
		$timeInMinutes = $timeValue; // Assume minutes if no valid unit is found.
		break;
}

// Format the timer as HH:MM.
$hours          = floor( $timeInMinutes / 60 );
$minutes        = $timeInMinutes % 60;
$formattedTimer = str_pad( $hours, 2, '0', STR_PAD_LEFT ) . 'h ' . str_pad( $minutes, 2, '0', STR_PAD_LEFT );

?>

<?php while ( have_posts() ) : ?>
	<?php the_post(); ?>

	<div class="ohmylms-lesson-content-body content-type-assignment" >
		<h1><?php echo esc_html( $assignment->get_name() ); ?></h1>

		<ul class="ohmylms-assignment-quiz-meta">
			<?php if ( $assignment->get_enable_time_limit() && $assignment->get_time_limit_type() && $assignment->get_time_limit() ) { ?>
				<li class="duratioin">
					<strong><?php echo esc_html__( 'Duration: ', 'ohmylms' ); ?></strong>
				<?php echo esc_html( $assignment->get_time_limit() ); ?>  <?php echo esc_html( $assignment->get_time_limit_type() ); ?>
				</li>
			<?php } ?>
			
			<?php if ( $deadline ) : ?>
				<li class="deadline">
					<strong><?php echo esc_html__( 'Deadline: ', 'ohmylms' ); ?></strong>
					<?php echo esc_html( $formattedTimer ); ?>
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
			$resources = $assignment->get_download_resource();

		if ( ! empty( $resources['file'] ) ) {
			?>
				<ul class="ohmylms-resources-list">
				<?php
				foreach ( $resources['file'] as $resource ) {
					?>

						<li>
							<div class="ohmylms-single-resource-info">
								<span class="resource-icon">
								<?php include OHMYLMS_DIR . '/assets/images/icon/file-icon.php'; ?>
								</span>
								<span class="resource-name"><?php echo esc_html( $resource['name'] ); ?></span>
								<span class="resource-size"><?php echo esc_html( $resource['size'] ); ?></span>
							</div>

							<a href="<?php echo esc_url( $resource['url'] ); ?>" class="resource-action" download>
							<?php include OHMYLMS_DIR . '/assets/images/icon/download-icon.php'; ?>
							</a>
						</li>
						<?php
				}
				?>
				</ul>
				<?php
		}
		?>

		<div class="ohmylms-assignment-submit" style="display: none">
			<div class="assignment-submit-head">
				<div class="assignment-submit-title">
					<h6><?php echo esc_html__( 'Submit Assignment', 'ohmylms' ); ?></h6>
					<p><?php echo esc_html__( 'Only your instructor will see your submission', 'ohmylms' ); ?></p>
				</div>

				
				<?php if ( $is_time_limit ) : ?>
				<div class="ohmylms-timer submission-time-limit" style="display: none;">
					<span class="clock">
						<?php include OHMYLMS_DIR . '/assets/images/icon/clock-icon.php'; ?>
						<span class="timer-display" data-timer="<?php echo esc_attr( $timeInMinutes ); ?>">
							<!-- <?php echo esc_html( $formattedTimer ); ?>:00 -->
						</span>
					</span>

					<span class="progress-outer">
						<span class="progress-inner" style="width: 100%;"></span>
					</span>
				</div>
				<?php endif; ?>
			</div>

			<form  class="ohmylms-assignment-form" enctype="multipart/form-data">
				<div class="ohmylms-form-group submission-body">
					<label for=""><?php echo esc_html__( 'Submission Body', 'ohmylms' ); ?></label>
					<textarea name="submission-body" id="" placeholder="Write something... "></textarea>
				</div>


				<?php if ( $assignment->get_allow_upload_files() ) { ?>
					<div class="ohmylms-form-group submission-file">
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

						<label for="submission-file" class="ohmylms-file-upload" tabindex="0">
							<input 
								id="submission-file" 
								type="file" 
								name="ohmylms-submission-file" 
								draggable="true"
								accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.mp3,.mp4,.zip"
								upload-limit="<?php echo esc_attr( $assignment->get_max_file_size_limit() ); ?>"
								aria-describedby="submission-max-file-limit submission-instructions"
							>
							<span class="ohmylms-button" role="button" aria-hidden="true">
								<?php echo esc_html__( 'Select a File', 'ohmylms' ); ?>
							</span>

							<span class="drop-file-text" id="submission-instructions">
								<?php echo esc_html__( 'Drop your files here or browse', 'ohmylms' ); ?>
							</span>
						</label>

						<span class="submission-max-file-limit">
							<?php printf( /* translators: %s: text value. */
							__( 'Max File size: %sMB', 'ohmylms' ), $assignment->get_max_file_size_limit() ); ?>
						</span>
					</div>
				<?php } ?>

				<div class="ohmylms-form-group attached-resources" style="display: none">
					<?php if ( $assignment->get_allow_upload_files() ) { ?>
						<div class="attached-file">
							<label class="attached-file-label">
								<?php echo esc_html__( 'File', 'ohmylms' ); ?>
							</label>

							<!-- <label class="ohmylms-button" type="button" for="ohmylms-add-new-attachment">
								<input id="ohmylms-add-new-attachment" type="file" name="ohmylms-submission-file" draggable="true">
								<?php echo esc_html__( 'Add Attachment', 'ohmylms' ); ?>
							</label> -->
						</div>
					<?php } ?>

					<ul class="ohmylms-resources-list" id="ohmylms-submission-file-list">
						<?php
						$get_submissions_files = $assignment->get_submission( get_current_user_id() );

						if ( ! empty( $get_submissions_files ) ) {
							foreach ( $get_submissions_files as $file ) {

								$file_data = maybe_unserialize( $file['files'] );
								$name      = basename( $file_data['file'] );
								$file_size = filesize( $file_data['file'] );
								$file_size = ohmylms_format_file_size( $file_size );
								?>

								<!-- <li>
									<div class="ohmylms-single-resource-info">
										<span class="resource-icon">
											<?php include OHMYLMS_DIR . '/assets/images/icon/file-icon.php'; ?>
										</span>
										<span class="resource-name"><?php echo esc_html( $name ); ?></span>
										<span class="resource-size"><?php echo esc_html( $file_size ); ?></span>
									</div>

									<a href="#" class="resource-action" title="Remove">
										<?php include OHMYLMS_DIR . '/assets/images/icon/delete-icon.php'; ?>
									</a>
								</li> -->
								<?php
							}
						}
						?>
					</ul>

					<span class="submission-max-file-limit-alert" style="display: none">
						<?php printf( /* translators: %s: text value. */
						__( 'File size exceeds the allowed limit of : %sMB', 'ohmylms' ), $assignment->get_max_file_size_limit() ); ?>
					</span>
				</div>
				<input type="hidden" name="action" value="save_assignment_submission_file">
				<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $assignment->get_id() ); ?>">
				<input type="hidden" name="ohmylms_assignment_id" value="<?php echo esc_attr( $assignment->get_id() ); ?>">
				<input type="hidden" name="ohmylms_assignment_deadline" value="<?php echo esc_attr( $deadline ); ?>">
				<input type="hidden" name="course_id" value="<?php echo esc_attr( ohmylms_get_course_by_content_id( $assignment->get_id() ) ); ?>">

				<div class="ohmylms-form-group submission-submit">
					<p class="assignment-submit-alert">
						<svg width="16" height="16" fill="none" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path fill="#FF6F6F" d="M8 16a8 8 0 118-8 8.009 8.009 0 01-8 8zM8 1.333A6.667 6.667 0 1014.667 8 6.674 6.674 0 008 1.333z"/><path fill="#FF6F6F" d="M8 12.667A.667.667 0 017.333 12V6.667a.667.667 0 111.334 0V12a.667.667 0 01-.667.667zM8.667 4a.667.667 0 11-1.334 0 .667.667 0 011.334 0z"/></svg>
						<span>
							<?php echo esc_html__( 'Add necessary error message', 'ohmylms' ); ?>
						</span>
					</p>

					<button class="ohmylms-button" type="submit" disabled>
						<?php echo esc_html__( 'Submit Assignment', 'ohmylms' ); ?>
					</button>
				</div>
				
			</form>

		</div>

	</div>

<?php endwhile; // end of the loop. ?>
