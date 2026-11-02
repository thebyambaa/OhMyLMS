export const optionsBody = {
	events: [
		{
			id: 'course_enrolled',
			label: 'A learner is enrolled in a course',
			conditions: [ 'course_ids' ],
		},
		{
			id: 'course_progress',
			label: 'Course progress reaches a rate',
			conditions: [ 'course_ids', 'operator', 'rate' ],
		},
		{
			id: 'assignment_reviewed',
			label: 'An assignment is graded',
			conditions: [ 'course_ids', 'assignment_ids', 'result' ],
		},
	],
	actions: [
		{
			id: 'send_email',
			label: 'Send an email',
			params: [ 'to', 'subject', 'body' ],
		},
		{
			id: 'enrol_course',
			label: 'Enrol in a course',
			params: [ 'course_id' ],
		},
		{
			id: 'add_to_cohort',
			label: 'Add to a cohort',
			params: [ 'cohort_id' ],
		},
		{
			id: 'grant_membership',
			label: 'Give a membership plan',
			params: [ 'membership_id' ],
		},
		{
			id: 'send_coupon',
			label: 'Send a coupon code',
			params: [ 'coupon_id', 'subject', 'body' ],
		},
	],
	operators: [ 'gte', 'lte', 'eq' ],
	results: [ 'pass', 'fail' ],
	recipients: [ 'learner', 'admin' ],
	merge_tags: [ '{first_name}', '{course_title}', '{coupon_code}' ],
	max_actions: 5,
	max_ids: 50,
	max_subject: 200,
	max_body: 5000,
};

export const ruleRow = {
	id: 7,
	name: 'Welcome',
	status: 'active',
	event: 'course_enrolled',
	conditions: { course_ids: [ 40 ] },
	actions: [
		{ type: 'send_email', to: 'learner', subject: 'Hi', body: 'Hello' },
		{ type: 'enrol_course', course_id: 41 },
	],
	run_count: 2,
	last_run_at: '2026-10-31T09:00:00',
	created_at: '2026-10-30T09:00:00',
	updated_at: '2026-10-30T09:00:00',
};
