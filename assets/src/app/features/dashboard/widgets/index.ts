import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { createWidgetRegistry } from './registry';
import type { DashboardWidgetDefinition } from './registry';

/**
 * Built-in dashboard widgets. To add one, create its component file in this folder (render it inside
 * `WidgetCard`, load data with `useWidget`) and add one entry here; the Dashboard page needs no change.
 * Every entry shares the dashboard chunk unless it names its own.
 */
export const builtInWidgets: readonly DashboardWidgetDefinition[] = [
	{
		id: 'needs-attention',
		title: __( 'Needs attention', 'ohmylms' ),
		order: 10,
		size: 'full',
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './NeedsAttentionWidget'
				)
		),
	},
	{
		id: 'pulse',
		title: __( 'Pulse', 'ohmylms' ),
		order: 20,
		size: 'full',
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './PulseWidget'
				)
		),
	},
	{
		id: 'at-risk',
		title: __( 'At-risk learners', 'ohmylms' ),
		order: 40,
		size: 'narrow',
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './AtRiskWidget'
				)
		),
	},
	{
		id: 'mastery-heatmap',
		title: __( 'Mastery heatmap', 'ohmylms' ),
		order: 50,
		size: 'wide',
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './MasteryHeatmapWidget'
				)
		),
	},
	{
		id: 'review-sets',
		title: __( 'Recommended next assignments', 'ohmylms' ),
		order: 60,
		size: 'narrow',
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './ReviewSetsWidget'
				)
		),
	},
	{
		id: 'overview',
		title: __( 'Earnings and courses', 'ohmylms' ),
		order: 30,
		size: 'full',
		isAvailable: ( context ) => context.scope.commerce,
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './OverviewWidget'
				)
		),
	},
	{
		id: 'insights',
		title: __( 'Insights', 'ohmylms' ),
		order: 15,
		size: 'full',
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './InsightsWidget'
				)
		),
	},
	{
		id: 'course-health',
		title: __( 'Course health', 'ohmylms' ),
		order: 70,
		size: 'wide',
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './CourseHealthWidget'
				)
		),
	},
	{
		id: 'activity',
		title: __( 'Happening now', 'ohmylms' ),
		order: 80,
		size: 'wide',
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './ActivityWidget'
				)
		),
	},
	{
		id: 'site-health',
		title: __( 'Site health', 'ohmylms' ),
		order: 90,
		size: 'narrow',
		isAvailable: ( context ) => context.siteAdmin && context.scope.commerce,
		component: lazy(
			() =>
				import(
					/* webpackChunkName: "admin-app-dashboard" */ './SiteHealthWidget'
				)
		),
	},
];

/** The registry the Dashboard page renders. */
export const dashboardWidgets = createWidgetRegistry( builtInWidgets );
