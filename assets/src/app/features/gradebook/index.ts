import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Capability of every gradebook screen (site administrators and school staff with a scope). */
const CAPABILITY = 'ohmylms_view_gradebook';

/** Content Hub › Gradebook: Course × Students (the tab's own screen). */
export const gradebook: FeatureDefinition = {
	id: 'gradebook',
	title: __( 'Gradebook', 'ohmylms' ),
	path: '/content-hub/gradebook',
	group: 'content',
	tab: 'gradebook',
	capability: CAPABILITY,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-gradebook" */ './CourseStudentsPage'
			)
	),
};

/** Gradebook › Roster × Assignments (`&section=roster`). */
export const gradebookRoster: FeatureDefinition = {
	id: 'gradebook-roster',
	title: __( 'Roster × Assignments', 'ohmylms' ),
	path: '/content-hub/gradebook/roster',
	group: 'content',
	tab: 'gradebook',
	section: 'roster',
	capability: CAPABILITY,
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-gradebook-roster" */ './RosterPage'
			)
	),
};

/** Gradebook › Student transcript (`&section=student`). */
export const gradebookStudent: FeatureDefinition = {
	id: 'gradebook-student',
	title: __( 'Student transcript', 'ohmylms' ),
	path: '/content-hub/gradebook/student',
	group: 'content',
	tab: 'gradebook',
	section: 'student',
	capability: CAPABILITY,
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-gradebook-student" */ './TranscriptPage'
			)
	),
};

/** Gradebook › Skill mastery (`&section=mastery`). */
export const gradebookMastery: FeatureDefinition = {
	id: 'gradebook-mastery',
	title: __( 'Skill mastery', 'ohmylms' ),
	path: '/content-hub/gradebook/mastery',
	group: 'content',
	tab: 'gradebook',
	section: 'mastery',
	capability: CAPABILITY,
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-gradebook-mastery" */ './MasteryPage'
			)
	),
};

/** Gradebook › Org roll-up (`&section=rollup`). */
export const gradebookRollup: FeatureDefinition = {
	id: 'gradebook-rollup',
	title: __( 'Org roll-up', 'ohmylms' ),
	path: '/content-hub/gradebook/rollup',
	group: 'content',
	tab: 'gradebook',
	section: 'rollup',
	capability: CAPABILITY,
	hidden: true,
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-gradebook-rollup" */ './RollupPage'
			)
	),
};
