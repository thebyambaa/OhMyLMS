/** Publication state of an assignment. `trash` is read only: saving a status restores it. */
export type AssignmentStatus = 'draft' | 'publish' | 'trash';

/** Writable publication states. */
export type WritableStatus = 'draft' | 'publish';

/** Unit of the learner deadline. */
export type TimeUnit = 'day' | 'week' | 'month';

/** List filter. `all` excludes the trash. */
export type StatusFilter = 'all' | 'publish' | 'draft' | 'trash';

/** A course reference. */
export interface CourseRef {
	id: number;
	title: string;
}

/** A course offered by the list filter. */
export interface CourseChoice extends CourseRef {
	status: string;
}

/** Where an assignment is placed (read only). */
export interface Placement {
	course_id: number;
	course_title: string;
	chapter_id: number | null;
	chapter_title: string | null;
	source: 'chapter' | 'program';
}

/** The settings learners see, stored exactly as the legacy editor stores them. */
export interface AssignmentSettings {
	max_score: number;
	pass_score: number;
	time_limit_enabled: boolean;
	time_limit: number;
	time_limit_unit: TimeUnit;
	allow_file_uploads: boolean;
	max_files: number;
	file_size_limit_enabled: boolean;
	max_file_size_mb: number;
}

/** An assignment as listed (`context=view`). */
export interface Assignment extends AssignmentSettings {
	id: number;
	title: string;
	slug: string;
	status: AssignmentStatus;
	permalink: string;
	author_id: number;
	author_name: string;
	course: CourseRef | null;
	submission_count: number;
	pending_count: number;
	created_at: string;
	updated_at: string;
	can_edit: boolean;
	can_delete: boolean;
}

/** An assignment as edited (`context=edit`). */
export interface AssignmentDetail extends Assignment {
	instructions: string;
	placements: Placement[];
}

/** The writable fields, exactly as the REST API accepts them. */
export interface AssignmentInput extends AssignmentSettings {
	title: string;
	instructions: string;
	status: WritableStatus;
	/** The `updated_at` the edit started from. */
	base_modified: string;
}

/** Editor state. Numbers stay strings until the form is validated. */
export interface FormState {
	title: string;
	instructions: string;
	status: WritableStatus;
	max_score: string;
	pass_score: string;
	time_limit_enabled: boolean;
	time_limit: string;
	time_limit_unit: TimeUnit;
	allow_file_uploads: boolean;
	max_files: string;
	file_size_limit_enabled: boolean;
	max_file_size_mb: string;
}

/** Field errors keyed by field name. */
export type FormErrors = Partial< Record< keyof FormState, string > >;

/** Whether a submission waits for a grade. */
export type SubmissionStatus = 'submitted' | 'graded';

/** Outcome of a graded submission. */
export type SubmissionResult = 'passed' | 'failed';

/** Submission list filter. */
export type SubmissionFilter = 'all' | SubmissionStatus;

/** A submission as listed: the student is only an ID and a display name. */
export interface Submission {
	id: number;
	assignment_id: number;
	student: { id: number; display_name: string };
	course: CourseRef | null;
	status: SubmissionStatus;
	result: SubmissionResult | null;
	score: number | null;
	max_score: number;
	pass_score: number;
	submitted_at: string;
	graded_at: string | null;
	attachment_count: number;
}

/** A downloadable upload. `url` is the authorised download route. */
export interface Attachment {
	name: string;
	type: string;
	size: number;
	url: string;
}

/** A submission with its text, feedback and attachments. */
export interface SubmissionDetail extends Submission {
	content: string;
	feedback: string;
	attachments: Attachment[];
}

/** A grade, exactly as the REST API accepts it. */
export interface GradeInput {
	score: number;
	feedback: string;
}
