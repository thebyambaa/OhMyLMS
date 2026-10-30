/** Sections of the gamification settings, in tab order. */
export const SECTION_IDS = [
	'points',
	'levels',
	'badges',
	'leaderboard',
	'rewards',
] as const;
export type SectionId = ( typeof SECTION_IDS )[ number ];

/** Tabs of the screen; rewards share the leaderboard tab, score and mastery the XP tab. */
export const TAB_IDS = [
	'points',
	'levels',
	'badges',
	'streaks',
	'xp',
	'leaderboard',
] as const;
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

/** Settings groups under `engagement/settings/<group>`. */
export const ENGAGEMENT_GROUPS = [
	'streak',
	'xp',
	'score',
	'mastery',
] as const;
export type EngagementGroup = ( typeof ENGAGEMENT_GROUPS )[ number ];

export interface StreakMilestone {
	days: number;
	/** Badge slug, or empty for no badge. */
	badge: string;
	points: number;
}

export interface StreakSettings {
	enable: boolean;
	lesson: boolean;
	quiz: boolean;
	practice: boolean;
	practice_minimum: number;
	freezes: boolean;
	initial_freezes: number;
	maximum_freezes: number;
	refill_days: number;
	milestones: StreakMilestone[];
}

export interface XpSettings {
	enable: boolean;
	lesson: number;
	perfect: number;
	challenge: number;
	min_answered: number;
	goals: number[];
	default_goal: number;
	goal_bonus: number;
	daily_cap: number;
}

export interface ScoreBand {
	gain: number;
	loss: number;
}

export interface ScoreSettings {
	enable: boolean;
	bands: ScoreBand[];
	hint_gain: number;
	unlock: number;
	decay_grace_days: number;
	decay_per_week: number;
}

export interface MasteryPolicy {
	id: string;
	label: string;
	description: string;
}

export interface MasterySurface {
	id: string;
	label: string;
	/** Whether the activity counts when the site has not chosen. */
	counts: boolean;
}

/** The mastery group as GET returns it: the settings plus read-only choices. */
export interface MasterySettings {
	policy: string;
	surfaces: Record< string, boolean >;
	show_score: boolean;
	ai_insights: boolean;
	policies: MasteryPolicy[];
	surface_options: MasterySurface[];
	ai_ready: boolean;
}

export interface EngagementMap {
	streak: StreakSettings;
	xp: XpSettings;
	score: ScoreSettings;
	mastery: MasterySettings;
}

export interface StreakMilestoneDraft {
	days: number | null;
	badge: string;
	points: number | null;
}

/** Editable copies: numbers stay `null` while the field is empty. */
export interface StreakDraft {
	enable: boolean;
	lesson: boolean;
	quiz: boolean;
	practice: boolean;
	practice_minimum: number | null;
	freezes: boolean;
	initial_freezes: number | null;
	maximum_freezes: number | null;
	refill_days: number | null;
	milestones: StreakMilestoneDraft[];
}

export interface XpDraft {
	enable: boolean;
	lesson: number | null;
	perfect: number | null;
	challenge: number | null;
	min_answered: number | null;
	/** Goal choices as typed, for example `10, 20, 30`. */
	goals: string;
	default_goal: number | null;
	goal_bonus: number | null;
	daily_cap: number | null;
}

export interface ScoreBandDraft {
	gain: number | null;
	loss: number | null;
}

export interface ScoreDraft {
	enable: boolean;
	bands: ScoreBandDraft[];
	hint_gain: number | null;
	unlock: number | null;
	decay_grace_days: number | null;
	decay_per_week: number | null;
}

export interface MasteryDraft {
	policy: string;
	surfaces: Record< string, boolean >;
	show_score: boolean;
	ai_insights: boolean;
}

export interface EngagementDraftMap {
	streak: StreakDraft;
	xp: XpDraft;
	score: ScoreDraft;
	mastery: MasteryDraft;
}
