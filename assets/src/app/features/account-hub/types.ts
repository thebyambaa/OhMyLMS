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
