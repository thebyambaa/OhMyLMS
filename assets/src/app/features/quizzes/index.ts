import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Quiz list at `#/quizzes`. */
export const quizzes: FeatureDefinition = {
	id: 'quizzes',
	title: __( 'Quizzes', 'ohmylms' ),
	path: '/quizzes',
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

/** Attempts review and grading at `#/quizzes/<id>/attempts`. */
export const quizAttempts: FeatureDefinition = {
	id: 'quizzes-attempts',
	title: __( 'Quiz attempts', 'ohmylms' ),
	path: '/quizzes/:id/attempts',
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
