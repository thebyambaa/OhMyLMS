/** Publication state of a quiz. `trash` quizzes can be restored or deleted for good. */
export type QuizStatus = 'draft' | 'publish' | 'trash';

/** How the quiz player lays out the questions. */
export type QuizLayout =
	| 'one_question_per_page'
	| 'all_questions_in_one_page'
	| 'number_of_questions_per_page';

/** When learners see marks and correctness. */
export type FeedbackMode = 'immediate' | 'after_close' | 'manual';

/** A course reference. */
export interface CourseRef {
	id: number;
	title: string;
}

/** A course choice for the list filter. */
export interface CourseChoice extends CourseRef {
	status: string;
}

/** A quiz as listed (`context=view`). Dates are UTC ISO 8601. */
export interface Quiz {
	id: number;
	title: string;
	slug: string;
	status: QuizStatus;
	permalink: string;
	author_id: number;
	author_name: string;
	course: CourseRef | null;
	question_count: number;
	attempt_count: number;
	pending_review_count: number;
	created_at: string;
	updated_at: string;
	can_edit: boolean;
	can_delete: boolean;
	can_grade: boolean;
	/** Block editor link added by the server when the user may edit the quiz. */
	native_editor_url: string | null;
}

/** The settings the server stores for a quiz. */
export interface QuizSettings {
	time_limit_minutes: number;
	passing_score: number;
	attempts_allowed: number;
	randomize_questions: boolean;
	show_correct_answers: boolean;
	layout: QuizLayout;
	questions_per_page: number;
	feedback_mode: FeedbackMode;
	available_from: string | null;
	available_until: string | null;
}

/** A quiz read with `context=edit`. */
export interface QuizDetail extends Quiz {
	description: string;
	settings: QuizSettings;
	total_marks: number;
}

/** Writable fields of a quiz, exactly as the REST API accepts them. */
export interface QuizInput {
	title: string;
	status: Exclude< QuizStatus, 'trash' >;
	description: string;
	settings: QuizSettings;
	/** The `updated_at` the editor loaded; a newer saved version answers 409. */
	base_modified: string;
}

/** Editor state. Numbers stay text until validated; dates are `datetime-local` values. */
export interface QuizForm {
	title: string;
	status: Exclude< QuizStatus, 'trash' >;
	description: string;
	time_limit_minutes: string;
	passing_score: string;
	attempts_allowed: string;
	randomize_questions: boolean;
	show_correct_answers: boolean;
	layout: QuizLayout;
	questions_per_page: string;
	feedback_mode: FeedbackMode;
	available_from: string;
	available_until: string;
}

/** Field errors by form field name. */
export type QuizFormErrors = Partial< Record< keyof QuizForm, string > >;

/** Attempt status. */
export type AttemptStatus = 'in-progress' | 'in-review' | 'completed';

/** The only student data an attempt carries. */
export interface Student {
	id: number;
	name: string;
	email: string;
}

/** An attempt as listed. */
export interface Attempt {
	id: number;
	quiz_id: number;
	course_id: number;
	student: Student;
	status: AttemptStatus;
	score: number;
	max_marks: number;
	percentage: number | null;
	started_at: string | null;
	submitted_at: string | null;
	needs_grading: boolean;
}

/** One part of a structured question. */
export interface AttemptPart {
	id: string;
	kind: string;
	max: number;
	awarded: number | null;
	grader: string | null;
}

/** One entry of the grade history of a question. */
export interface GradeEvent {
	awarded: number;
	grader: string;
	reviewer_id: number;
	reviewer_name: string;
	reason: string;
	created_at: string;
}

/** A question of an attempt with the learner's answer and marks. */
export interface AttemptQuestion {
	question_id: number;
	position: number;
	title: string;
	type: string;
	max_marks: number;
	awarded: number;
	status: 'graded' | 'in-review';
	correct: boolean | null;
	answer: string[];
	parts: AttemptPart[] | null;
	history: GradeEvent[];
}

/** An attempt with its questions. */
export interface AttemptDetail extends Attempt {
	passing_mark: number;
	passed: boolean | null;
	versioned: boolean;
	questions: AttemptQuestion[];
}

/** The result of grading: the refreshed attempt and how many grade events were written. */
export interface GradeResult extends AttemptDetail {
	changed: number;
}

/** Marks for one question: a number, or marks by part ID. */
export type Marks = number | Record< string, number >;

/** One entry of the grading request. */
export interface GradeInput {
	question_id: number;
	marks: Marks;
}

/** What the grader typed for a question. */
export interface QuestionDraft {
	total: string;
	parts: Record< string, string >;
}

/** Drafts by question id. */
export type Drafts = Record< number, QuestionDraft >;

/** Attempt list filters. */
export interface AttemptFilters {
	status: 'all' | AttemptStatus;
	search: string;
}

/** Lifecycle of a question in a quiz; `unavailable` means trashed or deleted. */
export type SlotStatus = 'draft' | 'approved' | 'archived' | 'unavailable';

/** Which version learners get: the pin, the current content, the approved version, or none. */
export type DeliverySource = 'pinned' | 'current' | 'approved' | 'unavailable';

/** One question placed in a quiz. Never carries settings, options or answer keys. */
export interface QuizQuestionSlot {
	question_id: number;
	position: number;
	/** Empty when the user may neither edit nor use the question. */
	name: string;
	type: string;
	type_label: string;
	/** Marks of the delivered version, or the section's marks. */
	marks: number;
	section: string;
	status: SlotStatus;
	/** Latest version number; 0 before the first version. */
	version: number;
	current_version_id: number;
	approved_version_id: number;
	/** 0 when not pinned. */
	pinned_version_id: number;
	pinned_version_no: number;
	delivery: {
		source: DeliverySource;
		version_id: number;
		version_no: number;
	};
	usage: { quizzes: number; responses: number };
	permissions: { can_edit: boolean; can_use: boolean };
	modified: string;
}

/** A random pool that draws approved questions of a skill at each attempt. */
export interface QuizPool {
	id: string;
	title: string;
	skill: { id: number; name: string };
	count: number;
	marks: number;
	difficulty: string;
	bank_id: number;
	include_secure: boolean;
}

/** `GET /quizzes/{id}/questions` and the answer to every write. */
export interface QuizQuestions {
	quiz_id: number;
	/** Token of the list as loaded; writes send it back and a changed list answers 409. */
	revision: string;
	questions: QuizQuestionSlot[];
	pools: QuizPool[];
	totals: {
		questions: number;
		fixed_marks: number;
		pool_questions: number;
		pool_marks: number;
		total_marks: number;
	};
}

/** The answer to creating a question in a quiz. */
export interface QuizQuestionCreated extends QuizQuestions {
	created_id: number;
}

/** A pin request: the version the quiz uses now, a version id, or `null` to unpin. */
export type PinChoice = 'current' | number | null;

/** A row of `GET /reports/quizzes`: aggregate attempt figures, never student data. */
export interface QuizReportRow {
	id: number;
	title: string;
	status: 'publish' | 'draft';
	course: CourseRef | null;
	attempts: number;
	learners: number;
	in_progress: number;
	to_grade: number;
	completed: number;
	/** Mean marks of completed attempts; null without completed attempts. */
	average_score: number | null;
	/** Pass mark in marks; 0 when the quiz has none. */
	passing_score: number;
	/** Completed attempts at or above the pass mark; null without a pass mark. */
	passed: number | null;
	/** Percentage of completed attempts that passed; null without a pass mark or completed attempts. */
	pass_rate: number | null;
	last_submitted_at: string | null;
	can_edit: boolean;
	can_grade: boolean;
}

/** `GET /reports/quizzes/summary`: totals over the filtered quizzes. */
export interface QuizReportSummary {
	quizzes: number;
	attempts: number;
	learners: number;
	to_grade: number;
	completed: number;
}

/** Which quizzes the report lists by attempt activity. */
export type ReportActivity = 'all' | 'attempted' | 'to_grade';

/** Sort fields of the report. */
export type ReportOrderBy =
	'last_submitted' | 'attempts' | 'to_grade' | 'title' | 'created_at';

/** Report filters, paging and sort, as kept in the address. */
export interface QuizReportQuery {
	page: number;
	search: string;
	course: number;
	status: 'all' | 'publish' | 'draft';
	activity: ReportActivity;
	orderby: ReportOrderBy;
	order: 'asc' | 'desc';
}
