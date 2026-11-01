import type { SyllabusOutline } from './model';

/** Test data only. */
export const outline: SyllabusOutline = {
	syllabus: {
		id: 5,
		name: 'Mathematics syllabus',
		code: '0580',
		version: '2026',
		description: '',
		item_type: 'syllabus',
	},
	course: {
		id: 12,
		title: 'Mathematics course',
		published: false,
		mode: 'skill-based',
	},
	settings: {
		subject: 'Mathematics',
		grade: '',
		language: 'English',
		categories: [ 'Core', 'Extended' ],
		category_styles: {
			Core: { icon: 'calculator', color: '#1e88e5' },
		},
	},
	roadmap_sources: [],
	requirements: [
		{ term_id: 10, required: false, target: 'proficient' },
		{ term_id: 11, required: false, target: 'proficient' },
	],
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
/** A topic folder with a group holding two skills, beside the syllabus root's own group. */
export const preview = {
	name: 'Mathematics syllabus',
	revision: 'source-revision',
	topics: [
		{
			id: 5,
			name: 'Mathematics syllabus',
			depth: 0,
			lessons: [
				{
					id: 7,
					title: 'A · Algebra',
					skills: [
						{
							term_id: 10,
							name: 'Solve equations',
							code: 'A.1',
							objective: 'Solve linear equations',
						},
						{
							term_id: 11,
							name: 'Use expressions',
							code: 'A.2',
							objective: 'Use expressions',
						},
					],
				},
			],
		},
		{
			id: 6,
			name: 'Geometry',
			depth: 1,
			lessons: [
				{
					id: 8,
					title: 'G · Angles',
					skills: [
						{
							term_id: 12,
							name: 'Measure angles',
							code: 'G.1',
							objective: 'Measure angles',
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
