import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/**
 * Exam list at `#/exams`, with `?action=new` and `?action=import`. Replaces the legacy Content Hub
 * Exams tab, so extensions see its route.
 */
export const exams: FeatureDefinition = {
	id: 'exams',
	title: __( 'Exams', 'ohmylms' ),
	path: '/exams',
	extensionRoute: '/content-hub/exams',
	capability: 'edit_posts',
	component: lazy(
		() => import( /* webpackChunkName: "admin-app-exams" */ './ExamsPage' )
	),
};

/** Exam editor at `#/exams/<id>` (settings and composition). The legacy admin had no such screen. */
export const examEditor: FeatureDefinition = {
	id: 'exams-editor',
	title: __( 'Exam', 'ohmylms' ),
	path: '/exams/:id',
	hidden: true,
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-exams" */ './ExamEditorPage'
			)
	),
};

/** Import review at `#/exams/imports/<jobId>`, the legacy Exams tab's review dialog as a page. */
export const examImportReview: FeatureDefinition = {
	id: 'exams-import-review',
	title: __( 'Import review', 'ohmylms' ),
	path: '/exams/imports/:jobId',
	hidden: true,
	capability: 'edit_posts',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-exams" */ './ImportReviewPage'
			)
	),
};
