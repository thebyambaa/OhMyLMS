/** Contract of `ohmylms/v1/exams/library` and `ohmylms/v1/exam-imports/jobs` (docs/api/exams.md). */

export type ExamStatus = 'draft' | 'publish';
export type ExamOrigin = 'composed' | 'imported';
export type StatusFilter = 'all' | ExamStatus;
export type FeedbackRelease = 'immediate' | 'after_close' | 'manual';

/** List row. */
export interface ExamSummary {
	id: number;
	title: string;
	status: ExamStatus;
	origin: ExamOrigin;
	job_id: number;
	quiz_id: number;
	duration_minutes: number;
	total_marks: number;
	question_count: number;
	released: boolean;
	updated_at: string;
	revision: string;
}

export const HEADER_FIELDS = [
	'board',
	'qualification',
	'subject_code',
	'paper',
	'variant',
	'session',
	'calculator',
] as const;
export type HeaderField = ( typeof HEADER_FIELDS )[ number ];
export type ExamHeader = Record< HeaderField, string >;

export interface ExamTiming {
	duration_minutes: number;
	available_from: string;
	available_until: string;
	grace_seconds: number;
	feedback_release: FeedbackRelease;
}

export interface PaperSettings {
	size: 'a4' | 'letter';
	margin: number;
	font_size: number;
	font: 'sans' | 'serif';
	numbering: 'number' | 'q';
	show_marks: boolean;
	lines_per_mark: number;
	page_numbers: boolean;
	default_view: boolean;
	versions: number;
}

export interface SectionItem {
	kind: 'section';
	title: string;
	new_page: boolean;
}

export interface QuestionItem {
	kind: 'question';
	question_id: number;
	name: string;
	type: string;
	marks: number;
	approved: boolean;
	can_edit: boolean;
}

export type CompositionItem = SectionItem | QuestionItem;

/** Editor view of one exam. */
export interface ExamDetail extends ExamSummary {
	header: ExamHeader;
	instructions: string;
	timing: ExamTiming;
	paper: PaperSettings;
	composition: CompositionItem[];
	problems: string[];
	warnings: string[];
	links: {
		player: string;
		preview: string;
		editor: string;
		paper: string;
		key: string;
	};
	permissions: {
		can_edit: boolean;
		can_compose: boolean;
		can_publish: boolean;
	};
}

/** A question the author may add from the bank. */
export interface BankQuestion {
	id: number;
	name: string;
	type: string;
	marks: number;
	approved: boolean;
	available: boolean;
}

/** Settings form; numbers stay text until validated. */
export interface SettingsForm {
	title: string;
	duration_minutes: string;
	instructions: string;
	header: ExamHeader;
	available_from: string;
	available_until: string;
	grace_seconds: string;
	feedback_release: FeedbackRelease;
	paper: PaperSettings;
}

export type SettingsErrors = Partial<
	Record< 'title' | 'duration_minutes' | 'grace_seconds' | 'timing', string >
>;

export interface SettingsInput {
	revision: string;
	title: string;
	duration_minutes: number;
	instructions: string;
	header: ExamHeader;
	timing: Omit< ExamTiming, 'duration_minutes' >;
	paper: PaperSettings;
}

/** Composition item as sent to the server. */
export type CompositionInput =
	| { kind: 'section'; title: string; new_page: boolean }
	| { kind: 'question'; question_id: number };

// Imports.

export type JobState =
	| 'queued'
	| 'failed'
	| 'ready_for_extraction'
	| 'extracting'
	| 'extraction_failed'
	| 'ready_for_review'
	| 'committed';
export type ImportTarget = 'exam' | 'quiz' | 'questions';
export type RowStatus = 'pending' | 'accepted' | 'rejected';
export type PartKind = 'numerical' | 'text' | 'written';

export interface ImportJob {
	id: number;
	title: string;
	kind: 'pdf' | 'rows';
	state: JobState;
	target: ImportTarget;
	attempts: number;
	error: string;
	validation_error: string;
	source: { name: string; bytes: number };
	has_mark_scheme: boolean;
	revision: number;
	row_count: number;
	accepted_count: number;
	rejected_count: number;
	question_ids: number[];
	exam_id: number;
	quiz_id: number;
	updated_at: string;
	can_extract: boolean;
	can_apply: boolean;
}

export interface ImportPart {
	id: string;
	label?: string;
	prompt?: string;
	kind?: PartKind;
	marks: number;
	answer?: number;
	tolerance?: number;
	unit?: string;
	accepted?: string[];
	rubric?: string;
	skills?: string;
}

export interface ImportFigure {
	id: string;
	page: number;
	bounds: number[];
	part_ids: string[];
	asset_id: string;
	alt: string;
	not_to_scale: boolean;
	selected: 'original' | 'jsxgraph';
	approved: boolean;
	spec?: unknown;
}

export interface ImportRow {
	index: number;
	number: string;
	status: RowStatus;
	question: string;
	page: number;
	requires_source: boolean;
	notes: string;
	skills: string;
	parts: ImportPart[];
	figures: ImportFigure[];
	figures_complete: boolean;
	marks: number;
}

export interface ImportJobDetail extends ImportJob {
	inventory: {
		title: string;
		duration_minutes: number;
		total_marks: number;
		page_count: number;
	};
	usage: { requests: number };
	rows: ImportRow[];
}

export interface FigureAsset {
	id: string;
	state: 'queued' | 'rendering' | 'ready' | 'failed';
	attempts: number;
	error: string;
	page: number;
	bounds: number[];
	width: number;
	height: number;
	url: string;
}

export interface SkillMatch {
	id: number;
	name: string;
	code: string;
	path: string;
}

export interface DryRunRow {
	row: number;
	status: 'create' | 'update' | 'unchanged' | 'error';
	errors: string[];
	warnings: string[];
}

export interface DryRunReport {
	dry_run: true;
	counts: Record< string, number >;
	rows: DryRunRow[];
}

/** Row edit body (fields omitted stay unchanged). */
export interface RowChanges {
	question?: string;
	page?: number;
	requires_source?: boolean;
	notes?: string;
	skills?: string;
	parts?: ImportPart[];
	figures?: ImportFigure[];
}

export interface ApplyInput {
	title?: string;
	duration_minutes?: number;
}

/** Import start: a file (PDF, CSV or JSON) or pasted rows. */
export interface ImportStart {
	file?: File | null;
	markScheme?: File | null;
	text?: string;
	format?: 'auto' | 'csv' | 'json';
	target: ImportTarget;
	name?: string;
	skipInvalid?: boolean;
}
