import { lazy } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../types';

export const students: FeatureDefinition = {
	id: 'students',
	title: __( 'Students', 'ohmylms' ),
	path: '/students',
	group: 'accounts',
	tab: 'students',
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-students" */ './StudentsPage'
			)
	),
};

/** One student's results at `#/students/<id>/report` (quiz attempts, assignments, skills). */
export const studentReport: FeatureDefinition = {
	id: 'student-report',
	title: __( 'Student report', 'ohmylms' ),
	path: '/students/:id/report',
	group: 'accounts',
	tab: 'students',
	view: 'report',
	extensionRoute: '/students/:id/report',
	hidden: true,
	capability: 'manage_options',
	component: lazy(
		() =>
			import(
				/* webpackChunkName: "admin-app-students" */ './StudentReportPage'
			)
	),
};
