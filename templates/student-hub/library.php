<?php
/**
 * Browse subjects: expandable subject tiles listing published courses.
 * Override in yourtheme/ohmylms/student-hub/library.php.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\Hub;
use OhMyLMS\Student\LearnerCatalog;

defined( 'ABSPATH' ) || exit;

$hub_subject = absint( get_query_var( 'hub_subject', 0 ) );
if ( $hub_subject ) {
	echo \OhMyLMS\Student\Subjects::render( $hub_subject, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Subject template escapes its output by context.
	return;
}

$hub_query = get_query_var( 'hub_q', '' );
$hub_query = is_string( $hub_query ) ? sanitize_text_field( $hub_query ) : '';
$subjects  = LearnerCatalog::subjects( $hub_query, $config['library_types'] ? $config['library_types'] : array( 'subject' ) );
$bundles   = $config['library_bundles'] ? LearnerCatalog::bundles( $hub_query ) : array();

/**
 * Print a group of courses, then its nested groups.
 *
 * @param array $node Subject node.
 * @param int   $depth Nesting depth below the subject tile.
 */
$hub_render_group = static function ( array $node, $depth ) use ( &$hub_render_group ) {
	if ( $depth > 0 ) {
		echo '<h3 class="oml-hub-group-title">' . esc_html( $node['name'] ) . '</h3>';
	}
	if ( $node['courses'] ) {
		echo '<ul class="oml-hub-course-links">';
		foreach ( $node['courses'] as $course ) {
			echo '<li><a class="oml-hub-course-link" href="' . esc_url( $course['url'] ) . '">' . esc_html( $course['title'] ) . '</a></li>';
		}
		echo '</ul>';
	}
	foreach ( $node['children'] as $child ) {
		$hub_render_group( $child, $depth + 1 );
	}
};
?>
<section class="oml-hub-banner">
	<h1><?php esc_html_e( 'Browse subjects', 'ohmylms' ); ?></h1>
	<p><?php esc_html_e( 'Select a subject to view courses.', 'ohmylms' ); ?></p>
</section>
<form class="oml-hub-search" method="get" action="<?php echo esc_url( Hub::base_url() ); ?>" role="search">
	<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
		<input type="hidden" name="page_id" value="<?php echo esc_attr( $config['page_id'] ); ?>">
	<?php endif; ?>
	<input type="hidden" name="hub_section" value="library">
	<label class="screen-reader-text" for="hub-library-search"><?php esc_html_e( 'Search courses', 'ohmylms' ); ?></label>
	<input id="hub-library-search" type="search" name="hub_q" value="<?php echo esc_attr( $hub_query ); ?>" placeholder="<?php esc_attr_e( 'Search courses', 'ohmylms' ); ?>">
	<button type="submit" class="oml-hub-button"><?php esc_html_e( 'Search', 'ohmylms' ); ?></button>
</form>
<h2 class="oml-hub-library-heading"><?php echo esc_html( '' === $hub_query ? __( 'All subjects', 'ohmylms' ) : __( 'Search results', 'ohmylms' ) ); ?></h2>
<?php if ( ! $subjects && ! $bundles ) : ?>
	<div class="oml-hub-empty">
		<h2><?php echo esc_html( '' === $hub_query ? __( 'No subjects yet', 'ohmylms' ) : __( 'No courses match your search', 'ohmylms' ) ); ?></h2>
		<p><?php echo esc_html( '' === $hub_query ? __( 'Subjects appear here once courses are published and placed in the curriculum.', 'ohmylms' ) : __( 'Try a different word, or clear the search to see every subject.', 'ohmylms' ) ); ?></p>
		<?php if ( '' !== $hub_query ) : ?>
			<a class="oml-hub-button" href="<?php echo esc_url( Hub::url( 'library' ) ); ?>"><?php esc_html_e( 'Show all subjects', 'ohmylms' ); ?></a>
		<?php endif; ?>
	</div>
<?php else : ?>
	<div class="oml-hub-subjects">
		<?php foreach ( $subjects as $subject ) : ?>
			<details class="oml-hub-subject" data-tone="<?php echo esc_attr( $subject['id'] % 6 ); ?>" <?php echo '' !== $hub_query ? 'open' : ''; ?>>
				<summary class="oml-hub-disclosure">
					<span class="oml-hub-subject-icon" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $subject['name'], 0, 1 ) ) ); ?></span>
					<span class="oml-hub-subject-name"><?php echo esc_html( $subject['name'] ); ?></span>
					<span class="oml-hub-subject-count"><?php /* translators: %d: number of courses. */ echo esc_html( sprintf( _n( '%d course', '%d courses', $subject['total'], 'ohmylms' ), $subject['total'] ) ); ?></span>
				</summary>
				<div class="oml-hub-subject-body">
					<p><a class="oml-hub-button" href="<?php echo esc_url( \OhMyLMS\Student\Subjects::url( $subject['id'], true ) ); ?>"><?php esc_html_e( 'View subject', 'ohmylms' ); ?></a></p>
					<?php $hub_render_group( $subject, 0 ); ?>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
	<?php if ( $bundles ) : ?>
		<h2 class="oml-hub-library-heading"><?php esc_html_e( 'Course bundles', 'ohmylms' ); ?></h2>
		<div class="oml-hub-subjects">
			<?php foreach ( $bundles as $bundle ) : ?>
				<details class="oml-hub-subject" data-kind="bundle" data-tone="<?php echo esc_attr( ( $bundle['id'] + 3 ) % 6 ); ?>" <?php echo '' !== $hub_query ? 'open' : ''; ?>>
					<summary class="oml-hub-disclosure">
						<span class="oml-hub-subject-icon" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $bundle['title'], 0, 1 ) ) ); ?></span>
						<span class="oml-hub-subject-name"><?php echo esc_html( $bundle['title'] ); ?></span>
						<span class="oml-hub-subject-count"><?php /* translators: %d: number of courses. */ echo esc_html( sprintf( _n( '%d course', '%d courses', $bundle['total'], 'ohmylms' ), $bundle['total'] ) ); ?></span>
					</summary>
					<div class="oml-hub-subject-body">
						<ul class="oml-hub-course-links">
							<?php foreach ( $bundle['courses'] as $course ) : ?>
								<li><a class="oml-hub-course-link" href="<?php echo esc_url( $course['url'] ); ?>"><?php echo esc_html( $course['title'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
<?php endif; ?>
