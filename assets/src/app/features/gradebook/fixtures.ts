import type {
	Cell,
	CellDetail,
	CourseGrid,
	GradebookContext,
	LearnerRow,
	MasteryView,
	RollupView,
	RosterView,
	Transcript,
} from './types';

export const SCALE = [
	{ letter: 'A', min: 90 },
	{ letter: 'B', min: 80 },
	{ letter: 'C', min: 70 },
	{ letter: 'D', min: 60 },
	{ letter: 'F', min: 0 },
];

export const context: GradebookContext = {
	scope: {
		id: 'all',
		type: 'all',
		label: 'All schools',
		school_id: 0,
		school_name: '',
	},
	scopes: [
		{
			id: 'all',
			type: 'all',
			label: 'All schools',
			school_id: 0,
			school_name: '',
		},
		{
			id: 'school:1',
			type: 'school',
			label: 'Alpha',
			school_id: 1,
			school_name: 'Alpha',
		},
		{
			id: 'class:7',
			type: 'class',
			label: 'Grade 8A',
			school_id: 1,
			school_name: 'Alpha',
		},
	],
	courses: [
		{ id: 12, title: 'Algebra' },
		{ id: 13, title: 'Geometry' },
	],
	classes: [ { id: 7, name: 'Grade 8A', school_name: 'Alpha' } ],
	scale: SCALE,
	site_admin: true,
};

export function cell( overrides: Partial< Cell > = {} ): Cell {
	const base: Cell = {
		state: 'score',
		score: 8,
		max: 10,
		percent: 80,
		overridden: false,
		version: 0,
		computed: { state: 'score', score: 8, max: 10, percent: 80 },
	};
	return { ...base, ...overrides };
}

function row(
	id: number,
	name: string,
	cells: LearnerRow[ 'cells' ],
	extra: Partial< LearnerRow > = {}
): LearnerRow {
	return {
		id,
		name,
		status: 'active',
		source: 'roster',
		sources: [ 'roster' ],
		via: { id: 7, name: 'Grade 8A' },
		rosters: [ { id: 7, name: 'Grade 8A' } ],
		total: {
			percent: 79.5,
			letter: 'C',
			computed_percent: 79.5,
			mode: 'weighted',
			weights_class: 0,
		},
		counts: { missing: 0, pending: 0, excused: 0, overridden: 0 },
		cells,
		...extra,
	};
}

export const grid: CourseGrid = {
	course: { id: 12, title: 'Algebra' },
	items: [
		{
			id: 101,
			title: 'Linear quiz',
			kind: 'quiz',
			category: 'quiz',
			module_id: 5,
			module: 'Equations',
			max: 10,
			audience: 'all',
			class_ids: [],
			due_at: null,
		},
		{
			id: 102,
			title: 'Practice set',
			kind: 'assignment',
			category: 'homework',
			module_id: 5,
			module: 'Equations',
			max: 20,
			audience: 'all',
			class_ids: [],
			due_at: '2026-10-30T09:00:00+00:00',
		},
		{
			id: 103,
			title: 'Retake',
			kind: 'exam',
			category: 'test',
			module_id: 6,
			module: 'Graphs',
			max: 40,
			audience: 'individuals',
			class_ids: [],
			due_at: null,
		},
	],
	all_items: 3,
	modules: [
		{ id: 5, title: 'Equations' },
		{ id: 6, title: 'Graphs' },
	],
	categories: [ 'homework', 'quiz', 'test' ],
	classes: [ { id: 7, name: 'Grade 8A', school_name: 'Alpha' } ],
	weights: {
		course: { weights: { homework: 40, quiz: 30, test: 30 }, version: 1 },
		overrides: [
			{
				class_id: 7,
				weights: { homework: 20, quiz: 30, test: 50 },
				version: 2,
			},
		],
	},
	scale: SCALE,
	missing_as_zero: true,
	rows: [
		row(
			1,
			'Ana Example',
			{
				'101': cell(),
				'102': cell( {
					state: 'missing',
					score: null,
					max: 20,
					percent: null,
					computed: {
						state: 'missing',
						score: null,
						max: 20,
						percent: null,
					},
				} ),
				'103': cell( {
					state: 'not_assigned',
					score: null,
					max: 40,
					percent: null,
					computed: {
						state: 'not_assigned',
						score: null,
						max: 40,
						percent: null,
					},
				} ),
			},
			{
				total: {
					percent: 32,
					letter: 'F',
					computed_percent: 32,
					mode: 'weighted',
					weights_class: 7,
				},
				counts: { missing: 1, pending: 0, excused: 0, overridden: 0 },
			}
		),
		row(
			2,
			'Bat Demo',
			{
				'101': cell( {
					state: 'score',
					score: 9,
					max: 10,
					percent: 90,
					overridden: true,
					version: 2,
					computed: {
						state: 'pending',
						score: null,
						max: 10,
						percent: null,
					},
				} ),
				'102': cell( {
					state: 'pending',
					score: null,
					max: 20,
					percent: null,
					computed: {
						state: 'pending',
						score: null,
						max: 20,
						percent: null,
					},
				} ),
				'103': cell( {
					state: 'none',
					score: null,
					max: 40,
					percent: null,
					computed: {
						state: 'none',
						score: null,
						max: 40,
						percent: null,
					},
				} ),
			},
			{
				source: 'purchase',
				sources: [ 'purchase' ],
				via: null,
				rosters: [],
				total: {
					percent: 90,
					letter: 'A',
					computed_percent: null,
					mode: 'weighted',
					weights_class: 0,
				},
			}
		),
	],
	summary: {
		learners: 2,
		average: 61,
		items: {
			'101': { average: 85, states: { score: 2 } },
			'102': { average: null, states: { missing: 1, pending: 1 } },
			'103': { average: null, states: { none: 1, not_assigned: 1 } },
		},
	},
	total: 2,
	total_pages: 1,
	page: 1,
	per_page: 50,
	generated_at: '2026-10-31T10:00:00+00:00',
	can: { grade: true, manage_course: true, weight_classes: [ 7 ] },
};

export const detail: CellDetail = {
	course: { id: 12, title: 'Algebra' },
	item: grid.items[ 1 ]!,
	learner: { id: 2, name: 'Bat Demo' },
	cell: {
		...cell( {
			state: 'pending',
			score: null,
			max: 20,
			percent: null,
			computed: { state: 'pending', score: null, max: 20, percent: null },
		} ),
		reason: '',
		updated_by: null,
		updated_at: null,
	},
	total: {
		percent: 90,
		letter: 'A',
		computed_percent: null,
		mode: 'weighted',
	},
	history: [],
};

export const roster: RosterView = {
	class: { id: 7, name: 'Grade 8A', school_id: 1, school_name: 'Alpha' },
	courses: [
		{
			id: 12,
			title: 'Algebra',
			items: grid.items.slice( 0, 2 ),
			average: 61,
		},
	],
	learners: [
		{
			id: 1,
			name: 'Ana Example',
			totals: {
				'12': {
					percent: 32,
					letter: 'F',
					status: 'active',
					source: 'roster',
				},
			},
			cells: {
				'12:101': cell(),
				'12:102': cell( {
					state: 'missing',
					score: null,
					percent: null,
				} ),
			},
		},
		{ id: 3, name: 'Chen Sample', totals: { '12': null }, cells: {} },
	],
	truncated: false,
	scale: SCALE,
};

export const transcript: Transcript = {
	learner: { id: 1, name: 'Ana Example' },
	enrolments: [
		{
			course: { id: 12, title: 'Algebra' },
			items: grid.items,
			weights: { homework: 20, quiz: 30, test: 50 },
			row: grid.rows[ 0 ]!,
		},
	],
	truncated: false,
	mastery: {
		skills: [
			{
				id: 50,
				name: 'Linear equations',
				code: 'ALG.1',
				subject: 'Math',
				percent: 88,
				level: 'proficient',
			},
		],
		levels: [
			{ id: 'mastered', label: 'Mastered', min: 95 },
			{ id: 'proficient', label: 'Proficient', min: 80 },
			{ id: 'familiar', label: 'Familiar', min: 50 },
			{ id: 'attempted', label: 'Attempted', min: null },
			{ id: 'not-started', label: 'Not started', min: null },
		],
	},
	scale: SCALE,
};

export const mastery: MasteryView = {
	skills: [
		{
			id: 50,
			name: 'Linear equations',
			code: 'ALG.1',
			subject: 'Math',
			assessed: 2,
		},
	],
	learners: [
		{
			id: 1,
			name: 'Ana Example',
			source: 'roster',
			rosters: [ { id: 7, name: 'Grade 8A' } ],
			cells: { '50': { percent: 88, level: 'proficient' } },
		},
		{ id: 2, name: 'Bat Demo', source: 'purchase', rosters: [], cells: {} },
	],
	levels: transcript.mastery.levels,
	total: 2,
	total_pages: 1,
	page: 1,
};

export const rollup: RollupView = {
	nodes: [
		{
			type: 'school',
			id: 1,
			name: 'Alpha',
			learners: 6,
			enrolments: 6,
			average: 55.5,
			missing_learners: 3,
			pending: 2,
			children: [
				{
					type: 'class',
					id: 7,
					name: 'Grade 8A',
					learners: 4,
					enrolments: 4,
					average: 60,
					missing_learners: 2,
					pending: 2,
					children: [],
				},
			],
		},
		{
			type: 'individuals',
			id: 0,
			name: 'Individual learners (no class)',
			learners: 3,
			enrolments: 3,
			average: 30,
			missing_learners: 3,
			pending: 0,
			children: [],
		},
	],
	truncated: false,
};
