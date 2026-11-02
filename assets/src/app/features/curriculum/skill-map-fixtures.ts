import type { SkillMap } from './skill-map';

/** Fixtures of the skill map and its overlays for the tests. */

const levels = [
	{ id: 'mastered', label: 'Mastered', min: 95 },
	{ id: 'proficient', label: 'Proficient', min: 80 },
	{ id: 'familiar', label: 'Familiar', min: 50 },
	{ id: 'attempted', label: 'Attempted', min: null },
	{ id: 'not-started', label: 'Not started', min: null },
];
const one = ( level: string ) => ( {
	mastered: 0,
	proficient: 0,
	familiar: 0,
	attempted: 0,
	'not-started': 0,
	[ level ]: 1,
} );

/** A learner overlay as the server sends it. */
export function rawLearnerOverlay() {
	return {
		subject: 'Mathematics',
		target: {
			key: 'learner:12',
			type: 'learner',
			id: 12,
			label: 'Ana Example',
			school: '',
			learners: 1,
			counted: 1,
		},
		skills: [
			{
				id: 1,
				percent: 100,
				level: 'mastered',
				assessed: 1,
				distribution: one( 'mastered' ),
			},
			{
				id: 2,
				percent: 30,
				level: 'attempted',
				assessed: 1,
				distribution: one( 'attempted' ),
			},
			{
				id: 3,
				percent: 80,
				level: 'proficient',
				assessed: 1,
				distribution: one( 'proficient' ),
			},
		],
		counts: {
			mastered: 1,
			proficient: 1,
			familiar: 0,
			attempted: 1,
			'not-started': 1,
		},
		total: 4,
		levels,
		links: {
			transcript: '/content-hub/gradebook/student?scope=all&learner=12',
			report: '/students/12/report',
			gradebook: null as string | null,
		},
		limits: { learners: 1000 },
	};
}

/** A class overlay as the server sends it. */
export function rawClassOverlay() {
	return {
		...rawLearnerOverlay(),
		target: {
			key: 'class:9',
			type: 'class',
			id: 9,
			label: 'Class A1',
			school: 'Alpha School',
			learners: 6,
			counted: 6,
		},
		skills: [
			{
				id: 1,
				percent: 64,
				level: 'familiar',
				assessed: 5,
				distribution: {
					mastered: 2,
					proficient: 1,
					familiar: 1,
					attempted: 1,
					'not-started': 1,
				},
			},
		],
		counts: {
			mastered: 0,
			proficient: 0,
			familiar: 1,
			attempted: 0,
			'not-started': 3,
		},
		links: {
			transcript: null,
			report: null,
			gradebook: '/content-hub/gradebook/mastery?scope=class%3A9',
		},
	};
}

/** Targets as the server sends them. */
export function rawTargets() {
	return {
		groups: [
			{
				key: 'school:3',
				type: 'school',
				label: 'Alpha School',
				school: '',
			},
			{
				key: 'class:9',
				type: 'class',
				label: 'Class A1',
				school: 'Alpha School',
			},
		],
		groups_total: 2,
		learners: [ { id: 12, name: 'Ana Example' } ],
		limit: 20,
		min_search: 2,
	};
}

/** The map the overlays are laid over. */
export const map: SkillMap = {
	subject: 'Mathematics',
	domains: [
		{ id: 'number', label: 'Number' },
		{ id: 'algebra', label: 'Algebra' },
	],
	grades: [
		{ value: 1, label: 'Grade 1' },
		{ value: 2, label: 'Grade 2' },
	],
	skills: [
		{
			id: 1,
			name: 'Count to ten',
			code: '',
			domain: 'number',
			grade_from: 1,
			grade_to: 1,
			prerequisites: [],
		},
		{
			id: 2,
			name: 'Add within twenty',
			code: '',
			domain: 'number',
			grade_from: 2,
			grade_to: 2,
			prerequisites: [ 1 ],
		},
		{
			id: 3,
			name: 'Spot patterns',
			code: '',
			domain: 'algebra',
			grade_from: 1,
			grade_to: 1,
			prerequisites: [],
		},
		{
			id: 4,
			name: 'Simple equations',
			code: '',
			domain: 'algebra',
			grade_from: 2,
			grade_to: 2,
			prerequisites: [ 3 ],
		},
	],
	unplaced: [],
	issues: [],
	syllabus: null,
};
