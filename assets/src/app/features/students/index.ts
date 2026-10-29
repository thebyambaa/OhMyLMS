import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const students: FeatureDefinition = {
	id: 'students',
	title: __( 'Students', 'ohmylms' ),
	path: '/students',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-students" */ './StudentsPage'
			)
	),
};
