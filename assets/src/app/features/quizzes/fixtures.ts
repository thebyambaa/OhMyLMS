import type {
	Attempt,
	AttemptDetail,
	Quiz,
	QuizDetail,
	QuizQuestions,
	QuizQuestionSlot,
	QuizSettings,
} from './types';

/** Test fixtures only; production components never import this module. */
export const settings: QuizSettings = {
	time_limit_minutes: 30,
	passing_score: 60,
	attempts_allowed: 2,
	randomize_questions: false,
	show_correct_answers: true,
	layout: 'one_question_per_page',
	questions_per_page: 1,
	feedback_mode: 'immediate',
	available_from: null,
	available_until: null,
};

export const summary: Quiz = {
	id: 11,
	title: 'Fractions check',
	slug: 'fractions-check',
	status: 'publish',
	permalink: 'https://example.test/quiz/fractions-check/',
	author_id: 2,
	author_name: 'Ada Author',
	course: { id: 5, title: 'Maths 101' },
	question_count: 4,
	attempt_count: 3,
	pending_review_count: 1,
	created_at: '2026-10-30T09:00:00Z',
	updated_at: '2026-10-30T09:30:00Z',
	can_edit: true,
	can_delete: true,
	can_grade: true,
	native_editor_url:
		'https://example.test/wp-admin/post.php?post=11&action=edit',
};

export const quiz: QuizDetail = {
	...summary,
	description: '<p>Do your best.</p>',
	settings,
	total_marks: 10,
};

export const attempt: Attempt = {
	id: 21,
	quiz_id: 11,
	course_id: 5,
	student: { id: 9, name: 'Sam Student', email: 'sam@example.test' },
	status: 'in-review',
	score: 2,
	max_marks: 6,
	percentage: 33.3,
	started_at: '2026-10-30T10:00:00Z',
	submitted_at: '2026-10-30T10:20:00Z',
	needs_grading: true,
};

export const attemptDetail: AttemptDetail = {
	...attempt,
	passing_mark: 3,
	passed: null,
	versioned: true,
	questions: [
		{
			question_id: 101,
			position: 1,
			title: 'Pick the larger fraction',
			type: 'single-choice',
			max_marks: 2,
			awarded: 2,
			status: 'graded',
			correct: true,
			answer: [ '3/4' ],
			parts: null,
			history: [],
		},
		{
			question_id: 102,
			position: 2,
			title: 'Explain why 1/2 is 2/4',
			type: 'short-text',
			max_marks: 4,
			awarded: 0,
			status: 'in-review',
			correct: null,
			answer: [ 'Because they are equal.' ],
			parts: null,
			history: [],
		},
	],
};

export const slot: QuizQuestionSlot = {
	question_id: 101,
	position: 1,
	name: 'Pick the larger fraction',
	type: 'single-choice',
	type_label: 'Single Choice',
	marks: 2,
	section: '',
	status: 'draft',
	version: 3,
	current_version_id: 31,
	approved_version_id: 0,
	pinned_version_id: 0,
	pinned_version_no: 0,
	delivery: { source: 'current', version_id: 31, version_no: 3 },
	usage: { quizzes: 1, responses: 0 },
	permissions: { can_edit: true, can_use: true },
	modified: '2026-10-30T09:00:00Z',
};

export const sharedSlot: QuizQuestionSlot = {
	...slot,
	question_id: 102,
	position: 2,
	name: 'Explain why 1/2 is 2/4',
	marks: 3,
	status: 'approved',
	approved_version_id: 40,
	pinned_version_id: 40,
	pinned_version_no: 1,
	delivery: { source: 'pinned', version_id: 40, version_no: 1 },
	permissions: { can_edit: false, can_use: true },
};

export const questionList: QuizQuestions = {
	quiz_id: 11,
	revision: '0123456789abcdef',
	questions: [ slot, sharedSlot ],
	pools: [],
	totals: {
		questions: 2,
		fixed_marks: 5,
		pool_questions: 0,
		pool_marks: 0,
		total_marks: 5,
	},
};
