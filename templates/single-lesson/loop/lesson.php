<?php
/**
 * The template for displaying lesson's Assignment content
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-assignment.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Lesson $lesson
 * @global \OhMyLMS\Data\Student $student
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! $lesson ) {
	return;
}

$lesson_id  = $lesson->get_id();
$current_id = get_the_ID();
$student    = new \OhMyLMS\Data\Student( get_current_user_id() );
$get_type   = $lesson->get_type();
$is_active  = $lesson_id == $current_id ? 'active' : '';


$type = $lesson->get_type();

$content_type = get_post_meta( $lesson_id, '_content_type', true );

if ( 'session' === $content_type ) {
	$type = 'session';
}

switch ( $type ) {
	case 'video':
		$icon_class = 'type-video';
		break;
	case 'audio':
		$icon_class = 'type-audio';
		break;
	case 'quiz':
		$icon_class = 'type-quiz';
		break;
	case 'assignment':
		$icon_class = 'type-assignment';
		break;
	case 'session':
		$icon_class = 'type-session';
		break;
	default:
		$icon_class = 'type-text';
}

$is_checked = $student->maybe_completed( $lesson_id ) ? 'checked' : '';

$_course_id       = ohmylms_get_course_id_by_content_id( $lesson_id );
$is_lesson_locked = false;
if ( $_course_id && function_exists( 'apply_filters' ) ) {
	$is_lesson_locked = apply_filters( 'ohmylms_is_lesson_locked', false, $lesson_id, $_course_id, get_current_user_id() );
}
?>


<li class="lesson-item 
<?php
echo $icon_class . ' ';
echo $is_active;
?>
<?php echo esc_attr( $is_lesson_locked ? ' lesson-locked' : '' ); ?>">
	<a href="<?php echo esc_url( $is_lesson_locked ? '#' : esc_url( $lesson->get_permalink() ) ); ?>"
		lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
		<?php
		if ( $is_lesson_locked ) {
			echo 'onclick="return false;" style="cursor:not-allowed;opacity:0.6;"'; }
		?>
		>
		<?php echo esc_html( $lesson->get_name() ); ?>
	</a>

	<?php if ( $is_lesson_locked ) : ?>
		<span style="position:absolute;right:17px;top:50%;transform:translateY(-50%);z-index:1;line-height:1;">
			<?php include OHMYLMS_DIR . '/assets/images/icon/lock-icon.php'; ?>
		</span>
	<?php else : ?>
		<span class="lesson-status <?php echo esc_attr( $is_checked ? 'checked' : '' ); ?>">
			<svg width="18" height="18" fill="none" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><rect width="18" height="18" fill="#19AA32" rx="9"/><path fill="#fff" d="M12.373 5.818l-4.628 4.628-2.122-2.121a.818.818 0 00-1.157 1.157l2.7 2.7a.818.818 0 001.157 0l5.207-5.207a.818.818 0 10-1.157-1.157z"/></svg>
		</span>
	<?php endif; ?>
</li>
