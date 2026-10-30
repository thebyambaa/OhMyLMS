/** Lifecycle state of a question: archived wins over approved, otherwise draft. */
export type QuestionStatus = 'draft' | 'approved' | 'archived';

/** Difficulty bands the server accepts on writes and filters. */
export type Difficulty = 'easy' | 'standard' | 'challenge';

/** Status filter of the list; `active` means not archived. */
export type StatusFilter = 'active' | 'draft' | 'approved' | 'archived';

/** A user reference (`id` 0 and an empty name for an unknown user). */
export interface UserRef {
	id: number;
	name: string;
}

/** How often a question is used. */
export interface QuestionUsage {
	quizzes: number;
	responses: number;
}

/** Lifecycle fields shared by list rows and the editor view. */
export interface QuestionLifecycle {
	status: QuestionStatus;
	post_status: string;
	/** Latest version number; 0 before the first version. */
	version: number;
	current_version_id: number;
	approved_version_id: number;
	approved_is_current: boolean;
}

/** A list row of `GET /questions`. Rows never carry settings, options or answer keys. */
export interface QuestionSummary extends QuestionLifecycle {
	id: number;
	uuid: string;
	name: string;
	type: string;
	type_label: string;
	marks: number;
	bank_id: number;
	/** Stored difficulty; normally one of {@link Difficulty}. */
	difficulty: string;
	source: string;
	family_id: string;
	secure: boolean;
	skills: number[];
	usage: QuestionUsage;
	author: UserRef;
	/** RFC 3339 UTC time; empty when unset. */
	modified: string;
	permissions: { can_edit: boolean; can_approve: boolean };
}

/** Matching data of an option (`matching` type). */
export interface MatchingData {
	label?: string;
	image_url?: string;
}

/** One answer option of the editor view. */
export interface QuestionOption {
	id: number;
	answer: string;
	is_correct: boolean;
	order_number: number;
	image_id: number;
	image_url: string;
	matching_data: MatchingData | null;
}

/** Bank attributes of a question. */
export interface QuestionBankFields {
	bank_id: number;
	difficulty: string;
	source: string;
	family_id: string;
	secure: boolean;
}

/** A quiz that uses the question. */
export interface QuizRef {
	id: number;
	name: string;
	can_edit: boolean;
}

/** Type settings: an open object whose `type` is the question type. */
export type QuestionSettings = Record< string, unknown >;

/** The editor view of `GET /questions/{id}` and of every write. */
export interface QuestionDetail extends QuestionLifecycle {
	id: number;
	uuid: string;
	name: string;
	description: string;
	type: string;
	settings: QuestionSettings;
	options: QuestionOption[];
	/** Skill map by part; kept as received because this editor does not change it. */
	skills: Record< string, unknown >;
	bank: QuestionBankFields;
	thumbnail_id: number;
	video_id: number;
	extension_settings: Record< string, unknown >;
	usage: QuestionUsage;
	quizzes: QuizRef[];
	permissions: {
		can_edit: boolean;
		can_approve: boolean;
		can_delete: boolean;
	};
	modified: string;
}

/** One saved version (`GET /questions/{id}/versions`). */
export interface QuestionVersion {
	id: number;
	version_no: number;
	created_at: string;
	created_by: UserRef;
	is_current: boolean;
	is_approved: boolean;
}

/** A registered question type (`GET /questions/types`). */
export interface QuestionType {
	id: string;
	label: string;
	/** The type's `editor.format`, for example `ohmylms-options`, or `null`. */
	format: string | null;
	answers: 'options' | 'text' | null;
	manual: boolean;
	snapshot: boolean;
	builtin: boolean;
}

/** Filters of the question list. Empty strings mean "any". */
export interface QuestionListQuery {
	page: number;
	search: string;
	type: string;
	difficulty: string;
	status: StatusFilter;
}

/** An option as the server accepts it. */
export interface OptionInput {
	id?: number;
	answer: string;
	is_correct: boolean;
	order_number: number;
	image_id?: number;
	matching_data?: MatchingData;
}

/** Body of `POST /questions`. */
export interface QuestionCreateInput {
	name: string;
	description?: string;
	settings: QuestionSettings;
	options?: OptionInput[];
	bank?: { difficulty: string };
}

/** Body of `PATCH /questions/{id}`: only changed fields, plus the `modified` of the last read. */
export interface QuestionUpdateInput {
	base_modified: string;
	name?: string;
	description?: string;
	settings?: QuestionSettings;
	options?: OptionInput[];
	bank?: { difficulty: string };
}

/** An option being edited. `key` is a stable React key; `id` is 0 for a new option. */
export interface FormOption {
	key: string;
	id: number;
	answer: string;
	is_correct: boolean;
	image_id: number;
	matching: MatchingData | null;
}

/** The editor state. Points stay a string until the form is validated. */
export interface FormState {
	name: string;
	description: string;
	points: string;
	difficulty: string;
	settings: QuestionSettings;
	options: FormOption[];
}

/** Field errors keyed by field name (`name`, `points`, `options`, `options.2.label`, …). */
export type FormErrors = Record< string, string >;
