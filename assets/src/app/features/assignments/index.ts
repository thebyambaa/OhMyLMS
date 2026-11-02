import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Assignment list at `#/assignments`. */
export const assignments: FeatureDefinition = {
	id: 'assignments',
	title: __( 'Assignments', 'ohmylms' ),
	path: '/assignments',
	group: 'content',
	tab: 'assignments',
	extensionRoute: '/assignments',
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-assignments" */ './AssignmentsPage'
			)
	),
};

/**
 * Assignment editor at `#/assignments/<id>`. A separate hidden route entry because the registry does
 * not accept wildcard paths; it shares the chunk with the list.
 */
export const assignmentEditor: FeatureDefinition = {
	id: 'assignments-editor',
	title: __( 'Assignment', 'ohmylms' ),
	path: '/assignments/:id',
	group: 'content',
	tab: 'assignments',
	extensionRoute: '/assignment-edit/:id',
	hidden: true,
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-assignments" */ './AssignmentEditorPage'
			)
	),
};

/** Submission list at `#/assignments/<id>/submissions`. */
export const assignmentSubmissions: FeatureDefinition = {
	id: 'assignments-submissions',
	title: __( 'Assignment submissions', 'ohmylms' ),
	path: '/assignments/:id/submissions',
	group: 'content',
	tab: 'assignments',
	view: 'submissions',
	extensionRoute: '/assignment-report/:id',
	hidden: true,
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-assignments" */ './SubmissionsPage'
			)
	),
};

/** Grading view at `#/assignments/<id>/submissions/<submissionId>`. */
export const assignmentGrading: FeatureDefinition = {
	id: 'assignments-grading',
	title: __( 'Grade submission', 'ohmylms' ),
	path: '/assignments/:id/submissions/:submissionId',
	group: 'content',
	tab: 'assignments',
	view: 'submissions',
	extensionRoute: '/assignment-report/:id/grade-assignment/:assignmentId',
	hidden: true,
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-assignments" */ './GradingPage'
			)
	),
};
