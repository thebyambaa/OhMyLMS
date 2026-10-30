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
