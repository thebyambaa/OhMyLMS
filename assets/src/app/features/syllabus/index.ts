import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const syllabusEditor: FeatureDefinition = {
	id: 'syllabus-editor',
	title: __( 'Syllabus editor', 'ohmylms' ),
	path: '/content-hub/curriculum/syllabus/:id',
	group: 'content',
	tab: 'curriculum',
	view: 'syllabus',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-syllabus" */ './SyllabusPage'
			)
	),
};
