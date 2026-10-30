/** Raw server responses for the tabs with their own routes. Test fixtures only. */

export const stripeRaw = {
	id: 'stripe',
	title: 'Stripe',
	description: 'Cards and wallets',
	subscription_support: true,
	enabled: false,
	mode: 'test',
	fields: [
		{
			name: 'title',
			label: 'Title',
			help: 'Shown at checkout.',
			type: 'text',
			mode: null,
			max_length: 200,
			required: true,
			default: 'Stripe',
		},
		{
			name: 'sandbox_publishable_key',
			label: 'Sandbox Publisher Key',
			help: '',
			type: 'text',
			mode: 'test',
			max_length: 200,
			required: false,
			default: '',
		},
		{
			name: 'sandbox_secret_key',
			label: 'Sandbox Secret Key',
			help: '',
			type: 'secret',
			mode: 'test',
			max_length: 500,
			required: false,
			default: '',
		},
		{
			name: 'secret_key',
			label: 'Live Secret Key',
			help: '',
			type: 'secret',
			mode: 'live',
			max_length: 500,
			required: false,
			default: '',
		},
		{
			name: 'payment_method_card',
			label: 'Cards',
			help: '',
			type: 'toggle',
			mode: null,
			max_length: null,
			required: false,
			default: true,
		},
	],
	values: {
		title: 'Card',
		sandbox_publishable_key: 'pk_test_1',
		payment_method_card: true,
	},
	secrets: {
		sandbox_secret_key: { has_value: true },
		secret_key: { has_value: false },
	},
};

export const offlineRaw = {
	id: 'offline',
	title: 'Offline',
	description: 'Pay later',
	subscription_support: false,
	enabled: true,
	mode: 'live',
	fields: [
		{
			name: 'title',
			label: 'Title',
			help: '',
			type: 'text',
			mode: null,
			max_length: 200,
			required: true,
			default: 'Offline',
		},
		{
			name: 'instruction',
			label: 'Instruction',
			help: '',
			type: 'textarea',
			mode: null,
			max_length: 2000,
			required: false,
			default: '',
		},
	],
	values: { title: 'Bank transfer', instruction: 'Pay by transfer.' },
	secrets: {},
};

const khanColors = {
	primary: '#1865f2',
	secondary: '#909296',
	success: '#00a60e',
	danger: '#d92916',
	warning: '#ffb100',
	link: '#1865f2',
	heading: '#0b2149',
	text: '#21242c',
	muted: '#5f6167',
	info: '#1865f2',
	border: '#dcdcdd',
	surface: '#ffffff',
	background: '#f7f8fa',
};

const classicColors = {
	primary: '#6e42d3',
	secondary: '#6e42d3',
	success: '#35bd4c',
	danger: '#cc1818',
	warning: '#b45309',
	link: '#6e42d3',
	heading: '#000d25',
	text: '#000d25',
	muted: '#7a8b9a',
	info: '#2271b1',
	border: '#dcdcde',
	surface: '#ffffff',
	background: '#f0f0f1',
};

export const designRaw = {
	preset: 'wordpress',
	colors: khanColors,
	button_radius: 4,
	density: 'comfortable',
	on_primary: '#ffffff',
	primary_passes: true,
	white_passes: true,
	admin_scheme: 'ocean',
	admin_colors: {
		...khanColors,
		primary: '#567958',
		secondary: '#567958',
		info: '#567958',
		link: '#2271b1',
		border: '#c3c4c7',
		background: '#f0f0f1',
	},
	presets: [
		{
			preset: 'wordpress',
			label: 'WordPress',
			colors: khanColors,
			button_radius: 4,
			density: 'comfortable',
		},
		{
			preset: 'khan',
			label: 'Khan Academy',
			colors: khanColors,
			button_radius: 4,
			density: 'comfortable',
		},
		{
			preset: 'classic',
			label: 'Classic',
			colors: classicColors,
			button_radius: 4,
			density: 'comfortable',
		},
	],
};

export const xapiRaw = {
	settings: {
		enabled: 0,
		endpoint: 'https://lrs.example.com/xapi',
		key: 'lrs-user',
		actor: 'pseudonym',
		groups: { enrolled: 1, lessons: 0 },
		responses: 1,
		retention_days: 14,
	},
	has_secret: true,
	locked: { endpoint: false, key: false, secret: false },
	group_labels: {
		enrolled: 'Course enrolment',
		lessons: 'Lesson completion',
	},
	counts: { pending: 2, sent: 5, failed: 1, skipped: 0 },
	health: { last_error: 'HTTP 401' },
	failures: [
		{
			id: '9',
			kind: 'enrolled',
			student_id: '3',
			tries: '5',
			last_error: 'HTTP 401',
			created_at: '2026-10-30 10:00:00',
		},
	],
};

export const aiRaw = {
	settings: {
		enabled: false,
		exam_converter_enabled: false,
		provider: 'anthropic',
		model: 'claude-test',
		hints: true,
		explanations: true,
		guests: false,
		daily_limit: 30,
		max_tokens: 400,
		timeout: 20,
		has_key: true,
		key_hint: '…a1b2',
		key_from: 'stored',
		providers: {
			anthropic: 'Anthropic (Claude)',
			openai: 'OpenAI',
			gemini: 'Google (Gemini)',
		},
	},
	stats: {
		requests: 4,
		failures: 1,
		cached: 0,
		input_tokens: 10,
		output_tokens: 20,
		last_error: '',
		last_ok: '',
	},
	practice_style: 'standard',
};

export const mcpRaw = {
	server_url: 'https://site.test/wp-json/ohmylms/v1/mcp',
	guide_url: 'https://site.test/docs/MCP.md',
	providers: [
		{
			id: 'openai',
			label: 'OpenAI',
			configured: true,
			created: '2026-10-01',
		},
		{
			id: 'anthropic',
			label: 'Anthropic',
			configured: false,
			created: null,
		},
		{ id: 'gemini', label: 'Gemini', configured: false, created: null },
	],
	tools: [ 'list_courses' ],
	implementation: 'WordPress Abilities API + MCP Adapter',
	status: { ready: true, abilities_api: true, adapter: true, message: '' },
	abilities: [ 'ohmylms/list-courses' ],
};

export const hubIconsRaw = {
	kinds: [
		{ key: 'lesson', group: 'activity', label: 'Lesson', id: 0, url: '' },
		{
			key: 'quiz',
			group: 'activity',
			label: 'Quiz',
			id: 7,
			url: 'https://site.test/quiz.png',
		},
		{
			key: 'logo',
			group: 'logo',
			label: 'Logo for every user type',
			id: 0,
			url: '',
		},
	],
};
