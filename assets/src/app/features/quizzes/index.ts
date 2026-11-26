import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Quiz list at `#/quizzes`. */
export const quizzes: FeatureDefinition = {
	id: 'quizzes',
	title: __( 'Quizzes', 'ohmylms' ),
	path: '/quizzes',
	group: 'content',
	tab: 'quizzes',
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-quizzes" */ './QuizzesPage'
			)
	),
};

/**
 * Quiz settings editor at `#/quizzes/<id>`. A separate hidden route entry because the registry does not
 * accept wildcard paths; it shares the chunk with the list.
 */
export const quizEditor: FeatureDefinition = {
	id: 'quizzes-editor',
	title: __( 'Quiz settings', 'ohmylms' ),
	path: '/quizzes/:id',
	group: 'content',
	tab: 'quizzes',
	extensionRoute: '/quiz-edit/:id',
	hidden: true,
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-quizzes" */ './QuizEditorPage'
			)
	),
};

/**
 * Quiz reports overview at `#/reports/quizzes`: attempt figures across every quiz the user may grade.
 * Replaces the legacy `#/quiz-report` address, so extensions see that route.
 */
export const quizReports: FeatureDefinition = {
	id: 'quiz-reports',
	title: __( 'Quiz reports', 'ohmylms' ),
	path: '/reports/quizzes',
	group: 'content',
	tab: 'quizzes',
	section: 'reports',
	extensionRoute: '/quiz-report',
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-quiz-reports" */ './QuizReportsPage'
			)
	),
};

/** Attempts review and grading at `#/quizzes/<id>/attempts`. */
export const quizAttempts: FeatureDefinition = {
	id: 'quizzes-attempts',
	title: __( 'Quiz attempts', 'ohmylms' ),
	path: '/quizzes/:id/attempts',
	group: 'content',
	tab: 'quizzes',
	view: 'attempts',
	extensionRoute: '/quiz-report/:id',
	hidden: true,
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-quizzes" */ './AttemptsPage'
			)
	),
};
