/** An add-on as returned by `GET /ohmylms/v1/integrations`. */
export interface Integration {
	id: string;
	label: string;
	description: string;
	icon: string;
	categories: string[];
	dependency: string | null;
	required_plan: string | null;
	enabled: boolean;
	available: boolean;
	unavailable_reason: string | null;
	configurable: boolean;
	/** True only in an update response when the admin menu changes. */
	requires_reload: boolean;
}

interface FieldBase {
	name: string;
	label: string;
	help: string;
	required: boolean;
	max_length: number;
}
export interface TextField extends FieldBase {
	kind: 'text';
	value: string;
}
/** Write-only: the server reports whether a secret exists and a short hint, never the secret. */
export interface SecretField extends FieldBase {
	kind: 'secret';
	configured: boolean;
	hint: string;
}
export type SettingsField = TextField | SecretField;

/** A value to copy into the provider's console (for example a redirect URI). */
export interface ReadonlyValue {
	name: string;
	label: string;
	help: string;
	value: string;
}

export interface IntegrationSettings {
	integration: string;
	configured: boolean;
	fields: SettingsField[];
	readonly: ReadonlyValue[];
}

/** Fields to send: text/secret replacements as strings, `null` to clear an optional secret. */
export type SettingsChanges = Record< string, string | null >;

/** What the user has typed: text values and new secrets by field name, plus secrets marked for removal. */
export interface SettingsDraft {
	values: Record< string, string >;
	cleared: Record< string, boolean >;
}

export type FieldErrors = Record< string, string >;

export interface IntegrationFilters {
	search: string;
	category: string;
}

export interface Option {
	value: string;
	label: string;
}

/** A load failure; `permission` hides the retry action because retrying cannot help. */
export interface LoadError {
	message: string;
	permission: boolean;
}
