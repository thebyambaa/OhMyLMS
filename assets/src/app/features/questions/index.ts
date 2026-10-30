import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Question bank list at `#/content-hub/question-bank`. */
export const questions: FeatureDefinition = {
	id: 'questions',
	title: __( 'Question bank', 'ohmylms' ),
	path: '/content-hub/question-bank',
	capability: 'edit_posts',
	extensionRoute: '/content-hub/question-bank',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-questions" */ './QuestionsPage'
			)
	),
};

/**
 * Single-question editor at `#/content-hub/question-bank/<id>`. A separate hidden route entry because
 * the registry does not accept wildcard paths; it shares the chunk with the list. Extensions see the
 * original admin route `/question-edit/:id`.
 */
export const questionEditor: FeatureDefinition = {
	id: 'questions-editor',
	title: __( 'Question', 'ohmylms' ),
	path: '/content-hub/question-bank/:id',
	hidden: true,
	capability: 'edit_posts',
	extensionRoute: '/question-edit/:id',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-questions" */ './QuestionEditorPage'
			)
	),
};
