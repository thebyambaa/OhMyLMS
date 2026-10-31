import type { NeedsAttention } from '../widgets/needsAttention';
import type { Pulse } from '../widgets/pulse';
import type {
	DashboardScope,
	ScopeList,
	WidgetContext,
	WidgetEnvelope,
} from './model';

/** Test fixtures only; production components never import this module. */
export const allScope: DashboardScope = {
	id: 'all',
	type: 'all',
	label: 'All schools',
	schoolId: 0,
	schoolName: '',
	commerce: true,
};
export const northScope: DashboardScope = {
	id: 'school:3',
	type: 'school',
	label: 'North Academy',
	schoolId: 3,
	schoolName: 'North Academy',
	commerce: false,
};
export const algebraClass: DashboardScope = {
	id: 'class:9',
	type: 'class',
	label: 'Algebra 8A',
	schoolId: 3,
	schoolName: 'North Academy',
	commerce: false,
};
export const clubClass: DashboardScope = {
	id: 'class:12',
	type: 'class',
	label: 'Chess club',
	schoolId: 0,
	schoolName: '',
	commerce: false,
};

export const adminScopes: ScopeList = {
	scopes: [ allScope, northScope, algebraClass, clubClass ],
	defaultScope: 'all',
	siteAdmin: true,
};
export const schoolAdminScopes: ScopeList = {
	scopes: [ northScope, algebraClass ],
	defaultScope: 'school:3',
	siteAdmin: false,
};
export const teacherScopes: ScopeList = {
	scopes: [ algebraClass ],
	defaultScope: 'class:9',
	siteAdmin: false,
};

export function context(
	scope: DashboardScope = northScope,
	period: WidgetContext[ 'period' ] = 30
): WidgetContext {
	return { scope, period, siteAdmin: scope.commerce };
}

export function envelope< T >(
	widget: string,
	scope: string,
	data: T,
	days = 30
): WidgetEnvelope< T > {
	return {
		widget,
		scope,
		period: {
			days,
			start: '2026-10-02',
			end: '2026-10-31',
			previousStart: '2026-09-02',
			previousEnd: '2026-10-01',
		},
		generatedAt: '2026-10-31T08:00:00Z',
		data,
	};
}

export const attention: NeedsAttention = {
	items: [
		{
			key: 'grading',
			urgency: 82,
			tone: 'warning',
			title: '12 submissions waiting more than 3 days to be graded',
			detail: 'Essay · oldest submitted Oct 26, 2026',
			count: 12,
			action: {
				label: 'Grade now',
				route: '/assignments/55/submissions',
			},
		},
		{
			key: 'stalling_course:41',
			urgency: 66,
			tone: 'warning',
			title: 'Intro to Algebra is stalling',
			detail: '15% of 40 learners finish · 58% stop at “Lesson 6”',
			count: 34,
			action: null,
		},
		{
			key: 'missed_question:301',
			urgency: 54,
			tone: 'info',
			title: 'Question missed by 71% of learners',
			detail: '“Q7” in SAT Prep · 24 learners answered',
			count: 17,
			action: {
				label: 'Edit question',
				route: '/content-hub/question-bank/301',
			},
		},
	],
	snoozed: 1,
	gradingDays: 3,
};

export const emptyAttention: NeedsAttention = {
	items: [],
	snoozed: 0,
	gradingDays: 3,
};

const days = Array.from(
	{ length: 7 },
	( _, index ) => `2026-10-${ String( 25 + index ).padStart( 2, '0' ) }`
);

export const pulse: Pulse = {
	dates: days,
	currency: { code: 'USD', symbol: '$', decimals: 2, position: 'left' },
	metrics: [
		{
			id: 'revenue',
			format: 'money',
			value: 4820,
			previous: 4463,
			series: [ 500, 700, 600, 900, 650, 720, 750 ],
		},
		{
			id: 'active_learners',
			format: 'number',
			value: 248,
			previous: 234,
			series: [ 30, 41, 38, 45, 44, 22, 28 ],
		},
		{
			id: 'completions',
			format: 'number',
			value: 0,
			previous: 0,
			series: [ 0, 0, 0, 0, 0, 0, 0 ],
		},
		{
			id: 'renewals_at_risk',
			format: 'number',
			value: 4,
			previous: 2,
			series: [ 0, 1, 0, 2, 0, 1, 0 ],
			onHold: 3,
		},
	],
};
