<?php
/**
 * Template for displaying course filters.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/filters.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
if ( isset( $atts ) && is_array( $atts ) && ! empty( $atts ) && isset( $atts['show_filter'] ) ) {
	$is_filter_enabled = $atts['show_filter'];
	$filter_data       = get_option( 'ohmylms_archive_page_filters', array() );
	if ( ! is_array( $filter_data ) || empty( $filter_data ) ) {
		$filter_data = array( 'category', 'tag', 'price_type', 'difficulty_level' );
	}
} else {
	$is_filter_enabled = get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' );
	$filter_data       = get_option( 'ohmylms_archive_page_filters', array() );
}

if ( 'no' === $is_filter_enabled ) {
	return;
}


$items = array(
	'category'         => array(),
	'tag'              => array(),
	'price_type'       => array(),
	'difficulty_level' => array(),
);
?>

<div class="ohmylms-filter-accordion ohmylms-default-accordion">
	<?php
	if ( ! empty( $filter_data ) ) {
		foreach ( $filter_data as $filter ) {
			$filter_title = '';
			// The "category" and "tag" filters keep their stored keys but now list the curriculum and
			// Course Bundles. Only entries with published courses are shown; slugs are c<id> / t<id>.
			if ( 'category' == $filter ) {
				$filter_title = __( 'Curriculum', 'ohmylms' );
				foreach ( \OhMyLMS\Curriculum\Placement::public_items() as $entry ) {
					$items['category'][] = array(
						'name' => str_repeat( '— ', (int) $entry['depth'] ) . $entry['name'],
						'slug' => $entry['slug'],
					);
				}
			}

			if ( 'tag' == $filter ) {
				$filter_title = __( 'Course bundle', 'ohmylms' );
				foreach ( \OhMyLMS\Curriculum\Placement::public_bundles() as $entry ) {
					$items['tag'][] = array(
						'name' => $entry['title'],
						'slug' => $entry['slug'],
					);
				}
			}

			if ( 'price_type' == $filter ) {
				$filter_title        = __( 'Price Type', 'ohmylms' );
				$items['price_type'] = array(
					array(
						'name' => 'Paid',
						'slug' => 'paid',
					),
					array(
						'name' => 'Free',
						'slug' => 'free',
					),
				);
			}

			if ( 'difficulty_level' == $filter ) {
				$filter_title              = __( 'Level', 'ohmylms' );
				$items['difficulty_level'] = array(
					array(
						'name' => 'All Levels',
						'slug' => 'all',
					),
					array(
						'name' => 'Beginner',
						'slug' => 'beginner',
					),
					array(
						'name' => 'Experience',
						'slug' => 'experience',
					),
					array(
						'name' => 'Expert',
						'slug' => 'expert',
					),

				);
			}
			if ( $filter_title && ! empty( $items[ $filter ] ) ) {

				?>
					<div class="ohmylms-accordion-item">
						<div class="ohmylms-accordion-head" aria-expanded="true">
							<span class="ohmylms-accordion-title">
							<?php echo esc_html( $filter_title ); ?>

								<svg width="13" height="7" fill="none" viewBox="0 0 13 7" xmlns="http://www.w3.org/2000/svg"><path fill="#A1A1AA" stroke="#A1A1AA" stroke-width=".3" d="M1.146 5.766a.792.792 0 001.124 0L5.896 2.14a.792.792 0 011.124 0l3.626 3.626A.793.793 0 1011.77 4.65L8.136 1.016a2.375 2.375 0 00-3.357 0L1.146 4.65a.792.792 0 000 1.116z"/></svg>
							</span>
						</div>

						<div class="ohmylms-accordion-body">
						<?php
						foreach ( $items[ $filter ] as $index => $item ) :
							?>
								
								<label for="<?php echo esc_attr( $filter . $index ); ?>" class="ohmylms-checkbox" >
									<input type="checkbox" name="ohmylms-filter-checkbox" value="" data-type="<?php echo esc_attr( $filter ); ?>" data-slug="<?php echo esc_attr( $item['slug'] ); ?>" id="<?php echo esc_attr( $filter . $index ); ?>" aria-required="true" aria-labelledby="ohmylms-<?php echo esc_attr( $filter . $index ); ?>-filter-label">

									<span class="ohmylms-checkbox-text">
										<span class="checkedbox" aria-hidden="false" id="ohmylms-<?php echo esc_attr( $filter . $index ); ?>-filter-label" tabindex="0" data-target="<?php echo esc_attr( $filter . $index ); ?>">
											<svg width="10" height="8" fill="none" viewBox="0 0 10 8" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.373.818L3.745 5.446 1.623 3.325A.818.818 0 00.466 4.482l2.7 2.7a.818.818 0 001.157 0L9.53 1.975A.818.818 0 008.373.818z"/></svg>
										</span>
									<?php echo esc_html( $item['name'] ); ?>
									</span>
								</label>
								
							<?php
							endforeach;
						?>
						</div>
					</div>
				<?php
			}
		}
	} else {
		?>
			<div class="no-filter">
			<?php echo esc_html__( 'No filters available.', 'ohmylms' ); ?>
			</div>
			<?php
	}
	?>
</div>
