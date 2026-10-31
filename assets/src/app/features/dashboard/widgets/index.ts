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
];

/** The registry the Dashboard page renders. */
export const dashboardWidgets = createWidgetRegistry( builtInWidgets );
