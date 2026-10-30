import type { QuizReportRow } from './types';

/** Test fixture only; production components never import this module. */
export const reportRow: QuizReportRow = {
	id: 11,
	title: 'Fractions check',
	status: 'publish',
	course: { id: 5, title: 'Maths 101' },
	attempts: 4,
	learners: 3,
	in_progress: 1,
	to_grade: 1,
	completed: 2,
	average_score: 5,
	passing_score: 5,
	passed: 1,
	pass_rate: 50,
	last_submitted_at: '2026-10-04T10:00:00Z',
	can_edit: true,
	can_grade: true,
};
