/** Step ids in wizard order. Mirrors `SetupSteps::ids()` (see contract.json). */
export const STEP_IDS = [
	'welcome',
	'experience',
	'preferences',
	'niche',
	'pages',
	'import',
] as const;
export type StepId = ( typeof STEP_IDS )[ number ];

export const EXPERIENCES = [
	'beginner',
	'intermediate',
	'experienced',
] as const;
export type Experience = ( typeof EXPERIENCES )[ number ];

export const NICHES = [
	'digital-marketing-growth',
	'tech-skills',
	'creator-economy',
	'academic-test-prep',
	'other',
] as const;
export type Niche = ( typeof NICHES )[ number ];

export const CURRENCY_POSITIONS = [
	'left',
	'right',
	'left_space',
	'right_space',
] as const;
export type CurrencyPosition = ( typeof CURRENCY_POSITIONS )[ number ];

export const STATUSES = [
	'not_started',
	'in_progress',
	'completed',
	'dismissed',
] as const;
export type WizardStatus = ( typeof STATUSES )[ number ];

export type StepStatus = 'pending' | 'done' | 'skipped';

export interface StepInfo {
	id: StepId;
	status: StepStatus;
	skippable: boolean;
	available: boolean;
}

export type PageStatus = 'ready' | 'adoptable' | 'missing';

export interface StarterPage {
	key: string;
	title: string;
	slug: string;
	page_id: number;
	status: PageStatus;
}

export interface Preferences {
	currency: string;
	currency_pos: string;
	price_num_decimals: number;
}

export interface SetupState {
	version: number;
	status: WizardStatus;
	currentStep: StepId | null;
	steps: StepInfo[];
	answers: {
		experience: Experience | null;
		niche: Niche | null;
		topics: string[];
	};
	preferences: Preferences;
	starterPages: StarterPage[];
	startedAt: string | null;
	completedAt: string | null;
}

export interface PageAction {
	key: string;
	page_id: number;
	action: 'kept' | 'adopted' | 'created';
}

export interface StepResult {
	changed: string[];
	pages: PageAction[];
}

export interface StepResponse {
	state: SetupState;
	result: StepResult;
}

/** Body of a step request. */
export interface ExperienceInput {
	experience: Experience;
}
export interface PreferencesInput {
	currency: string;
	currency_pos: CurrencyPosition;
	price_num_decimals: number;
}
export interface NicheInput {
	niche: Niche;
	topics: string[];
}
export type StepInput =
	ExperienceInput | PreferencesInput | NicheInput | Record< string, never >;

export interface CurrencyOption {
	code: string;
	name: string;
	symbol: string;
}

export type FieldErrors = Record< string, string >;

/** A failed request, reduced to what the screen shows. */
export interface FailureView {
	message: string;
	/** 401/403: retrying cannot help. */
	permission: boolean;
	fieldErrors: FieldErrors;
}
