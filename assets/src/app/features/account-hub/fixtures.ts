import type {
	Account,
	AccountOptions,
	ClassMember,
	ClassWork,
	EditableAccount,
	GuardianInvitation,
	GuardianRow,
	Parent,
	ParentChildren,
	PersonRef,
	RolloverPlan,
	RolloverResult,
	SampleSchool,
	SchoolClass,
	SchoolDetail,
	SchoolRemoval,
	SchoolReport,
	Submission,
	Teacher,
} from './types';

export const school: SchoolDetail = {
	id: 3,
	name: 'North School',
	slug: 'north-school',
	timezone: 'Asia/Ulaanbaatar',
	status: 'active',
	created_at: '2026-10-30T09:15:00Z',
	class_count: 1,
	student_count: 2,
	teacher_count: 1,
	sample: false,
	version: '0123456789abcdef',
	years: [
		{
			id: 5,
			label: '2026-2027',
			starts_at: '2026-09-01',
			ends_at: '2027-06-30',
			status: 'active',
		},
	],
};

export const schoolClass: SchoolClass = {
	id: 9,
	name: 'Algebra',
	subject: 'Math',
	grade: '5',
	status: 'active',
	school_id: 3,
	school_name: 'North School',
	academic_year_id: 5,
	academic_year_label: '2026-2027',
	student_count: 1,
	teacher_count: 1,
	version: 'fedcba9876543210',
};

export const member: ClassMember = {
	id: 40,
	user_id: 21,
	display_name: 'Ana Student',
	role: 'student',
	joined_at: '2026-10-30T09:20:00Z',
};

export const person: PersonRef = { id: 22, display_name: 'Bo Student' };

export const teacher: Teacher = {
	id: 12,
	display_name: 'Tess Teacher',
	email: 'tess@example.org',
	schools: [ { id: 3, name: 'North School' } ],
	class_count: 2,
};

export const parentAccount: Parent = {
	id: 30,
	display_name: 'Pat Parent',
	email: 'pat@example.org',
	child_count: 1,
};

export const children: ParentChildren = {
	children: [
		{
			id: 70,
			school_id: 3,
			school_name: 'North School',
			student_id: 21,
			student_name: 'Ana Student',
			linked_at: '2026-10-30T09:30:00Z',
		},
	],
	pending: [
		{
			id: 80,
			school_id: 3,
			school_name: 'North School',
			student_id: 22,
			student_name: 'Bo Student',
			expires_at: '2026-11-01T09:30:00Z',
		},
	],
};

export const invitation: GuardianInvitation = {
	id: 81,
	school_id: 3,
	student_id: 22,
	url: 'https://example.test/profile/?ohmylms_invite=abc',
	expires_at: '2026-11-01T09:30:00Z',
	email_sent: false,
};

export const account: Account = {
	id: 41,
	display_name: 'Tess Teacher',
	username: 'tess',
	email: 'tess@example.org',
	roles: [ { slug: 'ohmylms_teacher', label: 'Teacher' } ],
	registered_at: '2026-10-01T08:00:00Z',
	last_login: null,
	blocked: false,
	can_edit: true,
};

export const editableAccount: EditableAccount = {
	id: 41,
	username: 'tess',
	display_name: 'Tess Teacher',
	first_name: 'Tess',
	last_name: 'Teacher',
	email: 'tess@example.org',
	roles: [ { slug: 'ohmylms_teacher', label: 'Teacher' } ],
	version: '0123456789abcdef',
};

export const accountOptions: AccountOptions = {
	roles: [
		{ slug: 'administrator', label: 'Administrator' },
		{ slug: 'ohmylms_teacher', label: 'Teacher' },
	],
	types: [
		{ value: 'student', label: 'Student' },
		{ value: 'teacher', label: 'Teacher' },
		{ value: 'parent', label: 'Parent' },
	],
	can_create: true,
};

export const noSample: SampleSchool = {
	exists: false,
	complete: false,
	school_id: 0,
	classes: [],
	people: [],
	removal: { allowed: false, remove: [], skip: [], blockers: [] },
};

export const sampleSchool: SampleSchool = {
	exists: true,
	complete: true,
	school_id: 50,
	classes: [
		{ key: 'a', id: 51, name: 'Sample Grade 7A' },
		{ key: 'b', id: 52, name: 'Sample Grade 8B' },
	],
	people: [
		{
			key: 'teacher',
			id: 60,
			display_name: 'Sample Teacher',
			role: 'teacher',
		},
		{
			key: 'student',
			id: 61,
			display_name: 'Sample Student 1',
			role: 'student',
		},
	],
	removal: {
		allowed: true,
		remove: [
			'School "Sample Preview School" (#50)',
			'Account "Sample Teacher" (#60)',
		],
		skip: [],
		blockers: [],
	},
};

export const removal: SchoolRemoval = {
	id: 3,
	name: 'North School',
	status: 'archived',
	can_delete: true,
	confirm: 'North School',
	impact: {
		classes: 2,
		academic_years: 1,
		students: 5,
		teachers: 1,
		school_admins: 1,
		class_memberships: 7,
		teacher_links: 1,
		guardian_links: 2,
		invitations: 0,
		assignments: 1,
		course_links: 1,
		roster_enrolments: 3,
		managed_accounts: 4,
	},
};

export const guardianLink: GuardianRow = {
	kind: 'link',
	id: 70,
	status: 'active',
	student: { id: 21, display_name: 'Ana Student' },
	parent: { id: 30, display_name: 'Pat Parent' },
	linked_at: '2026-10-30T09:30:00Z',
	expires_at: null,
	email_hint: null,
};

export const guardianInvite: GuardianRow = {
	kind: 'invitation',
	id: 80,
	status: 'pending',
	student: { id: 22, display_name: 'Bo Student' },
	parent: null,
	linked_at: null,
	expires_at: '2026-11-01T09:30:00Z',
	email_hint: 'g***@example.org',
};

const figures = {
	learners: 5,
	active_learners: 3,
	enrolled: 4,
	completed: 1,
	completion: 25,
};

export const report: SchoolReport = {
	school: { id: 3, name: 'North School', status: 'active' },
	period: { days: 30, start: '2026-10-02', end: '2026-10-31' },
	summary: {
		...figures,
		classes: 1,
		archived_classes: 0,
		assigned: 2,
		assigned_completed: 1,
	},
	classes: [
		{
			...figures,
			id: 9,
			name: 'Algebra',
			grade: '5',
			teachers: 1,
			assigned: 2,
			assigned_completed: 1,
		},
	],
	more_classes: 0,
	mastery: {
		skills: [
			{
				id: 4,
				name: 'Fractions',
				code: 'F1',
				assessed: 3,
				average: 62,
				below: 1,
			},
		],
		average: 62,
		assessed: 3,
	},
	generated_at: '2026-10-31T02:00:00Z',
};

const totals = {
	classes: 1,
	promoted_classes: 1,
	leaving_classes: 0,
	classes_created: 1,
	students_promoted: 1,
	students_leaving: 0,
	teachers_copied: 0,
	enrolments_moved: 1,
	enrolments_revoked: 0,
	course_links: 1,
};

export const plan: RolloverPlan = {
	from: { id: 5, label: '2026-2027', status: 'active' },
	to: { id: 6, label: '2027-2028', status: 'active' },
	classes: [
		{
			class_id: 9,
			name: '5A',
			grade: '5',
			action: 'promote',
			students: 1,
			teachers: 0,
			enrolments: 1,
			links: 1,
			target: { id: null, name: '6A', grade: '6', exists: false },
		},
	],
	totals,
	review: 'a'.repeat( 64 ),
};

export const rolloverResult: RolloverResult = {
	from: { id: 5, label: '2026-2027', status: 'archived' },
	to: { id: 6, label: '2027-2028', status: 'active' },
	classes: [ { source_id: 9, target_id: 12, action: 'promote' } ],
	totals,
	already_applied: false,
};

export const classWork: ClassWork = {
	id: 90,
	title: 'Fractions homework',
	course_id: 15,
	course_title: 'Maths 5',
	content_id: 0,
	content_title: null,
	due_at: '2026-11-05T09:00:00Z',
	created_at: '2026-10-30T09:00:00Z',
	recipients: 2,
	completed: 1,
};

export const submission: Submission = {
	id: 95,
	assignment_id: 90,
	title: 'Essay',
	student_name: 'Ana Student',
	content: 'My answer',
	score: null,
	status: 'submitted',
	note: '',
	total_points: 10,
	file_url: null,
};

/**
 * One page of rows.
 *
 * @param items Rows.
 * @return A collection with matching totals.
 */
export function page< T >( items: T[] ) {
	return { items, total: items.length, totalPages: items.length ? 1 : 0 };
}
