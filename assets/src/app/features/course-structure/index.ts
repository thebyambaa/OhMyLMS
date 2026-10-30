import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const courses: FeatureDefinition = {
	id: 'course-structures',
	title: __( 'Courses', 'ohmylms' ),
	path: '/content-hub/courses',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-course-structure" */ './CoursesPage'
			)
	),
};
export const courseEditor: FeatureDefinition = {
	id: 'course-structure-editor',
	title: __( 'Course structure', 'ohmylms' ),
	path: '/content-hub/courses/:id',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-course-structure" */ './CourseEditorPage'
			)
	),
};
