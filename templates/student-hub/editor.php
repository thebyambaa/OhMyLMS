<?php
/**
 * Administrator editor for the unified student workspace.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\Hub;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap oml-hub-editor">
	<h1><?php esc_html_e( 'Student Home', 'ohmylms' ); ?></h1>
	<p><?php esc_html_e( 'One learning workspace. Choose its navigation, arrange Home cards, and set your branding.', 'ohmylms' ); ?></p>
	<p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( get_permalink( $config['page_id'] ) ); ?>"><?php esc_html_e( 'Preview Student Home', 'ohmylms' ); ?></a> <code>[ohmylms_student_hub]</code></p>
	<?php if ( isset( $_GET['saved'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ohmylms_student_hub_saved' ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Student Home settings saved.', 'ohmylms' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ohmylms_student_hub">
		<?php wp_nonce_field( 'ohmylms_student_hub' ); ?>
		<div class="oml-hub-editor-grid">
			<section class="oml-hub-editor-panel">
				<h2><?php esc_html_e( 'Workspace settings', 'ohmylms' ); ?></h2>
				<label><input type="checkbox" name="hub[enabled]" value="1" <?php checked( $config['enabled'] ); ?>> <?php esc_html_e( 'Use Student Home for existing student links', 'ohmylms' ); ?></label>
				<p><?php esc_html_e( 'Existing student pages stay available when this is turned off.', 'ohmylms' ); ?></p>
				<label for="hub-title"><?php esc_html_e( 'Workspace title', 'ohmylms' ); ?></label>
				<input id="hub-title" type="text" name="hub[title]" value="<?php echo esc_attr( $config['title'] ); ?>" maxlength="100">
				<label for="hub-accent"><?php esc_html_e( 'Accent color', 'ohmylms' ); ?></label>
				<input id="hub-accent" type="color" name="hub[accent]" value="<?php echo esc_attr( $config['accent'] ); ?>">
				<label><input type="checkbox" name="hub[accent_brand]" value="1" <?php checked( $config['accent_brand'] ); ?>> <?php esc_html_e( 'Use the site brand color (ignores the color above)', 'ohmylms' ); ?></label>
				<label for="hub-layout"><?php esc_html_e( 'Navigation layout', 'ohmylms' ); ?></label>
				<select id="hub-layout" name="hub[layout]"><option value="sidebar" <?php selected( $config['layout'], 'sidebar' ); ?>><?php esc_html_e( 'Left sidebar', 'ohmylms' ); ?></option><option value="top" <?php selected( $config['layout'], 'top' ); ?>><?php esc_html_e( 'Top navigation', 'ohmylms' ); ?></option></select>
				<label for="hub-default"><?php esc_html_e( 'Default section', 'ohmylms' ); ?></label>
				<select id="hub-default" name="hub[default]">
					<?php foreach ( Hub::sections() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $config['default'], $key ); ?>><?php echo esc_html( $config['labels'][ $key ] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p><label><input type="checkbox" name="hub[commerce]" value="1" <?php checked( $config['commerce'] ); ?>> <?php esc_html_e( 'Show purchases, billing, and available membership features', 'ohmylms' ); ?></label></p>
				<p><label><input type="checkbox" name="hub[personalize]" value="1" <?php checked( $config['personalize'] ); ?>> <?php esc_html_e( 'Let students choose their Home theme', 'ohmylms' ); ?></label></p>
			</section>
			<section class="oml-hub-editor-panel">
				<h2><?php esc_html_e( 'Navigation', 'ohmylms' ); ?></h2>
				<p><?php esc_html_e( 'Drag rows or use the arrow buttons to reorder. Account stays available.', 'ohmylms' ); ?></p>
				<ul data-hub-sortable>
					<?php foreach ( array_unique( array_merge( $config['tabs'], array_keys( Hub::sections() ) ) ) as $key ) : ?>
						<li draggable="true" data-sort-row>
							<span class="oml-hub-drag" aria-hidden="true">⠿</span>
							<label><input type="checkbox" name="hub[tabs][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $config['tabs'], true ) ); ?> <?php disabled( 'account', $key ); ?>><span class="screen-reader-text"><?php echo esc_html( Hub::sections()[ $key ] ); ?></span></label>
							<?php
							if ( 'account' === $key ) :
								?>
								<input type="hidden" name="hub[tabs][]" value="account"><?php endif; ?>
							<input type="text" name="hub[labels][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $config['labels'][ $key ] ); ?>" aria-label="<?php echo esc_attr( Hub::sections()[ $key ] ); ?>" maxlength="50">
							<button type="button" data-move="up" aria-label="<?php esc_attr_e( 'Move up', 'ohmylms' ); ?>">↑</button><button type="button" data-move="down" aria-label="<?php esc_attr_e( 'Move down', 'ohmylms' ); ?>">↓</button>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
			<section class="oml-hub-editor-panel">
				<h2><?php esc_html_e( 'Wording', 'ohmylms' ); ?></h2>
				<?php $hub_terms = \OhMyLMS\Utility\Terminology::settings(); ?>
				<p><label><input type="checkbox" name="terms[enabled]" value="1" <?php checked( $hub_terms['enabled'] ); ?>> <?php esc_html_e( 'Call chapters something else across the whole LMS', 'ohmylms' ); ?></label></p>
				<label for="hub-term-singular"><?php esc_html_e( 'Singular word', 'ohmylms' ); ?></label>
				<input id="hub-term-singular" type="text" name="terms[singular]" value="<?php echo esc_attr( $hub_terms['singular'] ); ?>" maxlength="30">
				<label for="hub-term-plural"><?php esc_html_e( 'Plural word', 'ohmylms' ); ?></label>
				<input id="hub-term-plural" type="text" name="terms[plural]" value="<?php echo esc_attr( $hub_terms['plural'] ); ?>" maxlength="30">
				<p><?php esc_html_e( 'Only displayed words change, in the course editor, admin screens and learner pages. Course data, links and integrations are untouched, and unchecking restores "chapter". Languages that already translate this word keep their own translation.', 'ohmylms' ); ?></p>
			</section>
			<section class="oml-hub-editor-panel">
				<h2><?php esc_html_e( 'Browse subjects', 'ohmylms' ); ?></h2>
				<p><?php esc_html_e( 'Subjects created in Subject profiles always appear in the Library. Choose any additional curriculum levels to show as tiles. Courses and deeper levels sit inside each tile.', 'ohmylms' ); ?></p>
				<?php
				$hub_types = array_diff( array_unique( array_merge( \OhMyLMS\Curriculum\Items::types(), array_column( \OhMyLMS\Curriculum\Items::all(), 'item_type' ), $config['library_types'] ) ), array( 'roadmap', 'subject' ) );
				?>
				<ul class="oml-hub-checklist">
					<?php foreach ( $hub_types as $hub_type ) : ?>
						<li><label><input type="checkbox" name="hub[library_types][]" value="<?php echo esc_attr( $hub_type ); ?>" <?php checked( in_array( $hub_type, $config['library_types'], true ) ); ?>> <?php echo esc_html( ucfirst( $hub_type ) ); ?></label></li>
					<?php endforeach; ?>
				</ul>
				<p><?php esc_html_e( 'Courses that sit only above a chosen level are not listed. Roadmaps are never listed.', 'ohmylms' ); ?></p>
			</section>
			<section class="oml-hub-editor-panel">
				<h2><?php esc_html_e( 'Home cards', 'ohmylms' ); ?></h2>
				<p><?php esc_html_e( 'Show or hide cards, then drag them into your preferred order.', 'ohmylms' ); ?></p>
				<ul data-hub-sortable>
					<?php foreach ( array_unique( array_merge( $config['cards'], array_keys( Hub::cards() ) ) ) as $key ) : ?>
						<li draggable="true" data-sort-row><span class="oml-hub-drag" aria-hidden="true">⠿</span><label><input type="checkbox" name="hub[cards][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $config['cards'], true ) ); ?>> <?php echo esc_html( Hub::cards()[ $key ] ); ?></label><button type="button" data-move="up" aria-label="<?php esc_attr_e( 'Move up', 'ohmylms' ); ?>">↑</button><button type="button" data-move="down" aria-label="<?php esc_attr_e( 'Move down', 'ohmylms' ); ?>">↓</button></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</div>
		<p role="status" data-hub-sort-status data-message="<?php esc_attr_e( 'Order updated. Save changes to apply it.', 'ohmylms' ); ?>"></p>
		<?php submit_button( __( 'Save changes', 'ohmylms' ) ); ?>
	</form>
	<section class="oml-hub-editor-panel oml-hub-preview">
		<h2><?php esc_html_e( 'Preview saved layout', 'ohmylms' ); ?></h2>
		<p><button type="button" class="button" data-preview-size="desktop"><?php esc_html_e( 'Desktop', 'ohmylms' ); ?></button> <button type="button" class="button" data-preview-size="mobile"><?php esc_html_e( 'Mobile', 'ohmylms' ); ?></button></p>
		<iframe title="<?php esc_attr_e( 'Student Home preview', 'ohmylms' ); ?>" src="<?php echo esc_url( get_permalink( $config['page_id'] ) ); ?>" loading="lazy" data-hub-preview></iframe>
	</section>
</div>
