import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

/**
 * The scoped dashboard (`page=ohmylms`): scope picker, period switcher and the widgets of
 * `widgets/index.ts`. Site administrators, school administrators and teachers (`ohmylms_view_dashboard`,
 * includes/Dashboard/Access.php); every widget endpoint scopes its figures on the server.
 */
export const dashboard: FeatureDefinition = {
	id: 'dashboard',
	title: __( 'Dashboard', 'ohmylms' ),
	path: '/dashboard',
	group: 'dashboard',
	capability: 'ohmylms_view_dashboard',
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
	group: 'commerce',
	tab: 'earnings',
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
	group: 'content',
	tab: 'courses',
	view: 'report',
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
	group: 'content',
	tab: 'courses',
	view: 'students',
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
