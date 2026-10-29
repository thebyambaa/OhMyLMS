import type { EmailPreview, EmailSettings, EmailTemplate } from './types';

/** Test fixtures only; production components never import this module. */
export const quizGraded: EmailTemplate = {
	id: 'student_quiz_graded',
	title: 'Quiz graded',
	description: 'Sent to the student when a quiz is graded.',
	recipient_type: 'student',
	enabled: true,
	subject: 'Your quiz {quiz_name} was graded',
	heading: 'Your quiz was graded',
	additional_content: '<p>Dear {student_name},</p>',
	button_text: 'View result',
	button_link: '',
	footer_text: '<p>Thanks, {site_name}</p>',
	course_suggestion_text: null,
	recipient_email: [],
	tags: [
		{
			tag: 'student_name',
			label: 'Student name',
			description: 'Display name of the student.',
		},
		{
			tag: 'course_name',
			label: 'Course name',
			description: 'Title of the course.',
		},
		{
			tag: 'quiz_name',
			label: 'Quiz name',
			description: 'Title of the quiz.',
		},
		{
			tag: 'site_name',
			label: 'Site name',
			description: 'Name of this website.',
		},
	],
};

export const newOrder: EmailTemplate = {
	...quizGraded,
	id: 'creator_new_order',
	title: 'New order',
	description: 'Sent to the creator when an order is placed.',
	recipient_type: 'creator',
	enabled: false,
	subject: '{student_name} placed an order',
	recipient_email: [ 'owner@example.com' ],
};

export const settings: EmailSettings = {
	branding_image: '',
	base_color: '#6E42D3',
	background_color: '#F4F5F7',
	body_background_color: '#FFFFFF',
	body_text_color: '#1F2328',
	button_position: 'left',
	sender_name: 'Example Academy',
	sender_email: 'academy@example.com',
	footer_text: '',
};

export const preview: EmailPreview = {
	subject: 'Your quiz Chapter 1 Quiz was graded',
	html: '<table><tr><td>Dear Alex Learner</td></tr></table>',
	unknown_tags: [],
};
