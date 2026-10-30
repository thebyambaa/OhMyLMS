import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const curriculum: FeatureDefinition = {
	id: 'curriculum',
	title: __( 'Curriculum', 'ohmylms' ),
	path: '/content-hub/curriculum',
	group: 'content',
	tab: 'curriculum',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-curriculum" */ './CurriculumPage'
			)
	),
};
export const skillMap: FeatureDefinition = {
	id: 'curriculum-skill-map',
	title: __( 'Skill map', 'ohmylms' ),
	path: '/content-hub/curriculum/skill-map',
	group: 'content',
	tab: 'curriculum',
	section: 'skill-map',
	extensionRoute: '/content-hub/curriculum/skill-map',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-curriculum-map" */ './SkillMapPage'
			)
	),
};
export const subjectProfiles: FeatureDefinition = {
	id: 'curriculum-subjects',
	title: __( 'Subject profiles', 'ohmylms' ),
	path: '/content-hub/curriculum/subjects',
	group: 'content',
	tab: 'curriculum',
	section: 'subjects',
	extensionRoute: '/content-hub/curriculum/subjects',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-curriculum-subjects" */ './SubjectProfilesPage'
			)
	),
};
/** Syllabus comparison (groups, roadmaps, shared skills, learner audit) at `#/content-hub/curriculum/compare`. */
export const curriculumCompare: FeatureDefinition = {
	id: 'curriculum-compare',
	title: __( 'Compare syllabuses', 'ohmylms' ),
	path: '/content-hub/curriculum/compare',
	group: 'content',
	tab: 'curriculum',
	view: 'compare',
	params: { tab: 'compare' },
	extensionRoute: '/content-hub/curriculum/compare',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-curriculum-compare" */ './ComparePage'
			)
	),
};
export const bundles: FeatureDefinition = {
	id: 'bundles',
	title: __( 'Course bundles', 'ohmylms' ),
	path: '/content-hub/bundles',
	group: 'content',
	tab: 'curriculum',
	section: 'bundles',
	extensionRoute: '/content-hub/bundles',
	capability: 'manage_options',
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-bundles" */ './BundlesPage'
			)
	),
};
export { CoursePlacement } from './CoursePlacement';
export { readTree } from './api';
