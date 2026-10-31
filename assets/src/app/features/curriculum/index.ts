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
/**
 * Content Hub › Skills › skill workspace sections (`&section=<id>`), all served by one chunk. The
 * screens need the curriculum administrator, unlike the Skills tab itself.
 */
const skillWorkspace = lazy(
	() =>
		import(
			/* webpackChunkName: "admin-app-skill-workspace" */ './SkillWorkspace'
		)
);
export const skillWorkspaceMappings: FeatureDefinition = {
	id: 'skill-workspace-mappings',
	title: __( 'Combined mappings', 'ohmylms' ),
	path: '/content-hub/skills/mappings',
	group: 'content',
	tab: 'skills',
	section: 'mappings',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspaceAiRuns: FeatureDefinition = {
	id: 'skill-workspace-ai-runs',
	title: __( 'AI runs', 'ohmylms' ),
	path: '/content-hub/skills/ai-runs',
	group: 'content',
	tab: 'skills',
	section: 'ai-runs',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspaceContentLinks: FeatureDefinition = {
	id: 'skill-workspace-content-links',
	title: __( 'Content links', 'ohmylms' ),
	path: '/content-hub/skills/content-links',
	group: 'content',
	tab: 'skills',
	section: 'content-links',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspacePlacement: FeatureDefinition = {
	id: 'skill-workspace-placement',
	title: __( 'Domain placement', 'ohmylms' ),
	path: '/content-hub/skills/placement',
	group: 'content',
	tab: 'skills',
	section: 'placement',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspaceQuizzes: FeatureDefinition = {
	id: 'skill-workspace-skill-quizzes',
	title: __( 'Skill quizzes', 'ohmylms' ),
	path: '/content-hub/skills/skill-quizzes',
	group: 'content',
	tab: 'skills',
	section: 'skill-quizzes',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspaceProvenance: FeatureDefinition = {
	id: 'skill-workspace-provenance',
	title: __( 'Provenance review', 'ohmylms' ),
	path: '/content-hub/skills/provenance',
	group: 'content',
	tab: 'skills',
	section: 'provenance',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
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
