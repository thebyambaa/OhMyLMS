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
			code: '',
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
/** The outline with a unit (and its own group) and an empty unit inside it. */
export const nested: SyllabusOutline = {
	...outline,
	contents: [
		...outline.contents,
		{
			id: 6,
			parent_id: 5,
			name: 'Geometry',
			code: 'U1',
			depth: 1,
			groups: [
				{
					id: 8,
					item_id: 6,
					position: 0,
					name: 'Angles',
					code: 'G',
					description: '',
					skills: [
						{
							term_id: 12,
							name: 'Measure angles',
							code: 'G.1',
							description: '',
						},
					],
				},
			],
		},
		{
			id: 13,
			parent_id: 6,
			name: 'Circles',
			code: '',
			depth: 2,
			groups: [],
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
