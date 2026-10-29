/** Who receives an email: the student, or staff (course creators and instructors). */
export type RecipientType = 'student' | 'creator';

/** A merge tag an email supports, written `{tag}` or `[tag]` in its text. */
export interface MergeTag {
	tag: string;
	label: string;
	description: string;
}

/** One system email as the REST API returns it. */
export interface EmailTemplate {
	id: string;
	title: string;
	description: string;
	recipient_type: RecipientType;
	enabled: boolean;
	subject: string;
	heading: string;
	additional_content: string;
	button_text: string;
	button_link: string;
	footer_text: string;
	/** `null` when the email has no course suggestion text. */
	course_suggestion_text: string | null;
	recipient_email: string[];
	tags: MergeTag[];
}

/** Fields the editor changes. */
export interface EmailDraft {
	subject: string;
	heading: string;
	additional_content: string;
	button_text: string;
	button_link: string;
	footer_text: string;
	course_suggestion_text: string;
	/** One address per line, or separated by commas. */
	recipient_email: string;
}

export type DraftField = keyof EmailDraft;

/** Request body of the update, preview and test routes. */
export type EmailInput = Partial<
	Omit<
		EmailTemplate,
		'id' | 'title' | 'description' | 'tags' | 'recipient_type'
	>
>;

export type ButtonPosition = 'left' | 'center' | 'right';

/** Global email settings. */
export interface EmailSettings {
	branding_image: string;
	base_color: string;
	background_color: string;
	body_background_color: string;
	body_text_color: string;
	button_position: ButtonPosition;
	sender_name: string;
	sender_email: string;
	footer_text: string;
}

export type SettingsField = keyof EmailSettings;

/** Result of rendering an email with sample data. */
export interface EmailPreview {
	subject: string;
	html: string;
	unknown_tags: string[];
}

/** Result of sending a test email. */
export interface TestResult {
	sent: true;
	to: string;
	subject: string;
}

export interface EmailFilters {
	search: string;
	recipient_type: 'all' | RecipientType;
}
