import type {
	Badge,
	MasterySettings,
	ScoreSettings,
	StreakSettings,
	XpSettings,
} from './types';

/** Test fixtures only; production components never import this module. */
export const streakSettings: StreakSettings = {
	enable: false,
	lesson: true,
	quiz: true,
	practice: true,
	practice_minimum: 3,
	freezes: true,
	initial_freezes: 2,
	maximum_freezes: 2,
	refill_days: 7,
	milestones: [
		{ days: 7, badge: 'streak-7', points: 0 },
		{ days: 30, badge: 'streak-30', points: 10 },
	],
};

export const xpSettings: XpSettings = {
	enable: true,
	lesson: 10,
	perfect: 5,
	challenge: 2,
	min_answered: 3,
	goals: [ 10, 20, 30 ],
	default_goal: 20,
	goal_bonus: 0,
	daily_cap: 300,
};

export const scoreSettings: ScoreSettings = {
	enable: false,
	bands: [
		{ gain: 9, loss: 1.5 },
		{ gain: 5, loss: 3.5 },
		{ gain: 2.5, loss: 5 },
		{ gain: 1.5, loss: 6.5 },
	],
	hint_gain: 0.5,
	unlock: 80,
	decay_grace_days: 21,
	decay_per_week: 1,
};

export const masterySettings: MasterySettings = {
	policy: 'evidence',
	surfaces: { practice: true },
	show_score: true,
	ai_insights: false,
	policies: [
		{
			id: 'evidence',
			label: 'Evidence rules',
			description: 'Levels come from the evidence rules.',
		},
		{
			id: 'steps',
			label: 'Khan Academy levels',
			description: 'An exercise sets a level from its percentage.',
		},
	],
	surface_options: [
		{ id: 'quiz', label: 'Quiz', counts: true },
		{ id: 'practice', label: 'Practice', counts: true },
	],
	ai_ready: false,
};

export const streakBadge: Badge = {
	slug: 'streak-7',
	name: '7-day learning streak',
	description: '',
	image_id: 0,
	image_url: null,
	color: '#365dbe',
	award_source: 'streak',
	criteria: [],
	source: 'streak',
	earned_count: 0,
};

export const customStreakBadge: Badge = {
	...streakBadge,
	slug: 'fortnight',
	name: 'Fortnight',
	source: 'custom',
};
