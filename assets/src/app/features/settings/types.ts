/** Ids of the settings groups, in tab order. */
export const GROUP_IDS = [
	'general',
	'branding',
	'account-and-privacy',
	'permalink',
	'advanced',
	'currency',
	'tax',
] as const;

export type GroupId = ( typeof GROUP_IDS )[ number ];

/** Field names per group; the server rejects any other key with 400. */
export const FIELD_NAMES = {
	general: [
		'course_page_id',
		'checkout_page_id',
		'thank_you_page_id',
		'student_dashboard_page_id',
		'student_profile_page_id',
		'student_courses_page_id',
		'registration_page_id',
		'terms_page_id',
		'privacy_policy_page_id',
		'profile_page_id',
	],
	branding: [
		'color_preset',
		'primary_color',
		'primary_hover_color',
		'heading_color',
		'body_text_color',
		'progress_color',
		'video_player_logo',
		'video_player_logo_bg_color',
	],
	'account-and-privacy': [
		'allow_purchase_without_login',
		'require_email_verification',
		'checkout_phone_field',
		'privacy_policy_message',
	],
	permalink: [
		'course_base',
		'lesson_base',
		'quiz_base',
		'category_base',
		'tag_base',
		'membership_base',
	],
	advanced: [ 'use_custom_video_player' ],
	currency: [
		'currency',
		'currency_pos',
		'price_thousand_sep',
		'price_decimal_sep',
		'price_num_decimals',
	],
	tax: [
		'tax_enabled',
		'tax_label',
		'prices_include_tax',
		'eu_vat_enabled',
		'disable_vat_validation',
		'vat_number_label',
		'fallback_tax_rate',
		'tax_rates',
	],
} as const satisfies Record< GroupId, readonly string[] >;

export type FieldName< G extends GroupId > =
	( typeof FIELD_NAMES )[ G ][ number ];

/** One tax rate row (`country_wide` rows have an empty `state`). */
export interface TaxRate {
	country: string;
	state: string;
	rate: number;
	country_wide: boolean;
}

export type SettingValue = boolean | number | string | TaxRate[];

/** Every value of a group, keyed by field name. */
export type GroupValues< G extends GroupId = GroupId > = Record<
	FieldName< G >,
	SettingValue
>;

/** Response of `GET` and of every write (`changed` is empty for reads). */
export interface GroupResponse< G extends GroupId = GroupId > {
	group: G;
	values: GroupValues< G >;
	changed: string[];
}

/** Field name to message, from validation or from the server's `data.params`. */
export type FieldErrors = Record< string, string >;

export interface PageOption {
	id: number;
	title: string;
	slug: string;
}

export interface CurrencyOption {
	code: string;
	name: string;
	symbol: string;
}

export interface CodeName {
	code: string;
	name: string;
}

export interface RestoredPage {
	key: string;
	page_id: number;
	title: string;
}

export interface RestorePagesResult {
	restored: RestoredPage[];
	values: GroupValues< 'general' >;
}

export interface SearchPagesParams {
	search?: string;
	include?: readonly number[];
	perPage?: number;
}

/** A failed request, reduced to what the screen shows. */
export interface FailureView {
	message: string;
	/** True for 401/403: retrying cannot help. */
	permission: boolean;
	fieldErrors: FieldErrors;
}
