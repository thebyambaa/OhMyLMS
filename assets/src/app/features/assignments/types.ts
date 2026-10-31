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

/** A downloadable file (media library attachment) or a link. File URL and size come from the library. */
export interface Resource {
	type: 'file' | 'link';
	/** Attachment ID of a file; 0 for a link. */
	attachment_id: number;
	name: string;
	url: string;
	size: string;
}

/** Kind of content a learner completes before submitting. */
export type PrerequisiteType = 'lesson' | 'quiz' | 'assignment' | 'other';

/** Content required before submitting. `status` is the post status, or `missing` when it was removed. */
export interface PrerequisiteItem {
	id: number;
	title: string;
	type: PrerequisiteType;
	status: string;
}

/** Prerequisites as read. */
export interface Prerequisites {
	enable: boolean;
	items: PrerequisiteItem[];
}

/** When the assignment opens. */
export type DripType =
	| 'enrollment-from-x-days'
	| 'specific-date'
	| 'cohort-start'
	| 'cohort-from-x-days';

/** Release schedule. `date` is `YYYY-MM-DD` and `time` `HH:MM`, both site time; empty when unset. */
export interface Drip {
	enable: boolean;
	type: DripType;
	days: number;
	date: string;
	time: string;
}

/** Resources, prerequisites, release and comments of an assignment (edit context). */
export interface DeliverySettings {
	resources: Resource[];
	prerequisites: Prerequisites;
	drip: Drip;
	comments_enabled: boolean;
}

/** An assignment as edited (`context=edit`). */
export interface AssignmentDetail extends Assignment, DeliverySettings {
	instructions: string;
	placements: Placement[];
}

/** The writable fields, exactly as the REST API accepts them. */
export interface AssignmentInput extends AssignmentSettings {
	title: string;
	instructions: string;
	status: WritableStatus;
	resources: Resource[];
	prerequisites: { enable: boolean; ids: number[] };
	drip: Drip;
	comments_enabled: boolean;
	/** The `updated_at` the edit started from. */
	base_modified: string;
}

/** A choice offered by the prerequisite picker. */
export interface PrerequisiteOption {
	id: number;
	title: string;
	type: PrerequisiteType;
	status: string;
}

/** A page of prerequisite choices; `scope` says whether they come from the assignment's courses. */
export interface PrerequisiteOptions {
	items: PrerequisiteOption[];
	total: number;
	pages: number;
	scope: 'course' | 'library';
}

/** One comment of a submission thread. */
export interface SubmissionComment {
	id: number;
	submission_id: number;
	author: { id: number; display_name: string };
	role: 'instructor' | 'learner';
	body: string;
	created_at: string;
	can_delete: boolean;
}

/** A submission thread as the reader sees it. */
export interface CommentThread {
	items: SubmissionComment[];
	total: number;
	role: 'instructor' | 'learner';
	comments_enabled: boolean;
}

/** Editor state. Numbers stay strings until the form is validated. */
export interface FormState extends DeliverySettings {
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
