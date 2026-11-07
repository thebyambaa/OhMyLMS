import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/**
 * Curriculum (`#/curriculum`): the curriculum tree beside the open item's skills, courses and linked
 * question banks and quizzes. The first tab of the Curriculum page; the older screens are its Tools tab.
 */
export const curriculumWorkspace: FeatureDefinition = {
	id: 'curriculum-workspace',
	title: __( 'Curriculum', 'ohmylms' ),
	path: '/curriculum',
	group: 'curriculum',
	tab: 'workspace',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-curriculum-workspace" */ './CurriculumWorkspace'
			)
	),
};
export const curriculum: FeatureDefinition = {
	id: 'curriculum',
	title: __( 'Curriculum', 'ohmylms' ),
	path: '/content-hub/curriculum',
	group: 'curriculum',
	tab: 'tools',
	section: 'tree',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-curriculum" */ './CurriculumPage'
			)
	),
};
/**
 * The read-only skill map is also open to school administrators and teachers, whose learner overlays
 * the server limits to their schools and classes; editing stays in the administrator-only workspace.
 */
export const skillMap: FeatureDefinition = {
	id: 'curriculum-skill-map',
	title: __( 'Skill map', 'ohmylms' ),
	path: '/content-hub/curriculum/skill-map',
	group: 'curriculum',
	tab: 'tools',
	section: 'skill-map',
	extensionRoute: '/content-hub/curriculum/skill-map',
	capability: 'ohmylms_view_dashboard',
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
	group: 'curriculum',
	tab: 'tools',
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
	group: 'curriculum',
	tab: 'tools',
	section: 'mappings',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspaceAiRuns: FeatureDefinition = {
	id: 'skill-workspace-ai-runs',
	title: __( 'AI runs', 'ohmylms' ),
	path: '/content-hub/skills/ai-runs',
	group: 'curriculum',
	tab: 'tools',
	section: 'ai-runs',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspaceContentLinks: FeatureDefinition = {
	id: 'skill-workspace-content-links',
	title: __( 'Content links', 'ohmylms' ),
	path: '/content-hub/skills/content-links',
	group: 'curriculum',
	tab: 'tools',
	section: 'content-links',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspacePlacement: FeatureDefinition = {
	id: 'skill-workspace-placement',
	title: __( 'Domain placement', 'ohmylms' ),
	path: '/content-hub/skills/placement',
	group: 'curriculum',
	tab: 'tools',
	section: 'placement',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspaceQuizzes: FeatureDefinition = {
	id: 'skill-workspace-skill-quizzes',
	title: __( 'Skill quizzes', 'ohmylms' ),
	path: '/content-hub/skills/skill-quizzes',
	group: 'curriculum',
	tab: 'tools',
	section: 'skill-quizzes',
	capability: 'manage_options',
	hidden: true,
	component: skillWorkspace,
};
export const skillWorkspaceProvenance: FeatureDefinition = {
	id: 'skill-workspace-provenance',
	title: __( 'Provenance review', 'ohmylms' ),
	path: '/content-hub/skills/provenance',
	group: 'curriculum',
	tab: 'tools',
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
	group: 'curriculum',
	tab: 'tools',
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
	group: 'curriculum',
	tab: 'tools',
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
