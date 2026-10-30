import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const lessons: FeatureDefinition = {
	id: 'lessons',
	title: __( 'Lessons', 'ohmylms' ),
	path: '/content-hub/lessons',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-lessons" */ './LessonsPage'
			)
	),
};
export const lessonEditor: FeatureDefinition = {
	id: 'lesson-editor',
	title: __( 'Lesson editor', 'ohmylms' ),
	path: '/content-hub/lessons/:id',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-lessons" */ './LessonEditorPage'
			)
	),
};
