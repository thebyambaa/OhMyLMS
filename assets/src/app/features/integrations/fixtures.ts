import type { Integration, IntegrationSettings } from './types';

/** Test fixtures only; production components never import this module. */
export const zoom: Integration = {
	id: 'zoom',
	label: 'Zoom',
	description: 'Host live classes inside your LMS.',
	icon: 'https://example.test/zoom.svg',
	categories: [ 'live-classes' ],
	dependency: 'Requires Cohorts',
	required_plan: null,
	enabled: false,
	available: true,
	unavailable_reason: null,
	configurable: true,
	requires_reload: false,
};
export const gamification: Integration = {
	...zoom,
	id: 'gamification',
	label: 'Gamification',
	description: 'Points and badges.',
	categories: [ 'course-engagement' ],
	dependency: null,
	enabled: true,
	configurable: false,
};
export const wpfusion: Integration = {
	...zoom,
	id: 'wpfusion',
	label: 'WP Fusion',
	description: 'Sync with your CRM.',
	categories: [ 'crm' ],
	dependency: null,
	available: false,
	unavailable_reason: 'Install and activate the WP Fusion Lite plugin.',
	configurable: false,
};
export const integrations: Integration[] = [ zoom, gamification, wpfusion ];

export const zoomSettings: IntegrationSettings = {
	integration: 'zoom',
	configured: true,
	fields: [
		{
			kind: 'text',
			name: 'account_id',
			label: 'Account ID',
			help: 'From your Zoom app.',
			required: true,
			max_length: 128,
			value: 'acct-1',
		},
		{
			kind: 'text',
			name: 'client_id',
			label: 'Client ID',
			help: '',
			required: true,
			max_length: 128,
			value: 'cid-1',
		},
		{
			kind: 'secret',
			name: 'client_secret',
			label: 'Client secret',
			help: '',
			required: true,
			max_length: 256,
			configured: true,
			hint: '****7890',
		},
		{
			kind: 'secret',
			name: 'webhook_secret_token',
			label: 'Webhook secret token',
			help: 'Optional.',
			required: false,
			max_length: 256,
			configured: true,
			hint: '****',
		},
	],
	readonly: [
		{
			name: 'webhook_url',
			label: 'Event notification endpoint URL',
			help: 'Paste into Zoom.',
			value: 'https://example.test/wp-json/ohmylms/v1/zoom/webhook',
		},
	],
};
