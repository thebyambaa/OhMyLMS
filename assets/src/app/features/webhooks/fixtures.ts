import type { Webhook, WebhookOptions } from './types';

/** Test fixtures only; production components never import this module. */
export const options: WebhookOptions = {
	triggers: [ { value: 'course_enrollment', label: 'Course Enrollment' } ],
	methods: [ { value: 'POST', label: 'POST' } ],
	data_types: [ { value: 'json', label: 'JSON' } ],
	statuses: [
		{ value: 'active', label: 'Active' },
		{ value: 'inactive', label: 'Inactive' },
	],
};
export const webhook: Webhook = {
	id: 7,
	name: 'Enrollment service',
	webhook_url: 'https://example.com/hooks',
	trigger_event: 'course_enrollment',
	http_method: 'POST',
	data_type: 'json',
	status: 'inactive',
	data_mapping: [],
	secret_hint: 'whsec_****1234',
	created_at: '2026-10-30T00:00:00',
	updated_at: '2026-10-30T00:00:00',
};
