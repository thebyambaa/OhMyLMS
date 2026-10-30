/** Publication state of a course as returned by the REST API. */
export type CourseStatus =
	'draft' | 'pending' | 'publish' | 'future' | 'private' | 'trash';

/** Statuses a client may write (the trash is reached by deleting). */
export type WritableStatus = Exclude< CourseStatus, 'trash' >;

export type Level = 'beginner' | 'experience' | 'expert' | 'all';
export type PriceType = 'free' | 'paid';
export type AccessType = 'public' | 'password_protected';

/** Estimated time to complete the course. */
export interface Duration {
	hours: number;
	minutes: number;
}

/** A course as listed (`context=view`). Money is a fixed-decimal string; dates are `YYYY-MM-DD`. */
export interface Course {
	id: number;
	title: string;
	slug: string;
	status: CourseStatus;
	permalink: string;
	author_id: number;
	/** Empty unless the user can edit other people's courses. */
	author_name: string;
	level: Level;
	price_type: PriceType;
	regular_price: string;
	sale_price: string | null;
	sale_start_date: string | null;
	sale_end_date: string | null;
	/** The price that applies today. */
	price: string;
	currency: string;
	access_type: AccessType;
	has_password: boolean;
	available_date: string | null;
	duration: Duration | null;
	has_capacity: boolean;
	capacity: number | null;
	enable_reviews: boolean;
	thumbnail_id: number | null;
	thumbnail_url: string | null;
	enrolled_count: number;
	/** Site-local `YYYY-MM-DDTHH:MM:SS` publish time of a scheduled course. */
	scheduled_at: string | null;
	created_at: string;
	updated_at: string;
}

/** A course as edited (`context=edit`). */
export interface CourseDetail extends Course {
	description: string;
	excerpt: string;
	learning_outcomes: string;
	target_audience: string;
	requirements: string;
	/** Present for users who can edit the course. */
	password: string;
}

/** The writable fields of a course, exactly as the REST API accepts them. */
export interface CourseInput {
	title: string;
	slug: string;
	status: WritableStatus;
	scheduled_at?: string;
	description: string;
	excerpt: string;
	level: Level;
	price_type: PriceType;
	regular_price: string;
	sale_price: string | null;
	sale_start_date: string | null;
	sale_end_date: string | null;
	access_type: AccessType;
	/** Only sent for password-protected courses. */
	password?: string;
	available_date: string | null;
	duration: Duration | null;
	has_capacity: boolean;
	capacity: number | null;
	enable_reviews: boolean;
	learning_outcomes: string;
	target_audience: string;
	requirements: string;
	thumbnail_id: number | null;
}

/** A labelled choice. */
export interface Option {
	value: string;
	label: string;
}

/** Choices, limits and abilities from `GET /courses/options`. */
export interface CourseOptions {
	statuses: Option[];
	levels: Option[];
	price_types: Option[];
	access_types: Option[];
	currency: {
		code: string;
		symbol: string;
		position: string;
		decimals: number;
	};
	limits: {
		max_title: number;
		max_text: number;
		max_price: number;
		max_capacity: number;
		max_password: number;
	};
	abilities: {
		publish: boolean;
		edit_others: boolean;
		upload_files: boolean;
		/** Classic builder address (without the id); empty when the user cannot open it. */
		builder_url: string;
	};
}

/** The certificate template a course awards, with the templates the user may assign. */
export interface CertificateAssignment {
	course_id: number;
	/** Assigned template ID, or 0 for none. */
	certificate_id: number;
	choices: { id: number; title: string; status: string }[];
}

/** An image from the media library. */
export interface MediaImage {
	id: number;
	url: string;
	thumbnail_url: string;
	title: string;
}

/** The list filters. */
export interface CourseFilters {
	search: string;
	status: string;
	price_type: string;
	created_after: string;
	created_before: string;
}

/** The editor state. Numbers stay strings until the form is validated and converted. */
export interface FormState {
	title: string;
	slug: string;
	status: WritableStatus;
	scheduled_at: string;
	description: string;
	excerpt: string;
	level: Level;
	price_type: PriceType;
	regular_price: string;
	sale_price: string;
	sale_start_date: string;
	sale_end_date: string;
	access_type: AccessType;
	/** The course password (shown to users who can edit the course). */
	password: string;
	available_date: string;
	duration_hours: string;
	duration_minutes: string;
	has_capacity: boolean;
	capacity: string;
	enable_reviews: boolean;
	learning_outcomes: string;
	target_audience: string;
	requirements: string;
	thumbnail: { id: number; url: string | null } | null;
}

/** Field errors keyed by field name. */
export type FormErrors = Record< string, string >;
