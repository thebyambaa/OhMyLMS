import type { CourseOption, Enrollment, Student } from './types';

/** Test fixtures only; production components never import this module. */
export const student: Student = {
	id: 12,
	name: 'Ada Lovelace',
	email: 'ada@example.com',
	avatar_url: 'https://example.com/ada.png',
	registered: '2026-01-05T10:00:00',
	last_login: '2026-10-29T08:30:00',
	blocked: false,
	enrolled_courses: 2,
	completed_courses: 1,
};
export const otherStudent: Student = {
	...student,
	id: 13,
	name: 'Grace Hopper',
	email: 'grace@example.com',
	last_login: null,
	blocked: true,
	enrolled_courses: 0,
	completed_courses: 0,
};
export const enrollment: Enrollment = {
	course_id: 40,
	title: 'Intro to Algebra',
	status: 'enrolled',
	progress: 'running',
	enrolled_at: '2026-02-01T09:00:00',
	completed_at: null,
	lessons: { completed: 2, total: 4 },
	quizzes: { completed: 0, total: 1 },
	assignments: { completed: 0, total: 0 },
	percent_complete: 40,
};
export const completedEnrollment: Enrollment = {
	...enrollment,
	course_id: 41,
	title: 'Geometry',
	progress: 'completed',
	completed_at: '2026-03-01T09:00:00',
	percent_complete: 100,
};
export const courses: CourseOption[] = [
	{ id: 40, title: 'Intro to Algebra', status: 'publish' },
	{ id: 41, title: 'Geometry', status: 'publish' },
	{ id: 42, title: 'Calculus', status: 'draft' },
];
