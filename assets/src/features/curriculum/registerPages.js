import { __ } from '@wordpress/i18n';

/**
 * Curriculum admin page, registered through the public extension registry.
 * @param registry
 * @param components
 */
export function registerCurriculumPages( registry, components ) {
	const { CurriculumPage } = components;
	registry.registerAdminPage( 'curriculum', {
		label: __( 'Curriculum', 'ohmylms' ),
		render: CurriculumPage,
		priority: 2,
	} );
}
