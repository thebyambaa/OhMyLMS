import type { Outline } from './model';

/** Test data only. */
export const outline: Outline = {
	course_id: 12,
	revision: 'read-token',
	units: [
		{ id: 8, title: 'Algebra', number: '1', description: 'Existing unit' },
		{ id: 9, title: 'Geometry', number: '2', description: '' },
	],
	program: {
		mode: 'blended',
		recognize_prior: true,
		evidence_days: 90,
		bank_ids: [ 3 ],
		groups: [
			{
				id: 'group-1',
				chapter_id: 8,
				title: 'Equations',
				skill_ids: [ 7 ],
			},
		],
		outcomes: [
			{
				term_id: 7,
				chapter_id: 8,
				target: 'proficient',
				required: false,
			},
		],
		items: [
			{
				id: 'activity-1',
				type: 'lesson',
				content_id: 20,
				chapter_id: 8,
				group_id: 'group-1',
				required: true,
				name: 'First lesson',
				skill_ids: [ 7 ],
			},
		],
	},
};
