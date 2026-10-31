<?php
/**
 * Classes: class selector, assigned work, mastery summary and approved connections.
 * Override in yourtheme/ohmylms/student-hub/classes.php.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Performance\Snapshot;
use OhMyLMS\Student\Hub;

defined( 'ABSPATH' ) || exit;

$learner_classes = array_column( Hub::learner_classes(), null, 'id' );
$class_id        = absint( get_query_var( 'hub_class', 0 ) );
$class_id        = isset( $learner_classes[ $class_id ] ) ? $class_id : ( array_key_first( $learner_classes ) ?? 0 );
$work_page       = max( 1, absint( get_query_var( 'current_page', 1 ) ) );
?>
<?php if ( 'join' === $view ) : ?>
	<section class="oml-hub-banner">
		<h1><?php esc_html_e( 'Join a class', 'ohmylms' ); ?></h1>
		<p><?php esc_html_e( 'Use the invitation your teacher gave you.', 'ohmylms' ); ?></p>
	</section>
	<div class="oml-hub-card">
		<h2><?php esc_html_e( 'Accept an invitation', 'ohmylms' ); ?></h2>
		<p><?php esc_html_e( 'Paste the OhMyLMS invitation link or invitation code you received. Sign in using the email address it was sent to.', 'ohmylms' ); ?></p>
		<form data-hub-invitation data-endpoint="<?php echo esc_url( rest_url( 'ohmylms/v1/account-hub/invitations/accept' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-error="<?php esc_attr_e( 'Could not accept this invitation. Check the link and try again.', 'ohmylms' ); ?>" data-success="<?php esc_attr_e( 'Invitation accepted. Open My classes, or use Switch role at the top to open your new workspace.', 'ohmylms' ); ?>">
			<label for="hub-invitation"><?php esc_html_e( 'Invitation link or code', 'ohmylms' ); ?></label>
			<input id="hub-invitation" name="invitation" type="text" value="<?php echo esc_attr( is_string( get_query_var( 'ohmylms_invite' ) ) ? get_query_var( 'ohmylms_invite' ) : '' ); ?>" required autocomplete="off" spellcheck="false">
			<button type="submit" class="oml-hub-button"><?php esc_html_e( 'Accept invitation', 'ohmylms' ); ?></button>
			<p role="status" aria-live="polite" data-invitation-status></p>
		</form>
		<p><a href="<?php echo esc_url( Hub::url( 'classes' ) ); ?>"><?php esc_html_e( 'Open My classes', 'ohmylms' ); ?></a></p>
	</div>
	<?php return; ?>
<?php endif; ?>

<?php if ( ! $class_id ) : ?>
	<section class="oml-hub-hero oml-hub-hero-center">
		<h1><?php esc_html_e( 'Your classes will appear here', 'ohmylms' ); ?></h1>
		<p><?php esc_html_e( 'Join with your teacher’s invitation. You can explore courses and learn on your own right away.', 'ohmylms' ); ?></p>
		<p>
			<a class="oml-hub-button" href="<?php echo esc_url( Hub::url( 'library' ) ); ?>"><?php esc_html_e( 'Go to library', 'ohmylms' ); ?></a>
			<a class="oml-hub-textlink" href="<?php echo esc_url( Hub::url( 'classes', 'join' ) ); ?>"><?php esc_html_e( 'Join a class', 'ohmylms' ); ?></a>
		</p>
	</section>
	<?php ohmylms_get_template( 'student-hub/family-connections.php' ); ?>
	<?php return; ?>
<?php endif; ?>

<form class="oml-hub-class-switcher" method="get" action="<?php echo esc_url( Hub::base_url() ); ?>">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
		<input type="hidden" name="page_id" value="<?php echo esc_attr( Hub::settings()['page_id'] ); ?>">
	<?php endif; ?>
	<input type="hidden" name="hub_section" value="classes"><input type="hidden" name="hub_view" value="<?php echo esc_attr( $view ); ?>">
	<label class="screen-reader-text" for="hub-class"><?php esc_html_e( 'Class', 'ohmylms' ); ?></label>
	<select name="hub_class" id="hub-class">
		<?php foreach ( $learner_classes as $learner_class ) : ?>
			<option value="<?php echo esc_attr( $learner_class['id'] ); ?>" <?php selected( $class_id, $learner_class['id'] ); ?>><?php echo esc_html( $learner_class['name'] . ( $learner_class['school_name'] ? ' · ' . $learner_class['school_name'] : '' ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<button type="submit" class="oml-hub-button"><?php esc_html_e( 'Open class', 'ohmylms' ); ?></button>
	<a class="oml-hub-textlink" href="<?php echo esc_url( Hub::url( 'classes', 'join' ) ); ?>"><?php esc_html_e( 'Join a class', 'ohmylms' ); ?></a>
</form>

<?php if ( 'connections' === $view ) : ?>
	<?php $supporters = Hub::supporters( $class_id ); ?>
	<p class="oml-hub-crumb"><a class="oml-hub-crumb-link" href="<?php echo esc_url( add_query_arg( 'hub_class', $class_id, Hub::url( 'classes' ) ) ); ?>">&larr; <?php esc_html_e( 'Assignments', 'ohmylms' ); ?></a></p>
	<div class="oml-hub-cards">
		<?php
		foreach ( array(
			'teachers'  => __( 'Teachers', 'ohmylms' ),
			'guardians' => __( 'Parents / guardians', 'ohmylms' ),
		) as $kind => $label ) :
			?>
			<article class="oml-hub-card"><h2><?php echo esc_html( $label ); ?></h2>
				<?php if ( ! $supporters[ $kind ] ) : ?>
					<p><?php esc_html_e( 'No active connections to display.', 'ohmylms' ); ?></p>
				<?php endif; ?>
				<?php foreach ( $supporters[ $kind ] as $supporter_name ) : ?>
					<p><?php echo esc_html( $supporter_name ); ?></p>
				<?php endforeach; ?>
			</article>
		<?php endforeach; ?>
	</div>
	<?php ohmylms_get_template( 'student-hub/family-connections.php' ); ?>
	<?php return; ?>
<?php endif; ?>

<?php
$work      = \OhMyLMS\Schools\Service::assignments( 0, 0, 0, $work_page, $class_id );
$mastery   = Snapshot::counts( get_current_user_id() );
$levels    = array(
	'attempted'  => __( 'Attempted', 'ohmylms' ),
	'familiar'   => __( 'Familiar', 'ohmylms' ),
	'proficient' => __( 'Proficient', 'ohmylms' ),
	'mastered'   => __( 'Mastered', 'ohmylms' ),
);
$page_link = static function ( $page ) use ( $class_id ) {
	return add_query_arg(
		array(
			'hub_class'    => $class_id,
			'current_page' => $page,
		),
		Hub::url( 'classes' )
	);
};
?>
<div class="oml-hub-classgrid">
	<div class="oml-hub-classmain">
		<?php if ( ! $work ) : ?>
			<section class="oml-hub-hero oml-hub-hero-center">
				<h1><?php esc_html_e( 'You have no assignments to complete.', 'ohmylms' ); ?></h1>
				<p><?php esc_html_e( 'Explore the library or check another class.', 'ohmylms' ); ?></p>
				<p><a class="oml-hub-button" href="<?php echo esc_url( Hub::url( 'library' ) ); ?>"><?php esc_html_e( 'Go to library', 'ohmylms' ); ?></a></p>
			</section>
		<?php else : ?>
			<h1 class="oml-hub-classtitle"><?php esc_html_e( 'Assignments', 'ohmylms' ); ?></h1>
			<ul class="oml-hub-assignments">
				<?php foreach ( $work as $assignment ) : ?>
					<?php
					$due     = $assignment['due_at'] ? strtotime( $assignment['due_at'] . ' UTC' ) : 0;
					$done    = ! empty( $assignment['completed_at'] );
					$overdue = $due && ! $done && $due < time();
					?>
					<li class="oml-hub-assignment <?php echo $done ? 'is-done' : ''; ?>">
						<div>
							<strong class="oml-hub-assignment-title"><?php echo esc_html( $assignment['title'] ); ?></strong>
							<small>
								<?php echo esc_html( $done ? __( 'Completed', 'ohmylms' ) : __( 'To do', 'ohmylms' ) ); ?>
								<?php if ( $due ) : ?>
									<?php /* translators: %s: assignment due date. */ echo esc_html( ' · ' . sprintf( __( 'Due %s', 'ohmylms' ), wp_date( get_option( 'date_format' ), $due ) ) ); ?>
								<?php endif; ?>
							</small>
						</div>
						<?php if ( $overdue ) : ?>
							<span class="oml-hub-badge is-overdue"><?php esc_html_e( 'Overdue', 'ohmylms' ); ?></span>
						<?php endif; ?>
						<a class="oml-hub-button" href="<?php echo esc_url( $assignment['url'] ); ?>"><?php esc_html_e( 'Open assignment', 'ohmylms' ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
			<nav class="oml-hub-pager" aria-label="<?php esc_attr_e( 'Assignment pages', 'ohmylms' ); ?>">
				<?php if ( $work_page > 1 ) : ?>
					<a class="oml-hub-pill" href="<?php echo esc_url( $page_link( $work_page - 1 ) ); ?>">&larr; <?php esc_html_e( 'Previous page', 'ohmylms' ); ?></a>
				<?php endif; ?>
				<?php if ( 50 === count( $work ) ) : ?>
					<a class="oml-hub-pill" href="<?php echo esc_url( $page_link( $work_page + 1 ) ); ?>"><?php esc_html_e( 'Next page', 'ohmylms' ); ?> &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
	<aside class="oml-hub-classaside" aria-label="<?php esc_attr_e( 'Your progress', 'ohmylms' ); ?>">
		<section class="oml-hub-card">
			<h2><?php esc_html_e( 'Mastery learning', 'ohmylms' ); ?></h2>
			<ul class="oml-hub-legend">
				<?php foreach ( $levels as $key => $label ) : ?>
					<li class="is-<?php echo esc_attr( $key ); ?>"><span class="oml-hub-swatch" aria-hidden="true"></span><span><?php echo esc_html( $label ); ?></span><strong class="oml-hub-legend-count"><?php echo esc_html( $mastery[ $key ] ); ?></strong></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<section class="oml-hub-card">
			<h2><?php esc_html_e( 'Your class', 'ohmylms' ); ?></h2>
			<p><?php echo esc_html( $learner_classes[ $class_id ]['name'] ); ?><br><small><?php echo esc_html( $learner_classes[ $class_id ]['school_name'] ); ?></small></p>
			<p><a class="oml-hub-textlink" href="<?php echo esc_url( add_query_arg( 'hub_class', $class_id, Hub::url( 'classes', 'connections' ) ) ); ?>"><?php esc_html_e( 'Teachers and guardians', 'ohmylms' ); ?></a></p>
		</section>
	</aside>
</div>
