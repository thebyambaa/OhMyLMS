/** Publication state of a plan. */
export type MembershipStatus = 'draft' | 'publish';

/** How often the price is charged. */
export type BillingPeriod = 'one_time' | 'year' | 'month' | 'week' | 'day';

/** A course reference returned by the REST API. */
export interface CourseRef {
	id: number;
	title: string;
	status: string;
}

/** A plan as listed (`context=view`). Money is a fixed-decimal string; dates are `YYYY-MM-DD`. */
export interface Membership {
	id: number;
	name: string;
	slug: string;
	status: MembershipStatus;
	permalink: string;
	regular_price: string;
	sale_price: string | null;
	sale_start_date: string | null;
	sale_end_date: string | null;
	/** The price that applies today. */
	price: string;
	sign_up_fee: string | null;
	billing_period: BillingPeriod;
	billing_interval: number;
	currency: string;
	all_learning_content: boolean;
	course_count: number;
	member_count: number;
	created_at: string;
	updated_at: string;
}

/** A plan as edited (`context=edit`). */
export interface MembershipDetail extends Membership {
	description: string;
	course_ids: number[];
	courses: CourseRef[];
	excluded_course_ids: number[];
	excluded_courses: CourseRef[];
	curriculum_item_ids: number[];
	extension_settings: ExtensionSettings;
}

/** Values of extensions registered for memberships, keyed by extension ID (its namespace). */
export type ExtensionSettings = Record< string, Record< string, unknown > >;

/** The writable fields of a plan, exactly as the REST API accepts them. */
export interface MembershipInput {
	name: string;
	status: MembershipStatus;
	description: string;
	regular_price: string;
	sale_price: string | null;
	sale_start_date: string | null;
	sale_end_date: string | null;
	sign_up_fee: string | null;
	billing_period: BillingPeriod;
	billing_interval: number;
	all_learning_content: boolean;
	course_ids: number[];
	excluded_course_ids: number[];
	curriculum_item_ids: number[];
	/** Only the namespaces to change; the server keeps the others. */
	extension_settings?: ExtensionSettings;
}

/** A labelled choice. */
export interface Option {
	value: string;
	label: string;
}

/** Site currency settings. */
export interface CurrencyInfo {
	code: string;
	symbol: string;
	position: string;
	decimals: number;
}

/** Choices and limits from `GET /memberships/options`. */
export interface MembershipOptions {
	statuses: Option[];
	billing_periods: Option[];
	currency: CurrencyInfo;
	limits: {
		max_courses: number;
		max_curriculum_items: number;
		max_price: number;
		max_name: number;
	};
	/** IDs of extensions whose membership settings the server accepts. */
	extension_settings: string[];
}

/** A plan member (read only). */
export interface Member {
	id: number;
	user_id: number;
	display_name: string;
	email: string;
	status: string;
	order_id: number;
	start_date: string | null;
	end_date: string | null;
}

/** The editor state. Numbers stay strings until the form is validated and converted. */
export interface FormState {
	name: string;
	status: MembershipStatus;
	description: string;
	regular_price: string;
	sale_price: string;
	sale_start_date: string;
	sale_end_date: string;
	sign_up_fee: string;
	billing_period: BillingPeriod;
	billing_interval: string;
	all_learning_content: boolean;
	courses: CourseRef[];
	excluded_courses: CourseRef[];
	/** Kept as loaded and sent back unchanged; the editor does not change curriculum rules. */
	curriculum_item_ids: number[];
	/** Edited by the registered `membership-settings` extensions. */
	extension_settings: ExtensionSettings;
}

/** Field errors keyed by field name. */
export type FormErrors = Record< string, string >;
