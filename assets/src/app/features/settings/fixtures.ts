import type {
	CodeName,
	CurrencyOption,
	GroupId,
	GroupResponse,
	GroupValues,
	PageOption,
} from './types';

/** Test fixtures only; production components never import this module. */
export const general: GroupValues< 'general' > = {
	course_page_id: 11,
	checkout_page_id: 12,
	thank_you_page_id: 0,
	student_dashboard_page_id: 0,
	student_profile_page_id: 0,
	student_courses_page_id: 0,
	registration_page_id: 0,
	terms_page_id: 0,
	privacy_policy_page_id: 0,
	profile_page_id: 0,
};

export const branding: GroupValues< 'branding' > = {
	color_preset: 'royal-purple',
	primary_color: '#6e42d3',
	primary_hover_color: '#1447bc',
	heading_color: '#000d25',
	body_text_color: '#52525b',
	progress_color: '#35bd4c',
	video_player_logo: '',
	video_player_logo_bg_color: '#6e42d3',
};

export const account: GroupValues< 'account-and-privacy' > = {
	allow_purchase_without_login: true,
	require_email_verification: false,
	checkout_phone_field: 'optional',
	privacy_policy_message: 'We use your data to run the course.',
};

export const permalink: GroupValues< 'permalink' > = {
	course_base: 'ohmylms-courses',
	lesson_base: 'course-lessons',
	quiz_base: 'course-quizzes',
	category_base: 'course-categories',
	tag_base: 'course-tags',
	membership_base: 'members',
};

export const advanced: GroupValues< 'advanced' > = {
	use_custom_video_player: true,
};

export const currency: GroupValues< 'currency' > = {
	currency: 'USD',
	currency_pos: 'left',
	price_thousand_sep: ',',
	price_decimal_sep: '.',
	price_num_decimals: 2,
};

export const tax: GroupValues< 'tax' > = {
	tax_enabled: false,
	tax_label: 'Tax',
	prices_include_tax: false,
	eu_vat_enabled: false,
	disable_vat_validation: false,
	vat_number_label: 'VAT Number',
	fallback_tax_rate: 0,
	tax_rates: [],
};

export const fixtures = {
	general,
	branding,
	'account-and-privacy': account,
	permalink,
	advanced,
	currency,
	tax,
} as const;

/**
 * A server response for a group.
 *
 * @param group   Group id.
 * @param values  Values (defaults to the group's fixture).
 * @param changed Names reported as changed.
 * @return A response object.
 */
export function response< G extends GroupId >(
	group: G,
	values: GroupValues< G > = fixtures[ group ] as GroupValues< G >,
	changed: string[] = []
): GroupResponse< G > {
	return { group, values, changed };
}

export const pages: PageOption[] = [
	{ id: 11, title: 'All courses', slug: 'ohmylms-all-courses' },
	{ id: 12, title: 'Checkout', slug: 'checkout' },
	{ id: 13, title: 'Thanks', slug: 'thanks' },
];

export const currencies: CurrencyOption[] = [
	{ code: 'USD', name: 'United States (US) dollar', symbol: '$' },
	{ code: 'EUR', name: 'Euro', symbol: '€' },
];

export const countries: CodeName[] = [
	{ code: 'DE', name: 'Germany' },
	{ code: 'US', name: 'United States (US)' },
];

export const usStates: CodeName[] = [
	{ code: 'CA', name: 'California' },
	{ code: 'NY', name: 'New York' },
];
