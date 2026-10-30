import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/** Overview at `#/dashboard`: totals, earnings chart, recent and popular courses. */
export const dashboard: FeatureDefinition = {
	id: 'dashboard',
	title: __( 'Dashboard', 'ohmylms' ),
	path: '/dashboard',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-dashboard" */ './DashboardPage'
			)
	),
};

/** Earnings report at `#/earnings`: totals, chart, orders and course report. */
export const earnings: FeatureDefinition = {
	id: 'earnings',
	title: __( 'Earnings', 'ohmylms' ),
	path: '/earnings',
	extensionRoute: '/earnings-report',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-dashboard" */ './EarningsPage'
			)
	),
};

/**
 * Report of one course at `#/reports/courses/<id>`. A separate route entry because the registry
 * does not accept wildcard paths; it shares the chunk with the other dashboard routes.
 */
export const courseReport: FeatureDefinition = {
	id: 'course-report',
	title: __( 'Course report', 'ohmylms' ),
	path: '/reports/courses/:id',
	extensionRoute: '/course/:id/report',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-dashboard" */ './CourseReportPage'
			)
	),
};

/** Roster of one course at `#/reports/courses/<id>/students`: enrol by e-mail, bulk unenrol, reminders. */
export const courseRoster: FeatureDefinition = {
	id: 'course-roster',
	title: __( 'Course roster', 'ohmylms' ),
	path: '/reports/courses/:id/students',
	extensionRoute: '/courses/:id/students',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-dashboard" */ './CourseRosterPage'
			)
	),
};
