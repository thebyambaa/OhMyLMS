import { STEP_IDS, type StepId, type StepStatus } from './types';

/** Test fixtures only; production components never import this module. */

/**
 * Build a server state (the raw JSON shape).
 *
 * @param statuses  Status per step; others are pending.
 * @param overrides Top-level members to replace.
 * @return Raw state as the REST API returns it.
 */
export function rawState(
	statuses: Partial< Record< StepId, StepStatus > > = {},
	overrides: Record< string, unknown > = {}
) {
	const steps = STEP_IDS.map( ( id ) => ( {
		id,
		status: statuses[ id ] ?? 'pending',
		skippable: 'welcome' !== id && 'experience' !== id,
		available: 'import' !== id,
	} ) );
	const current = steps.find( ( step ) => 'pending' === step.status );
	return {
		version: 1,
		status: 'in_progress',
		current_step: current ? current.id : null,
		steps,
		answers: { experience: null, niche: null, topics: [] },
		preferences: {
			currency: 'USD',
			currency_pos: 'left',
			price_num_decimals: 2,
		},
		starter_pages: [
			{
				key: 'checkout',
				title: 'OhMy Checkout',
				slug: 'ohmylms-checkout',
				page_id: 0,
				status: 'missing',
			},
			{
				key: 'student_dashboard',
				title: 'My Dashboard',
				slug: 'my-dashboard',
				page_id: 9,
				status: 'adoptable',
			},
			{
				key: 'course',
				title: 'All Course',
				slug: 'ohmylms-all-courses',
				page_id: 4,
				status: 'ready',
			},
		],
		started_at: '2026-10-30T05:33:03Z',
		completed_at: null,
		...overrides,
	};
}

export const everyStepDone: Partial< Record< StepId, StepStatus > > = {
	welcome: 'done',
	experience: 'done',
	preferences: 'done',
	niche: 'skipped',
	pages: 'done',
	import: 'skipped',
};

export const noResult = { changed: [], pages: [] };

export const currencies = [
	{ code: 'USD', name: 'United States dollar', symbol: '$' },
	{ code: 'EUR', name: 'Euro', symbol: 'EUR' },
];
