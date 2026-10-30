import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const courses: FeatureDefinition = {
	id: 'course-structures',
	title: __( 'Course structure', 'ohmylms' ),
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
	extensionRoute: '/course-edit/:id/:step?/:subStep?',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-course-structure" */ './CourseEditorPage'
			)
	),
};
export const courseSettings: FeatureDefinition = {
	id: 'course-settings-editor',
	title: __( 'Course settings', 'ohmylms' ),
	path: '/content-hub/courses/:id/settings',
	extensionRoute: '/course-edit/:id/:step?/:subStep?',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-course-settings" */ './CourseSettingsPage'
			)
	),
};
