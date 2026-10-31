/** Tabs of the Account Hub landing page (`#/account-hub?tab=<id>`). */
export const TAB_IDS = [
	'schools',
	'classes',
	'teachers',
	'parents',
	'users',
] as const;

export type TabId = ( typeof TAB_IDS )[ number ];

/** A school as listed. `version` is sent back on save to detect concurrent edits. */
export interface School {
	id: number;
	name: string;
	slug: string;
	timezone: string;
	status: string;
	created_at: string | null;
	class_count: number;
	student_count: number;
	teacher_count: number;
	/** The removable sample school. */
	sample: boolean;
	version: string;
}

export interface AcademicYear {
	id: number;
	label: string;
	starts_at: string;
	ends_at: string;
	status: string;
}

/** One school with its academic years. */
export interface SchoolDetail extends School {
	years: AcademicYear[];
}

export type ClassStatus = 'active' | 'archived';

export type ClassRole = 'student' | 'teacher';

/** A class. Independent classes have `school_id` 0 and a null school name and year. */
export interface SchoolClass {
	id: number;
	name: string;
	subject: string;
	grade: string;
	status: ClassStatus;
	school_id: number;
	school_name: string | null;
	academic_year_id: number;
	academic_year_label: string | null;
	student_count: number;
	teacher_count: number;
	version: string;
}

/** An active class membership. No email or login is exposed. */
export interface ClassMember {
	id: number;
	user_id: number;
	display_name: string;
	role: ClassRole;
	joined_at: string | null;
}

/** A person picked from a school roster. */
export interface PersonRef {
	id: number;
	display_name: string;
}

export interface SchoolRef {
	id: number;
	name: string;
}

/** `email` is null unless the current user may list users. */
export interface Teacher {
	id: number;
	display_name: string;
	email: string | null;
	schools: SchoolRef[];
	class_count: number;
}

export interface Parent {
	id: number;
	display_name: string;
	email: string | null;
	child_count: number;
}

export interface ChildLink {
	id: number;
	school_id: number;
	school_name: string;
	student_id: number;
	student_name: string;
	linked_at: string | null;
}

export interface PendingLink {
	id: number;
	school_id: number;
	school_name: string;
	student_id: number;
	student_name: string;
	expires_at: string | null;
}

export interface ParentChildren {
	children: ChildLink[];
	pending: PendingLink[];
}

/** A single-use guardian invitation; `url` is shown once. */
export interface GuardianInvitation {
	id: number;
	school_id: number;
	student_id: number;
	url: string;
	expires_at: string | null;
	email_sent: boolean;
}

export interface SchoolInput {
	name: string;
	timezone: string;
}

export interface ClassInput {
	name: string;
	subject: string;
	grade: string;
}

export interface NewClassInput extends ClassInput {
	academic_year_id: number;
}

export interface YearInput {
	label: string;
	starts_at: string;
	ends_at: string;
}

/** Field errors keyed by field name. */
export type FormErrors< T > = Partial< Record< keyof T, string > >;

/** Account types the All accounts tab creates. */
export const ACCOUNT_TYPES = [ 'student', 'teacher', 'parent' ] as const;

export type AccountType = ( typeof ACCOUNT_TYPES )[ number ];

/** Registration period filters of the All accounts tab. */
export const REGISTERED_PERIODS = [
	'all',
	'last_30_days',
	'current_month',
	'previous_month',
	'current_year',
	'last_12_months',
] as const;

export type RegisteredPeriod = ( typeof REGISTERED_PERIODS )[ number ];

export type AccountStatusFilter = 'all' | 'active' | 'blocked';

export interface RoleRef {
	slug: string;
	label: string;
}

/**
 * One account in the All accounts list. Only the fields the directory shows; `can_edit` is false
 * for the caller's own account and for accounts with privileges the caller lacks.
 */
export interface Account {
	id: number;
	display_name: string;
	username: string;
	email: string;
	roles: RoleRef[];
	registered_at: string | null;
	last_login: string | null;
	blocked: boolean;
	can_edit: boolean;
}

/** An account opened for editing. `version` is sent back on save to detect concurrent edits. */
export interface EditableAccount {
	id: number;
	username: string;
	display_name: string;
	first_name: string;
	last_name: string;
	email: string;
	roles: RoleRef[];
	version: string;
}

/** Filter choices and the account types the current user may create. */
export interface AccountOptions {
	roles: RoleRef[];
	types: { value: AccountType; label: string }[];
	can_create: boolean;
}

export interface AccountFilters {
	role: string;
	registered: RegisteredPeriod;
	status: AccountStatusFilter;
}

export interface AccountInput {
	first_name: string;
	last_name: string;
	email: string;
}

export interface NewAccountInput extends AccountInput {
	type: AccountType;
	username: string;
	send_email: boolean;
}

/** Sub-screens of a school (`?panel=` on the school route). */
export const SCHOOL_PANELS = [
	'details',
	'guardians',
	'report',
	'rollover',
] as const;

export type SchoolPanel = ( typeof SCHOOL_PANELS )[ number ];

/** What deleting a school would remove, counted now. */
export interface RemovalImpact {
	classes: number;
	academic_years: number;
	students: number;
	teachers: number;
	school_admins: number;
	class_memberships: number;
	teacher_links: number;
	guardian_links: number;
	invitations: number;
	assignments: number;
	course_links: number;
	roster_enrolments: number;
	managed_accounts: number;
}

export interface SchoolRemoval {
	id: number;
	name: string;
	status: string;
	can_delete: boolean;
	/** The text to type to confirm the deletion (the school name). */
	confirm: string;
	impact: RemovalImpact;
}

export type GuardianStatusFilter = 'all' | 'active' | 'pending';

/** An active guardian link or an open guardian invitation. */
export interface GuardianRow {
	kind: 'link' | 'invitation';
	id: number;
	status: 'active' | 'pending';
	student: PersonRef;
	parent: PersonRef | null;
	linked_at: string | null;
	expires_at: string | null;
	email_hint: string | null;
}

/** Invite a guardian: an existing parent account or an email address. */
export type GuardianInviteInput =
	| { student_id: number; parent_id: number; send_email: boolean }
	| { student_id: number; email: string; send_email: boolean };

export interface ReportFigures {
	learners: number;
	active_learners: number;
	enrolled: number;
	completed: number;
	completion: number | null;
}

export interface ReportClass extends ReportFigures {
	id: number;
	name: string;
	grade: string;
	teachers: number;
	assigned: number;
	assigned_completed: number;
}

export interface ReportSkill {
	id: number;
	name: string;
	code: string;
	assessed: number;
	average: number;
	below: number;
}

export type ReportPeriod = 7 | 30 | 90;

export interface SchoolReport {
	school: { id: number; name: string; status: string };
	period: { days: ReportPeriod; start: string; end: string };
	summary: ReportFigures & {
		classes: number;
		archived_classes: number;
		assigned: number;
		assigned_completed: number;
	};
	classes: ReportClass[];
	more_classes: number;
	mastery: {
		skills: ReportSkill[];
		average: number | null;
		assessed: number;
	};
	generated_at: string;
}

export interface ReportExport {
	filename: string;
	content_type: string;
	csv: string;
}

/** One grade mapping row; an empty `to` means the class leaves (graduates). */
export interface GradeMapping {
	from: string;
	to: string;
}

export interface RolloverInput {
	from_year_id: number;
	to_year_id: number;
	grade_map: GradeMapping[];
	copy_teachers: boolean;
	/** Also create next-year classes for classes without students (skipped by default). */
	include_empty?: boolean;
}

export interface RolloverClass {
	class_id: number;
	name: string;
	grade: string;
	action: 'promote' | 'leave' | 'skip';
	students: number;
	teachers: number;
	enrolments: number;
	links: number;
	target: {
		id: number | null;
		name: string;
		grade: string;
		exists: boolean;
	} | null;
}

export interface RolloverTotals {
	classes: number;
	promoted_classes: number;
	leaving_classes: number;
	classes_skipped: number;
	classes_created: number;
	students_promoted: number;
	students_leaving: number;
	teachers_copied: number;
	enrolments_moved: number;
	enrolments_revoked: number;
	course_links: number;
}

export interface YearRef {
	id: number;
	label: string;
	status: string;
}

export interface RolloverPlan {
	from: YearRef;
	to: YearRef;
	classes: RolloverClass[];
	totals: RolloverTotals;
	review: string;
}

export interface RolloverResult {
	from: YearRef;
	to: YearRef;
	classes: {
		source_id: number;
		target_id: number | null;
		action: 'promote' | 'leave' | 'skip';
	}[];
	totals: RolloverTotals;
	already_applied: boolean;
}

export interface SampleSchool {
	exists: boolean;
	complete: boolean;
	school_id: number;
	classes: { key: string; id: number; name: string }[];
	people: { key: string; id: number; display_name: string; role: string }[];
	removal: {
		allowed: boolean;
		remove: string[];
		skip: string[];
		blockers: string[];
	};
}

/** A course or activity to assign. */
export interface TitledRef {
	id: number;
	title: string;
}

/** Work distributed to a class. */
export interface ClassWork {
	id: number;
	title: string;
	course_id: number;
	course_title: string;
	content_id: number;
	content_title: string | null;
	due_at: string | null;
	created_at: string | null;
	recipients: number;
	completed: number;
}

export interface ClassWorkInput {
	course_id: number;
	content_id: number;
	title: string;
	/** `YYYY-MM-DDTHH:MM` in the school's time zone, or empty. */
	due_at: string;
	prior_completion: boolean;
}

export interface Submission {
	id: number;
	assignment_id: number;
	title: string;
	student_name: string;
	content: string;
	score: number | null;
	status: string;
	note: string;
	total_points: number;
	file_url: string | null;
}
