import type {
	ClassMember,
	GuardianInvitation,
	Parent,
	ParentChildren,
	PersonRef,
	SchoolClass,
	SchoolDetail,
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

/**
 * One page of rows.
 *
 * @param items Rows.
 * @return A collection with matching totals.
 */
export function page< T >( items: T[] ) {
	return { items, total: items.length, totalPages: items.length ? 1 : 0 };
}
