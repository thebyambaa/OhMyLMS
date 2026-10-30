import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const curriculum: FeatureDefinition = {
	id: 'curriculum',
	title: __( 'Curriculum', 'ohmylms' ),
	path: '/content-hub/curriculum',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-curriculum" */ './CurriculumPage'
			)
	),
};
export { CoursePlacement } from './CoursePlacement';
export { readTree } from './api';
