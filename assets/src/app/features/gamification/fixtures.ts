import type {
	Badge,
	GamificationOptions,
	LeaderboardSettings,
	Level,
	MediaImage,
	PointRule,
	PointsSettings,
	RewardsSettings,
	ToggleSettings,
} from './types';

export const lessonRule: PointRule = {
	event: 'lesson_complete',
	label: 'Lesson completion',
	description: 'Award points when a learner finishes a lesson.',
	supports_threshold: false,
	enabled: true,
	points: 5,
	threshold: null,
	email: { enabled: false, subject: '', body: '' },
};

export const quizRule: PointRule = {
	event: 'quiz_passing_mark',
	label: 'Quiz achievement',
	description: 'Award points when a learner passes a quiz.',
	supports_threshold: true,
	enabled: false,
	points: 5,
	threshold: 30,
	email: { enabled: false, subject: '', body: '' },
};

export const points: PointsSettings = {
	enabled: true,
	rules: [ lessonRule, quizRule ],
};

export const toggleOn: ToggleSettings = { enabled: true };
export const toggleOff: ToggleSettings = { enabled: false };

export const leaderboard: LeaderboardSettings = {
	enabled: true,
	mode: 'completion_rate',
	threshold: 0,
	limit: 10,
};

export const rewards: RewardsSettings = {
	enabled: false,
	purchase_with_points: false,
};

export const bronze: Level = {
	slug: 'bronze',
	name: 'Bronze',
	description: '',
	color: '#aa6633',
	text_color: '#ffffff',
	points: 0,
	earned_count: 4,
};

export const silver: Level = {
	slug: 'silver',
	name: 'Silver',
	description: '',
	color: '#999999',
	text_color: '#000000',
	points: 100,
	earned_count: 1,
};

export const starter: Badge = {
	slug: 'starter',
	name: 'Starter',
	description: 'First steps',
	image_id: 41,
	image_url: 'https://example.test/starter.png',
	color: '#6e42d3',
	award_source: 'criteria',
	criteria: [ { metric: 'points', operator: '>=', value: 10 } ],
	source: 'custom',
	earned_count: 3,
};

export const options: GamificationOptions = {
	integration_enabled: true,
	metrics: [
		{ value: 'points', label: 'Bonus points' },
		{ value: 'completed_lesson', label: 'Completed lessons' },
		{ value: 'completed_courses', label: 'Completed courses' },
	],
	operators: [
		{ value: '>=', label: '>=' },
		{ value: '>', label: '>' },
		{ value: '<=', label: '<=' },
		{ value: '<', label: '<' },
		{ value: '==', label: '==' },
		{ value: '!=', label: '!=' },
	],
	leaderboard_modes: [
		{ value: 'completion_rate', label: 'Course completion rate' },
		{ value: 'highest_quiz', label: 'Highest quiz score' },
		{ value: 'fastest_time', label: 'Fastest completion time' },
	],
	limits: {
		max_points: 100000,
		max_criterion_value: 1000000,
		max_levels: 50,
		max_badges: 100,
		max_criteria: 10,
	},
};

export const image: MediaImage = {
	id: 77,
	url: 'https://example.test/full.png',
	thumbnail_url: 'https://example.test/thumb.png',
	title: 'Trophy',
};

export function page< T >( items: T[] ) {
	return { items, total: items.length, totalPages: 1 };
}

/**
 * Item `index` of a list, failing the test when it is missing (the app compiles with
 * `noUncheckedIndexedAccess`).
 *
 * @param list  Items.
 * @param index Position.
 */
export function at< T >( list: readonly T[], index = 0 ): T {
	const item = list[ index ];
	if ( undefined === item ) {
		throw new Error( `Missing item ${ index }` );
	}
	return item;
}
