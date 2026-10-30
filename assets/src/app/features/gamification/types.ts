/** Sections of the gamification settings, in tab order. */
export const SECTION_IDS = [
	'points',
	'levels',
	'badges',
	'leaderboard',
	'rewards',
] as const;
export type SectionId = ( typeof SECTION_IDS )[ number ];

/** Tabs of the screen; rewards share the leaderboard tab. */
export const TAB_IDS = [ 'points', 'levels', 'badges', 'leaderboard' ] as const;
export type TabId = ( typeof TAB_IDS )[ number ];

export const LEADERBOARD_MODES = [
	'completion_rate',
	'highest_quiz',
	'fastest_time',
] as const;
export type LeaderboardMode = ( typeof LEADERBOARD_MODES )[ number ];

export const METRICS = [
	'points',
	'completed_lesson',
	'completed_courses',
] as const;
export type Metric = ( typeof METRICS )[ number ];

export const OPERATORS = [ '>=', '>', '<=', '<', '==', '!=' ] as const;
export type Operator = ( typeof OPERATORS )[ number ];

export interface PointEmail {
	enabled: boolean;
	subject: string;
	body: string;
}

/** A point rule as stored. `label`, `description` and `supports_threshold` are display-only. */
export interface PointRule {
	event: string;
	label: string;
	description: string;
	supports_threshold: boolean;
	enabled: boolean;
	points: number;
	threshold: number | null;
	email: PointEmail;
}

export interface PointsSettings {
	enabled: boolean;
	rules: PointRule[];
}

export interface ToggleSettings {
	enabled: boolean;
}

export interface LeaderboardSettings {
	enabled: boolean;
	mode: LeaderboardMode;
	threshold: number;
	limit: number;
}

export interface RewardsSettings {
	enabled: boolean;
	purchase_with_points: boolean;
}

export interface SectionMap {
	points: PointsSettings;
	levels: ToggleSettings;
	badges: ToggleSettings;
	leaderboard: LeaderboardSettings;
	rewards: RewardsSettings;
}

/** Editable copy of a point rule: numbers stay `null` while the field is empty. */
export interface PointRuleDraft {
	event: string;
	label: string;
	description: string;
	supports_threshold: boolean;
	enabled: boolean;
	points: number | null;
	threshold: number | null;
	email: PointEmail;
}

export interface PointsDraft {
	enabled: boolean;
	rules: PointRuleDraft[];
}

export interface LeaderboardDraft {
	enabled: boolean;
	mode: LeaderboardMode;
	threshold: number | null;
	limit: number | null;
}

export interface DraftMap {
	points: PointsDraft;
	levels: ToggleSettings;
	badges: ToggleSettings;
	leaderboard: LeaderboardDraft;
	rewards: RewardsSettings;
}

export interface Level {
	slug: string;
	name: string;
	description: string;
	color: string;
	text_color: string;
	points: number;
	earned_count: number;
}

export interface LevelDraft {
	name: string;
	description: string;
	color: string;
	text_color: string;
	points: number | null;
}

export interface Criterion {
	metric: Metric;
	operator: Operator;
	value: number;
}

export interface CriterionDraft {
	metric: Metric;
	operator: Operator;
	value: number | null;
}

export type AwardSource = 'criteria' | 'streak';

export interface Badge {
	slug: string;
	name: string;
	description: string;
	image_id: number;
	image_url: string | null;
	color: string;
	award_source: AwardSource;
	criteria: Criterion[];
	source: 'custom' | 'streak';
	earned_count: number;
}

export interface BadgeDraft {
	name: string;
	description: string;
	image_id: number;
	image_url: string | null;
	color: string;
	award_source: AwardSource;
	criteria: CriterionDraft[];
}

export interface Choice< T extends string > {
	value: T;
	label: string;
}

export interface Limits {
	max_points: number;
	max_criterion_value: number;
	max_levels: number;
	max_badges: number;
	max_criteria: number;
}

export interface GamificationOptions {
	integration_enabled: boolean;
	metrics: Choice< Metric >[];
	operators: Choice< Operator >[];
	leaderboard_modes: Choice< LeaderboardMode >[];
	limits: Limits;
}

export interface MediaImage {
	id: number;
	url: string;
	thumbnail_url: string;
	title: string;
}

/** Field name to message, from client validation or the server. */
export type FieldErrors = Record< string, string >;

/** A failed request, reduced to what the screen shows. */
export interface FailureView {
	message: string;
	/** True for 401/403: retrying cannot help. */
	permission: boolean;
	code: string;
	fieldErrors: FieldErrors;
}

export interface Page< T > {
	items: T[];
	total: number;
	totalPages: number;
}
