import type { SyllabusOutline } from './model';

/** Test data only. */
export const outline: SyllabusOutline = {
	syllabus: { id: 5, name: 'Mathematics syllabus' },
	course: { id: 12, title: 'Mathematics course', published: false },
	contents: [
		{
			id: 5,
			parent_id: 0,
			name: 'Mathematics syllabus',
			depth: 0,
			groups: [
				{
					id: 7,
					item_id: 5,
					position: 0,
					name: 'Algebra',
					code: 'A',
					description: '',
					skills: [
						{
							term_id: 10,
							name: 'Solve equations',
							code: 'A.1',
							description: 'Notes',
						},
						{
							term_id: 11,
							name: 'Use expressions',
							code: 'A.2',
							description: '',
						},
					],
				},
			],
		},
	],
};
export const report = {
	review_revision: 'review-token',
	valid: true,
	applied: false,
	errors: [],
	warnings: [],
	rows: { used: 1, skipped: 0 },
	contents: { create: 0, update: 0 },
	groups: { create: 1, update: 0 },
	skills: { create: 1, update: 0 },
};
