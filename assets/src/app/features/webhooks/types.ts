export interface MappingRow {
	key: string;
	value: string;
}
export interface WebhookInput {
	name: string;
	webhook_url: string;
	trigger_event: string;
	http_method: string;
	data_type: string;
	status: 'active' | 'inactive';
	data_mapping: MappingRow[];
}
export interface Webhook extends WebhookInput {
	id: number;
	secret_hint: string;
	created_at: string;
	updated_at: string;
}
export interface Option {
	value: string;
	label: string;
}
export interface WebhookOptions {
	triggers: Option[];
	methods: Option[];
	data_types: Option[];
	statuses: Option[];
}
export interface Delivery {
	id: number;
	delivery_id: string;
	status: 'success' | 'failed';
	response_code: number;
	error_message: string;
	created_at: string;
}
export type SecretWebhook = Webhook & { secret: string };
