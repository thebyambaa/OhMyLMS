import type {
	Assignment,
	AssignmentDetail,
	Submission,
	SubmissionDetail,
} from './types';

/** Test fixtures only; production components never import this module. */
export const summary: Assignment = {
	id: 31,
	title: 'Essay on rivers',
	slug: 'essay-on-rivers',
	status: 'publish',
	permalink: 'https://example.test/assignment/essay-on-rivers/',
	author_id: 3,
	author_name: 'Sam Teacher',
	course: { id: 12, title: 'Geography' },
	max_score: 50,
	pass_score: 25,
	time_limit_enabled: false,
	time_limit: 10,
	time_limit_unit: 'week',
	allow_file_uploads: true,
	max_files: 5,
	file_size_limit_enabled: true,
	max_file_size_mb: 5,
	submission_count: 4,
	pending_count: 1,
	created_at: '2026-10-30T09:15:00Z',
	updated_at: '2026-10-30T09:20:00Z',
	can_edit: true,
	can_delete: true,
};

export const assignment: AssignmentDetail = {
	...summary,
	instructions: '<p>Write 500 words.</p>',
	placements: [
		{
			course_id: 12,
			course_title: 'Geography',
			chapter_id: 40,
			chapter_title: 'Rivers',
			source: 'chapter',
		},
	],
};

export const pending: Submission = {
	id: 501,
	assignment_id: 31,
	student: { id: 9, display_name: 'Alex Learner' },
	course: { id: 12, title: 'Geography' },
	status: 'submitted',
	result: null,
	score: null,
	max_score: 50,
	pass_score: 25,
	submitted_at: '2026-10-30T10:00:00Z',
	graded_at: null,
	attachment_count: 1,
};

export const pendingDetail: SubmissionDetail = {
	...pending,
	content: 'My essay text.',
	feedback: '',
	attachments: [
		{
			name: 'essay.pdf',
			type: 'application/pdf',
			size: 20480,
			url: 'https://example.test/wp-json/ohmylms/v1/assignments/31/submissions/501/file?_wpnonce=abc',
		},
	],
};

export const graded: SubmissionDetail = {
	...pendingDetail,
	status: 'graded',
	result: 'passed',
	score: 40,
	feedback: '<p>Well done.</p>',
	graded_at: '2026-10-30T11:00:00Z',
};
