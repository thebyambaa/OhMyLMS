import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

const redirect = lazy(
	() =>
		import(
			/* webpackChunkName: "admin-app-course-structure" */ './CourseRedirect'
		)
);

/** Old Content Hub course routes; each redirects into the merged course editor at `#/courses`. */
export const courses: FeatureDefinition = {
	id: 'course-structures',
	title: __( 'Course structure', 'ohmylms' ),
	path: '/content-hub/courses',
	capability: 'edit_posts',
	hidden: true,
	component: redirect,
};
export const courseEditor: FeatureDefinition = {
	id: 'course-structure-editor',
	title: __( 'Course structure', 'ohmylms' ),
	path: '/content-hub/courses/:id',
	extensionRoute: '/course-edit/:id/:step?/:subStep?',
	capability: 'edit_posts',
	hidden: true,
	component: redirect,
};
export const courseSettings: FeatureDefinition = {
	id: 'course-settings-editor',
	title: __( 'Course settings', 'ohmylms' ),
	path: '/content-hub/courses/:id/settings',
	extensionRoute: '/course-edit/:id/:step?/:subStep?',
	capability: 'edit_posts',
	hidden: true,
	component: redirect,
};
