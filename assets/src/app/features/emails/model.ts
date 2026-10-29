import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type {
	ButtonPosition,
	DraftField,
	EmailDraft,
	EmailInput,
	EmailPreview,
	EmailSettings,
	EmailTemplate,
	MergeTag,
	RecipientType,
	SettingsField,
	TestResult,
} from './types';

/** Limits shared with `EmailManager` in PHP. */
export const LIMITS = {
	subject: 255,
	heading: 200,
	button_text: 60,
	button_link: 2048,
	additional_content: 20000,
	footer_text: 5000,
	course_suggestion_text: 5000,
	recipients: 10,
	sender_name: 100,
	settings_footer: 1000,
} as const;

export const BUTTON_POSITIONS: readonly ButtonPosition[] = [
	'left',
	'center',
	'right',
];

/** Fields that can contain merge tags. */
export const TAG_FIELDS = [
	'subject',
	'heading',
	'additional_content',
	'button_text',
	'footer_text',
	'course_suggestion_text',
] as const satisfies readonly DraftField[];

/** Route of the email list. */
export const LIST_PATH = '/emails';

/**
 * Route of one email's editor. The shell has flat routes, so the id travels in the query string;
 * `EmailsPage` also honours an `:id` route parameter.
 *
 * @param id Email id.
 * @return Router path.
 */
export function editorPath( id: string ): string {
	return `${ LIST_PATH }?email=${ encodeURIComponent( id ) }`;
}

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid email data.', 'ohmylms' ),
	} );
}

export function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function list( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

/**
 * Validate an email id before it is placed in a URL.
 *
 * @param value Candidate id.
 * @return The id.
 */
export function emailId( value: unknown ): string {
	if ( 'string' !== typeof value || ! /^[a-z][a-z0-9_]*$/.test( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function parseTag( value: unknown ): MergeTag {
	const item = record( value );
	return {
		tag: text( item.tag ),
		label: text( item.label ),
		description: text( item.description ),
	};
}

/**
 * Validate one email from the API.
 *
 * @param value Parsed JSON.
 * @return The typed email.
 */
export function parseEmail( value: unknown ): EmailTemplate {
	const item = record( value );
	const recipientType = item.recipient_type;
	if ( 'student' !== recipientType && 'creator' !== recipientType ) {
		throw invalidResponse();
	}
	if ( 'boolean' !== typeof item.enabled ) {
		throw invalidResponse();
	}
	const suggestion = item.course_suggestion_text;
	if ( null !== suggestion && 'string' !== typeof suggestion ) {
		throw invalidResponse();
	}
	return {
		id: emailId( item.id ),
		title: text( item.title ),
		description: text( item.description ),
		recipient_type: recipientType as RecipientType,
		enabled: item.enabled,
		subject: text( item.subject ),
		heading: text( item.heading ),
		additional_content: text( item.additional_content ),
		button_text: text( item.button_text ),
		button_link: text( item.button_link ),
		footer_text: text( item.footer_text ),
		course_suggestion_text: suggestion,
		recipient_email: list( item.recipient_email ).map( text ),
		tags: list( item.tags ).map( parseTag ),
	};
}

/**
 * Validate the global settings from the API.
 *
 * @param value Parsed JSON.
 * @return The typed settings.
 */
export function parseSettings( value: unknown ): EmailSettings {
	const item = record( value );
	const position = item.button_position;
	if ( ! BUTTON_POSITIONS.includes( position as ButtonPosition ) ) {
		throw invalidResponse();
	}
	return {
		branding_image: text( item.branding_image ),
		base_color: text( item.base_color ),
		background_color: text( item.background_color ),
		body_background_color: text( item.body_background_color ),
		body_text_color: text( item.body_text_color ),
		button_position: position as ButtonPosition,
		sender_name: text( item.sender_name ),
		sender_email: text( item.sender_email ),
		footer_text: text( item.footer_text ),
	};
}

/**
 * Validate a preview response.
 *
 * @param value Parsed JSON.
 * @return The typed preview.
 */
export function parsePreview( value: unknown ): EmailPreview {
	const item = record( value );
	return {
		subject: text( item.subject ),
		html: text( item.html ),
		unknown_tags: list( item.unknown_tags ).map( text ),
	};
}

/**
 * Validate a test-email response.
 *
 * @param value Parsed JSON.
 * @return The typed result.
 */
export function parseTestResult( value: unknown ): TestResult {
	const item = record( value );
	if ( true !== item.sent ) {
		throw invalidResponse();
	}
	return { sent: true, to: text( item.to ), subject: text( item.subject ) };
}

/**
 * Split typed recipients (lines, spaces, commas or semicolons) into non-empty addresses.
 *
 * @param value Text from the recipients field.
 * @return Addresses as typed.
 */
export function parseRecipients( value: string ): string[] {
	return value
		.split( /[\s,;]+/ )
		.map( ( address ) => address.trim() )
		.filter( Boolean );
}

/**
 * Form state for an email.
 *
 * @param email Email from the API.
 * @return Editable draft.
 */
export function toDraft( email: EmailTemplate ): EmailDraft {
	return {
		subject: email.subject,
		heading: email.heading,
		additional_content: email.additional_content,
		button_text: email.button_text,
		button_link: email.button_link,
		footer_text: email.footer_text,
		course_suggestion_text: email.course_suggestion_text ?? '',
		recipient_email: email.recipient_email.join( '\n' ),
	};
}

const DRAFT_FIELDS: readonly DraftField[] = [
	'subject',
	'heading',
	'additional_content',
	'button_text',
	'button_link',
	'footer_text',
	'course_suggestion_text',
	'recipient_email',
];

/**
 * Fields whose draft value differs from the saved email.
 *
 * @param draft Current form state.
 * @param email Saved email.
 * @return Names of the changed fields.
 */
export function changedFields(
	draft: EmailDraft,
	email: EmailTemplate
): DraftField[] {
	const saved = toDraft( email );
	return DRAFT_FIELDS.filter( ( field ) => {
		if (
			'course_suggestion_text' === field &&
			null === email.course_suggestion_text
		) {
			return false;
		}
		if ( 'recipient_email' === field ) {
			return (
				parseRecipients( draft[ field ] ).join( '\n' ) !==
				parseRecipients( saved[ field ] ).join( '\n' )
			);
		}
		return draft[ field ] !== saved[ field ];
	} );
}

/**
 * Request body for the given draft fields. Single-line fields are trimmed; recipients become a list.
 *
 * @param draft  Current form state.
 * @param fields Fields to send.
 * @return Body for the update, preview or test route.
 */
export function toInput(
	draft: EmailDraft,
	fields: readonly DraftField[]
): EmailInput {
	const input: Record< string, string | string[] > = {};
	for ( const field of fields ) {
		if ( 'recipient_email' === field ) {
			input[ field ] = parseRecipients( draft[ field ] );
		} else if (
			'subject' === field ||
			'heading' === field ||
			'button_text' === field ||
			'button_link' === field
		) {
			input[ field ] = draft[ field ].trim();
		} else {
			input[ field ] = draft[ field ];
		}
	}
	return input as EmailInput;
}

const TAG_PATTERN =
	/\{([A-Za-z][A-Za-z0-9_]*)\}|\[([A-Za-z][A-Za-z0-9]*_[A-Za-z0-9_]*)\]/g;

/**
 * Merge tags in text that the email does not support. `{name}` is always checked; `[name]` only when it
 * contains an underscore, so literal text such as `[Important]` is left alone. Mirrors
 * `Emails::find_unknown_tags()` in PHP.
 *
 * @param value   Text to scan.
 * @param allowed Supported tag names.
 * @return Unknown tags as written, without duplicates.
 */
export function findUnknownTags(
	value: string,
	allowed: readonly string[]
): string[] {
	const found = new Set< string >();
	for ( const match of value.matchAll( TAG_PATTERN ) ) {
		const name = ( match[ 1 ] ?? match[ 2 ] ?? '' ).toLowerCase();
		if ( ! allowed.includes( name ) ) {
			found.add( match[ 0 ] );
		}
	}
	return [ ...found ];
}

/** Error messages by field. */
export type FieldErrors< F extends string > = Partial< Record< F, string > >;

const SIMPLE_EMAIL = /^[^\s@<>",]+@[^\s@<>",]+\.[^\s@<>",]+$/;

export function isHttpUrl( value: string ): boolean {
	if ( ! /^https?:\/\/\S+$/i.test( value ) ) {
		return false;
	}
	try {
		new URL( value );
		return true;
	} catch {
		return false;
	}
}

function stripTags( value: string ): string {
	return value
		.replace( /<(script|style)\b[^>]*>[\s\S]*?<\/\1\s*>/gi, '' )
		.replace( /<[^>]*>/g, '' )
		.trim();
}

function tooLong( limit: number ): string {
	return sprintf(
		/* translators: %d: maximum characters. */
		__( 'Use at most %d characters.', 'ohmylms' ),
		limit
	);
}

/**
 * Validate an email draft the way the server does, field by field (merge tags excluded, see
 * {@link validateTags}).
 *
 * @param draft Current form state.
 * @param email Saved email (its type decides what is valid).
 * @return Message per invalid field; empty when the draft is valid.
 */
export function validateDraft(
	draft: EmailDraft,
	email: EmailTemplate
): FieldErrors< DraftField > {
	const errors: FieldErrors< DraftField > = {};
	const subject = draft.subject.trim();
	const heading = draft.heading.trim();

	if ( ! subject ) {
		errors.subject = __( 'Enter a subject.', 'ohmylms' );
	} else if ( subject.length > LIMITS.subject ) {
		errors.subject = tooLong( LIMITS.subject );
	}
	if ( ! heading ) {
		errors.heading = __( 'Enter a heading.', 'ohmylms' );
	} else if ( heading.length > LIMITS.heading ) {
		errors.heading = tooLong( LIMITS.heading );
	}
	if (
		! stripTags( draft.additional_content ) &&
		! /<img\b/i.test( draft.additional_content )
	) {
		errors.additional_content = __( 'Enter the message.', 'ohmylms' );
	} else if ( draft.additional_content.length > LIMITS.additional_content ) {
		errors.additional_content = tooLong( LIMITS.additional_content );
	}
	if ( draft.button_text.trim().length > LIMITS.button_text ) {
		errors.button_text = tooLong( LIMITS.button_text );
	}
	const link = draft.button_link.trim();
	if ( link && ( link.length > LIMITS.button_link || ! isHttpUrl( link ) ) ) {
		errors.button_link = __(
			'Enter a full address starting with http:// or https://.',
			'ohmylms'
		);
	}
	if ( draft.footer_text.length > LIMITS.footer_text ) {
		errors.footer_text = tooLong( LIMITS.footer_text );
	}
	if (
		null !== email.course_suggestion_text &&
		draft.course_suggestion_text.length > LIMITS.course_suggestion_text
	) {
		errors.course_suggestion_text = tooLong(
			LIMITS.course_suggestion_text
		);
	}

	const addresses = parseRecipients( draft.recipient_email );
	if ( addresses.length > LIMITS.recipients ) {
		errors.recipient_email = sprintf(
			/* translators: %d: maximum recipients. */
			__( 'Use at most %d recipients.', 'ohmylms' ),
			LIMITS.recipients
		);
	} else if (
		addresses.some( ( address ) => ! SIMPLE_EMAIL.test( address ) )
	) {
		errors.recipient_email = __(
			'Every recipient must be a valid email address.',
			'ohmylms'
		);
	} else if ( addresses.length && 'student' === email.recipient_type ) {
		errors.recipient_email = __(
			'Student emails always go to the student.',
			'ohmylms'
		);
	}
	return errors;
}

/**
 * Merge tag problems of a draft. A preview can still be rendered with these (it lists them); saving cannot.
 *
 * @param draft Current form state.
 * @param email Saved email (its tags decide what is supported).
 * @return Message per field that uses an unsupported tag.
 */
export function validateTags(
	draft: EmailDraft,
	email: EmailTemplate
): FieldErrors< DraftField > {
	const allowed = email.tags.map( ( tag ) => tag.tag );
	const errors: FieldErrors< DraftField > = {};
	for ( const field of TAG_FIELDS ) {
		const unknown = findUnknownTags( draft[ field ], allowed );
		if ( unknown.length ) {
			errors[ field ] = sprintf(
				/* translators: %s: comma separated merge tags. */
				__( 'This email does not support: %s', 'ohmylms' ),
				unknown.join( ', ' )
			);
		}
	}
	return errors;
}

/**
 * Insert a merge tag into text, replacing the selection.
 *
 * @param value Current text.
 * @param start Selection start.
 * @param end   Selection end.
 * @param tag   Tag name, inserted as `{tag}`.
 * @return New text and the caret position after the tag.
 */
export function insertTag(
	value: string,
	start: number,
	end: number,
	tag: string
): { value: string; caret: number } {
	const from = Math.max( 0, Math.min( start, value.length ) );
	const to = Math.max( from, Math.min( end, value.length ) );
	const snippet = `{${ tag }}`;
	return {
		value: value.slice( 0, from ) + snippet + value.slice( to ),
		caret: from + snippet.length,
	};
}

/**
 * Validate global settings the way the server does.
 *
 * @param settings Current form state.
 * @return Message per invalid field; empty when valid.
 */
export function validateSettings(
	settings: EmailSettings
): FieldErrors< SettingsField > {
	const errors: FieldErrors< SettingsField > = {};
	for ( const field of [
		'base_color',
		'background_color',
		'body_background_color',
		'body_text_color',
	] as const ) {
		if ( ! /^#[0-9A-Fa-f]{6}$/.test( settings[ field ].trim() ) ) {
			errors[ field ] = __( 'Use a color like #6E42D3.', 'ohmylms' );
		}
	}
	const logo = settings.branding_image.trim();
	if ( logo && ( logo.length > LIMITS.button_link || ! isHttpUrl( logo ) ) ) {
		errors.branding_image = __(
			'Enter a full image address starting with http:// or https://.',
			'ohmylms'
		);
	}
	const name = settings.sender_name.trim();

	if (
		name.length > LIMITS.sender_name ||
		/[\u0000-\u001F\u007F<>"]/.test( name )
	) {
		errors.sender_name = __(
			'Use up to 100 characters, without line breaks, quotes or angle brackets.',
			'ohmylms'
		);
	}
	const address = settings.sender_email.trim();
	if ( address && ! SIMPLE_EMAIL.test( address ) ) {
		errors.sender_email = __( 'Enter a valid email address.', 'ohmylms' );
	}
	if ( settings.footer_text.length > LIMITS.settings_footer ) {
		errors.footer_text = tooLong( LIMITS.settings_footer );
	}
	return errors;
}

/**
 * Settings that differ from the saved ones, trimmed, ready to send.
 *
 * @param settings Current form state.
 * @param saved    Saved settings.
 * @return Changed fields only.
 */
export function settingsPatch(
	settings: EmailSettings,
	saved: EmailSettings
): Partial< EmailSettings > {
	const patch: Record< string, string > = {};
	for ( const key of Object.keys( saved ) as SettingsField[] ) {
		const value =
			'footer_text' === key ? settings[ key ] : settings[ key ].trim();
		if ( value !== saved[ key ] ) {
			patch[ key ] = value;
		}
	}
	return patch as Partial< EmailSettings >;
}

/**
 * The form field a server validation error (422) names, if any.
 *
 * @param error         Error thrown by the API client.
 * @param error.status
 * @param error.details
 * @param error.message
 * @param allowed       Fields the form has.
 * @return Field and message, or null for errors that are not about one field.
 */
export function serverFieldError< F extends string >(
	error: { status: number; details?: unknown; message: string },
	allowed: readonly F[]
): { field: F; message: string } | null {
	if ( 422 !== error.status || ! error.details ) {
		return null;
	}
	const field = ( error.details as { field?: unknown } ).field;
	if ( 'string' === typeof field && allowed.includes( field as F ) ) {
		return { field: field as F, message: error.message };
	}
	return null;
}
