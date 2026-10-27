import { __ } from '@wordpress/i18n';

/**
 * Curriculum and Course Bundle admin pages, registered through the public extension registry.
 * @param registry
 * @param components
 */
export function registerCurriculumPages( registry, components ) {
	const { CurriculumPage, BundlesPage } = components;
	registry.registerAdminPage( 'curriculum', {
		label: __( 'Curriculum', 'ohmylms' ),
		render: CurriculumPage,
		priority: 2,
	} );
	registry.registerAdminPage( 'bundles', {
		label: __( 'Course Bundles', 'ohmylms' ),
		render: BundlesPage,
		priority: 2,
	} );
}
