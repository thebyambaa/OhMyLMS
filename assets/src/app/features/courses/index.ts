import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Course list at `#/courses`. Authors see their own courses; the server decides per course. */
export const courses: FeatureDefinition = {
	id: 'courses',
	title: __( 'Courses', 'ohmylms' ),
	path: '/courses',
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-courses" */ './CoursesPage'
			)
	),
};

/**
 * Course settings editor at `#/courses/<id>`. A separate hidden route entry because the registry does
 * not accept wildcard paths; it shares the chunk with the list.
 */
export const courseEditor: FeatureDefinition = {
	id: 'courses-editor',
	title: __( 'Course', 'ohmylms' ),
	path: '/courses/:id',
	extensionRoute: '/course-edit/:id/:step?/:subStep?',
	hidden: true,
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-courses" */ './CourseEditorPage'
			)
	),
};
