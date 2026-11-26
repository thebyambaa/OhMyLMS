<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * List screens for the Teaching tabs that are WordPress post types (math references, downloads).
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Prints a post type's list inside the Teaching page, so those tabs share its address, heading and tab row.
 * Adding and editing still use the WordPress editor of the post type.
 */
final class TeachingList {

	const PER_PAGE = 20;

	/**
	 * Math references tab.
	 */
	public static function math_references() {
		self::render( 'ohmylms-reference', __( 'Add math reference', 'ohmylms' ) );
	}

	/**
	 * Downloads tab.
	 */
	public static function downloads() {
		self::render( 'ohmylms-download', __( 'Add protected download', 'ohmylms' ) );
	}

	/**
	 * Print the list of a post type.
	 *
	 * @param string $post_type    Post type.
	 * @param string $add_label    Label of the add link.
	 */
	private static function render( $post_type, $add_label ) {
		$object = get_post_type_object( $post_type );
		if ( ! $object || ! current_user_can( $object->cap->edit_posts ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => self::PER_PAGE,
				'paged'          => $paged,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$pages = (int) $query->max_num_pages;
		?>
		<p>
			<?php if ( current_user_can( $object->cap->create_posts ) ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $post_type ) ); ?>"><?php echo esc_html( $add_label ); ?></a>
			<?php endif; ?>
		</p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Title', 'ohmylms' ); ?></th>
					<th scope="col" style="width:140px"><?php esc_html_e( 'Status', 'ohmylms' ); ?></th>
					<th scope="col" style="width:180px"><?php esc_html_e( 'Updated', 'ohmylms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $query->have_posts() ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'Nothing here yet.', 'ohmylms' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $query->posts as $post ) : ?>
					<?php
					$status = get_post_status_object( $post->post_status );
					$title  = '' !== trim( $post->post_title ) ? $post->post_title : __( '(no title)', 'ohmylms' );
					?>
					<tr>
						<td>
							<?php if ( current_user_can( 'edit_post', $post->ID ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>"><strong><?php echo esc_html( $title ); ?></strong></a>
							<?php else : ?>
								<strong><?php echo esc_html( $title ); ?></strong>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $status ? $status->label : $post->post_status ); ?></td>
						<td><?php echo esc_html( get_the_modified_date( '', $post ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $pages,
								'prev_text' => '&lsaquo;',
								'next_text' => '&rsaquo;',
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}
}
