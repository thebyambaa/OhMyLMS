/** Who receives an email: the student, or staff (course creators and instructors). */
export type RecipientType = 'student' | 'creator';

/** A merge tag an email supports, written `{tag}` or `[tag]` in its text. */
export interface MergeTag {
	tag: string;
	label: string;
	description: string;
}

/** Text fields an administrator can customize; the others keep their translated default. */
export type TextField =
	| 'subject'
	| 'heading'
	| 'additional_content'
	| 'button_text'
	| 'button_link'
	| 'footer_text'
	| 'course_suggestion_text';

/** Default text of each field in the administrator's language; `course_suggestion_text` is `null` when the email has none. */
export type EmailDefaults = Record<
	Exclude< TextField, 'course_suggestion_text' >,
	string
> & { course_suggestion_text: string | null };

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
	/** What each field is when it is not customized. */
	defaults: EmailDefaults;
	/** Whether each field holds saved text (sent as written) instead of the translated default. */
	customized: Record< TextField, boolean >;
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

/** Request body of the update, preview and test routes. `null` for a text field means "use the default". */
export type EmailInput = Partial<
	Pick< EmailTemplate, 'enabled' | 'recipient_email' > &
		Record< TextField, string | null >
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
