import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type {
	AccessType,
	Course,
	CourseDetail,
	CourseInput,
	CourseOptions,
	CourseStatus,
	Duration,
	FormErrors,
	FormState,
	Level,
	MediaImage,
	Option,
	PriceType,
	WritableStatus,
} from './types';

export const STATUSES: readonly CourseStatus[] = [
	'draft',
	'pending',
	'publish',
	'future',
	'private',
	'trash',
];
export const LEVELS: readonly Level[] = [
	'beginner',
	'experience',
	'expert',
	'all',
];
const PRICE_TYPES: readonly PriceType[] = [ 'free', 'paid' ];
const ACCESS_TYPES: readonly AccessType[] = [ 'public', 'password_protected' ];
const PRICE_FORMAT = /^\d{1,8}(\.\d+)?$/;
const DATE_FORMAT = /^\d{4}-\d{2}-\d{2}$/;
const DATETIME_FORMAT = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/;

/** Largest price the server accepts. */
export const MAX_PRICE = 99999999.99;

/** Which form field each server rejection concerns (kept equal to `contract.json`). */
export const FIELD_ERRORS: Readonly< Record< string, string > > = {
	ohmylms_course_invalid_title: 'title',
	ohmylms_course_invalid_slug: 'slug',
	ohmylms_course_paid_needs_price: 'regular_price',
	ohmylms_course_sale_price_too_high: 'sale_price',
	ohmylms_course_sale_dates_without_price: 'sale_price',
	ohmylms_course_sale_on_free: 'sale_price',
	ohmylms_course_invalid_sale_dates: 'sale_end_date',
	ohmylms_course_sale_dates_incomplete: 'sale_end_date',
	ohmylms_course_invalid_date: 'available_date',
	ohmylms_course_invalid_schedule: 'scheduled_at',
	ohmylms_course_password_required: 'password',
	ohmylms_course_invalid_password: 'password',
	ohmylms_course_private_password: 'access_type',
	ohmylms_course_invalid_capacity: 'capacity',
	ohmylms_course_invalid_thumbnail: 'thumbnail_id',
	ohmylms_course_thumbnail_forbidden: 'thumbnail_id',
};

/**
 * Build the error thrown when the server answers with data that does not match the contract.
 *
 * @return A 502 `invalid_response` error.
 */
export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid course data.', 'ohmylms' ),
	} );
}

/**
 * Narrow an unknown value to a plain object or fail with an invalid response.
 *
 * @param value Parsed JSON.
 * @return The object.
 */
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

function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}

function boolean( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( item ) => item === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

function list( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

/**
 * Validate a positive integer identifier.
 *
 * @param value Candidate id.
 * @return The id.
 */
export function positiveId( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 1
	) {
		throw invalidResponse();
	}
	return value;
}

function count( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

function nullableId( value: unknown ): number | null {
	return null === value ? null : positiveId( value );
}

function price( value: unknown ): string {
	if ( 'string' !== typeof value || ! /^\d+(\.\d+)?$/.test( value ) ) {
		throw invalidResponse();
	}
	return value;
}

/**
 * Whether a value is a real `YYYY-MM-DD` calendar date.
 *
 * @param value Candidate date.
 * @return True for a date that exists (so not 2026-02-30).
 */
export function isCalendarDate( value: string ): boolean {
	if ( ! DATE_FORMAT.test( value ) ) {
		return false;
	}
	const date = new Date( `${ value }T00:00:00Z` );
	return (
		! Number.isNaN( date.getTime() ) &&
		date.toISOString().slice( 0, 10 ) === value
	);
}

function nullableDate( value: unknown ): string | null {
	if ( null === value ) {
		return null;
	}
	if ( 'string' !== typeof value || ! isCalendarDate( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function parseDuration( value: unknown ): Duration | null {
	if ( null === value ) {
		return null;
	}
	const item = record( value );
	return { hours: count( item.hours ), minutes: count( item.minutes ) };
}

/**
 * Validate a listed course.
 *
 * @param value Parsed JSON.
 * @return The course.
 */
export function parseCourse( value: unknown ): Course {
	const item = record( value );
	const capacity = item.capacity;
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		slug: text( item.slug ),
		status: oneOf( item.status, STATUSES ),
		permalink: text( item.permalink ),
		author_id: count( item.author_id ),
		author_name: text( item.author_name ),
		level: oneOf( item.level, LEVELS ),
		price_type: oneOf( item.price_type, PRICE_TYPES ),
		regular_price: price( item.regular_price ),
		sale_price: null === item.sale_price ? null : price( item.sale_price ),
		sale_start_date: nullableDate( item.sale_start_date ),
		sale_end_date: nullableDate( item.sale_end_date ),
		price: price( item.price ),
		currency: text( item.currency ),
		access_type: oneOf( item.access_type, ACCESS_TYPES ),
		has_password: boolean( item.has_password ),
		available_date: nullableDate( item.available_date ),
		duration: parseDuration( item.duration ),
		has_capacity: boolean( item.has_capacity ),
		capacity: null === capacity ? null : positiveId( capacity ),
		enable_reviews: boolean( item.enable_reviews ),
		thumbnail_id: nullableId( item.thumbnail_id ),
		thumbnail_url: nullableText( item.thumbnail_url ),
		enrolled_count: count( item.enrolled_count ),
		scheduled_at: nullableText( item.scheduled_at ),
		created_at: text( item.created_at ),
		updated_at: text( item.updated_at ),
	};
}

/**
 * Validate a course read with `context=edit`.
 *
 * @param value Parsed JSON.
 * @return The course with its text fields.
 */
export function parseCourseDetail( value: unknown ): CourseDetail {
	const item = record( value );
	return {
		...parseCourse( value ),
		description: text( item.description ),
		excerpt: text( item.excerpt ),
		learning_outcomes: text( item.learning_outcomes ),
		target_audience: text( item.target_audience ),
		requirements: text( item.requirements ),
		password: text( item.password ),
	};
}

function parseOptionList( value: unknown ): Option[] {
	const items = list( value );
	if ( ! items.length ) {
		throw invalidResponse();
	}
	return items.map( ( raw ) => {
		const option = record( raw );
		return { value: text( option.value ), label: text( option.label ) };
	} );
}

/**
 * Validate the editor options.
 *
 * @param value Parsed JSON.
 * @return The options.
 */
export function parseOptions( value: unknown ): CourseOptions {
	const item = record( value );
	const currency = record( item.currency );
	const limits = record( item.limits );
	const abilities = record( item.abilities );
	if ( 'number' !== typeof limits.max_price ) {
		throw invalidResponse();
	}
	return {
		statuses: parseOptionList( item.statuses ),
		levels: parseOptionList( item.levels ),
		price_types: parseOptionList( item.price_types ),
		access_types: parseOptionList( item.access_types ),
		currency: {
			code: text( currency.code ),
			symbol: text( currency.symbol ),
			position: text( currency.position ),
			decimals: count( currency.decimals ),
		},
		limits: {
			max_title: positiveId( limits.max_title ),
			max_text: positiveId( limits.max_text ),
			max_price: limits.max_price,
			max_capacity: positiveId( limits.max_capacity ),
			max_password: positiveId( limits.max_password ),
		},
		abilities: {
			publish: boolean( abilities.publish ),
			edit_others: boolean( abilities.edit_others ),
			upload_files: boolean( abilities.upload_files ),
			builder_url: text( abilities.builder_url ),
		},
	};
}

/**
 * Validate an item of the core media endpoint.
 *
 * @param value Parsed JSON.
 * @return The image.
 */
export function parseMedia( value: unknown ): MediaImage {
	const item = record( value );
	const details = item.media_details
		? record( item.media_details )
		: ( {} as Record< string, unknown > );
	const sizes = details.sizes ? record( details.sizes ) : {};
	const thumb = sizes.thumbnail ? record( sizes.thumbnail ) : null;
	const url = text( item.source_url );
	const title = item.title ? record( item.title ) : {};
	return {
		id: positiveId( item.id ),
		url,
		thumbnail_url: thumb ? text( thumb.source_url ) : url,
		title: 'string' === typeof title.rendered ? title.rendered : '',
	};
}

function writable( status: CourseStatus ): WritableStatus {
	return 'trash' === status ? 'draft' : status;
}

/**
 * Start state of the editor form.
 *
 * @param course Course read with `context=edit`.
 * @return Form state with all values as strings.
 */
export function formFromCourse( course: CourseDetail ): FormState {
	return {
		title: course.title,
		slug: course.slug,
		status: writable( course.status ),
		scheduled_at: course.scheduled_at
			? course.scheduled_at.slice( 0, 16 )
			: '',
		description: course.description,
		excerpt: course.excerpt,
		level: course.level,
		price_type: course.price_type,
		regular_price: course.regular_price,
		sale_price: course.sale_price ?? '',
		sale_start_date: course.sale_start_date ?? '',
		sale_end_date: course.sale_end_date ?? '',
		access_type: course.access_type,
		password: course.password,
		available_date: course.available_date ?? '',
		duration_hours: course.duration ? String( course.duration.hours ) : '',
		duration_minutes: course.duration
			? String( course.duration.minutes )
			: '',
		has_capacity: course.has_capacity,
		capacity: null === course.capacity ? '' : String( course.capacity ),
		enable_reviews: course.enable_reviews,
		learning_outcomes: course.learning_outcomes,
		target_audience: course.target_audience,
		requirements: course.requirements,
		thumbnail: course.thumbnail_id
			? { id: course.thumbnail_id, url: course.thumbnail_url }
			: null,
	};
}

/**
 * Whether the form differs from its saved copy.
 *
 * @param current Current form.
 * @param saved   Saved form.
 * @return True when there are unsaved changes.
 */
export function isDirty( current: FormState, saved: FormState ): boolean {
	return JSON.stringify( current ) !== JSON.stringify( saved );
}

/**
 * Whether there are no field errors.
 *
 * @param errors Result of {@link validateForm}.
 * @return True when valid.
 */
export function isValid( errors: FormErrors ): boolean {
	return 0 === Object.keys( errors ).length;
}

function validPrice(
	value: string,
	decimals: number,
	maxPrice: number
): boolean {
	if ( ! PRICE_FORMAT.test( value ) ) {
		return false;
	}
	const places = value.split( '.' )[ 1 ]?.length ?? 0;
	return places <= decimals && Number( value ) <= maxPrice;
}

/**
 * Parse a `datetime-local` value (`YYYY-MM-DDTHH:MM`) as a real calendar time.
 *
 * @param value Candidate.
 * @return Milliseconds in the user's local time zone, or `null` when invalid.
 */
export function parseLocalDateTime( value: string ): number | null {
	const match = DATETIME_FORMAT.exec( value );
	if (
		! match ||
		! isCalendarDate( match[ 1 ]! ) ||
		Number( match[ 2 ] ) > 23 ||
		Number( match[ 3 ] ) > 59
	) {
		return null;
	}
	const [ y, m, d ] = match[ 1 ]!.split( '-' ).map( Number );
	return new Date(
		y!,
		m! - 1,
		d!,
		Number( match[ 2 ] ),
		Number( match[ 3 ] )
	).getTime();
}

/**
 * Validate the form with the same rules as the server (which stays authoritative).
 *
 * @param form    Form state.
 * @param options Server options (currency decimals and limits).
 * @param now     Current time in milliseconds; injectable for tests.
 * @return Errors keyed by field; empty when valid.
 */
export function validateForm(
	form: FormState,
	options: CourseOptions,
	now: number = Date.now()
): FormErrors {
	const errors: FormErrors = {};
	const { limits } = options;
	const { decimals } = options.currency;
	const title = form.title.trim();
	if ( ! title || Array.from( title ).length > limits.max_title ) {
		errors.title = sprintf(
			/* translators: %d: maximum title length. */
			__( 'Enter a title of 1 to %d characters.', 'ohmylms' ),
			limits.max_title
		);
	}
	if ( form.slug.trim() && ! /[a-z0-9]/i.test( form.slug ) ) {
		errors.slug = __( 'Use letters or numbers in the slug.', 'ohmylms' );
	}
	for ( const field of [
		'excerpt',
		'learning_outcomes',
		'target_audience',
		'requirements',
	] as const ) {
		if ( Array.from( form[ field ] ).length > limits.max_text ) {
			errors[ field ] = sprintf(
				/* translators: %d: maximum number of characters. */
				__( 'Use at most %d characters.', 'ohmylms' ),
				limits.max_text
			);
		}
	}

	if ( 'paid' === form.price_type ) {
		const priceMessage = sprintf(
			/* translators: %d: number of decimal places. */
			__( 'Enter an amount with at most %d decimal places.', 'ohmylms' ),
			decimals
		);
		const regular = form.regular_price.trim();
		const sale = form.sale_price.trim();
		const regularOk = validPrice( regular, decimals, limits.max_price );
		if ( ! regularOk ) {
			errors.regular_price = priceMessage;
		} else if ( Number( regular ) <= 0 ) {
			errors.regular_price = __(
				'A paid course needs a price above zero.',
				'ohmylms'
			);
		}
		if ( sale && ! validPrice( sale, decimals, limits.max_price ) ) {
			errors.sale_price = priceMessage;
		} else if ( sale && regularOk && Number( sale ) > Number( regular ) ) {
			errors.sale_price = __(
				'The sale price cannot be higher than the regular price.',
				'ohmylms'
			);
		}
		const from = form.sale_start_date.trim();
		const to = form.sale_end_date.trim();
		if ( from && ! isCalendarDate( from ) ) {
			errors.sale_start_date = __( 'Enter a valid date.', 'ohmylms' );
		}
		if ( to && ! isCalendarDate( to ) ) {
			errors.sale_end_date = __( 'Enter a valid date.', 'ohmylms' );
		}
		if ( ! errors.sale_start_date && ! errors.sale_end_date ) {
			if ( from && to && from > to ) {
				errors.sale_end_date = __(
					'The end date must be on or after the start date.',
					'ohmylms'
				);
			} else if ( ! sale && ( from || to ) ) {
				errors.sale_price = __(
					'Enter a sale price for the sale dates, or clear the dates.',
					'ohmylms'
				);
			} else if ( sale && Boolean( from ) !== Boolean( to ) ) {
				errors.sale_end_date = __(
					'A sale needs both a start and an end date, or neither.',
					'ohmylms'
				);
			}
		}
	}

	if (
		form.available_date.trim() &&
		! isCalendarDate( form.available_date.trim() )
	) {
		errors.available_date = __( 'Enter a valid date.', 'ohmylms' );
	}
	const hours = form.duration_hours.trim();
	const minutes = form.duration_minutes.trim();
	if ( hours && ( ! /^\d+$/.test( hours ) || Number( hours ) > 9999 ) ) {
		errors.duration_hours = __(
			'Enter whole hours from 0 to 9999.',
			'ohmylms'
		);
	}
	if ( minutes && ( ! /^\d+$/.test( minutes ) || Number( minutes ) > 59 ) ) {
		errors.duration_minutes = __(
			'Enter whole minutes from 0 to 59.',
			'ohmylms'
		);
	}

	if ( form.has_capacity ) {
		const capacity = form.capacity.trim();
		if (
			! /^\d+$/.test( capacity ) ||
			Number( capacity ) < 1 ||
			Number( capacity ) > limits.max_capacity
		) {
			errors.capacity = sprintf(
				/* translators: %d: largest capacity. */
				__( 'Enter a whole number from 1 to %d.', 'ohmylms' ),
				limits.max_capacity
			);
		}
	}

	if ( 'password_protected' === form.access_type ) {
		if ( ! form.password.trim() ) {
			errors.password = __(
				'Enter a password for this course.',
				'ohmylms'
			);
		} else if ( Array.from( form.password ).length > limits.max_password ) {
			errors.password = sprintf(
				/* translators: %d: maximum password length. */
				__( 'Use at most %d characters.', 'ohmylms' ),
				limits.max_password
			);
		}
		if ( 'private' === form.status ) {
			errors.access_type = __(
				'A private course cannot also be protected by a password.',
				'ohmylms'
			);
		}
	}

	if ( 'future' === form.status ) {
		const when = parseLocalDateTime( form.scheduled_at );
		if ( null === when || when <= now ) {
			errors.scheduled_at = __(
				'Choose a date and time in the future.',
				'ohmylms'
			);
		}
	}
	return errors;
}

function normalizePrice( value: string, decimals: number ): string {
	return Number( value ).toFixed( decimals );
}

/**
 * Convert form state into the REST payload. Call after {@link validateForm} reports no errors.
 *
 * @param form     Form state.
 * @param decimals Currency decimals from the options.
 * @return Trimmed, typed payload; the form is not modified.
 */
export function toInput( form: FormState, decimals: number ): CourseInput {
	const paid = 'paid' === form.price_type;
	const sale = paid ? form.sale_price.trim() : '';
	const hours = Number( form.duration_hours.trim() || 0 );
	const minutes = Number( form.duration_minutes.trim() || 0 );
	const input: CourseInput = {
		title: form.title.trim(),
		slug: form.slug.trim(),
		status: form.status,
		description: form.description,
		excerpt: form.excerpt.trim(),
		level: form.level,
		price_type: form.price_type,
		regular_price: paid
			? normalizePrice( form.regular_price.trim(), decimals )
			: normalizePrice( '0', decimals ),
		sale_price: sale ? normalizePrice( sale, decimals ) : null,
		sale_start_date: sale ? form.sale_start_date.trim() || null : null,
		sale_end_date: sale ? form.sale_end_date.trim() || null : null,
		access_type: form.access_type,
		available_date: form.available_date.trim() || null,
		duration: hours || minutes ? { hours, minutes } : null,
		has_capacity: form.has_capacity,
		capacity: form.has_capacity ? Number( form.capacity.trim() ) : null,
		enable_reviews: form.enable_reviews,
		learning_outcomes: form.learning_outcomes.trim(),
		target_audience: form.target_audience.trim(),
		requirements: form.requirements.trim(),
		thumbnail_id: form.thumbnail ? form.thumbnail.id : null,
	};
	if ( 'future' === form.status ) {
		input.scheduled_at = form.scheduled_at.trim();
	}
	if ( 'password_protected' === form.access_type ) {
		input.password = form.password;
	}
	return input;
}

/**
 * Map a server rejection to the form field it concerns.
 *
 * @param error Error from the API.
 * @return The field and message, or `null` when it is not about one field.
 */
export function serverFieldError(
	error: ApiError
): { path: string; message: string } | null {
	if (
		'ohmylms_course_invalid_price' === error.code ||
		'ohmylms_course_invalid_text' === error.code
	) {
		const details = error.details;
		const field =
			details && 'object' === typeof details
				? ( details as Record< string, unknown > ).field
				: undefined;
		return {
			path: 'string' === typeof field ? field : 'regular_price',
			message: error.message,
		};
	}
	const path = FIELD_ERRORS[ error.code ];
	return path ? { path, message: error.message } : null;
}

/**
 * Format an amount for display with the course currency code.
 *
 * @param amount   Fixed-decimal amount.
 * @param currency Currency code.
 * @param locale   BCP 47 locale; the browser default when omitted.
 * @return Localised amount, or "AMOUNT CODE" when the code is unknown to the browser.
 */
export function formatPrice(
	amount: string,
	currency: string,
	locale?: string
): string {
	try {
		return new Intl.NumberFormat( locale, {
			style: 'currency',
			currency,
		} ).format( Number( amount ) );
	} catch {
		return `${ amount } ${ currency }`;
	}
}

/**
 * Translated name of a course status.
 *
 * @param status Status.
 * @return Label.
 */
export function statusLabel( status: CourseStatus ): string {
	switch ( status ) {
		case 'publish':
			return __( 'Published', 'ohmylms' );
		case 'pending':
			return __( 'Pending review', 'ohmylms' );
		case 'future':
			return __( 'Scheduled', 'ohmylms' );
		case 'private':
			return __( 'Private', 'ohmylms' );
		case 'trash':
			return __( 'Trashed', 'ohmylms' );
		default:
			return __( 'Draft', 'ohmylms' );
	}
}

/**
 * Translated name of a course level.
 *
 * @param level Level.
 * @return Label.
 */
export function levelLabel( level: Level ): string {
	switch ( level ) {
		case 'experience':
			return __( 'Intermediate', 'ohmylms' );
		case 'expert':
			return __( 'Expert', 'ohmylms' );
		case 'all':
			return __( 'All levels', 'ohmylms' );
		default:
			return __( 'Beginner', 'ohmylms' );
	}
}

/**
 * Describe a duration for lists.
 *
 * @param duration Duration or null.
 * @return Text such as "3 h 30 min", or an empty string.
 */
export function durationLabel( duration: Duration | null ): string {
	if ( ! duration ) {
		return '';
	}
	const parts: string[] = [];
	if ( duration.hours ) {
		parts.push(
			sprintf(
				/* translators: %d: number of hours. */
				__( '%d h', 'ohmylms' ),
				duration.hours
			)
		);
	}
	if ( duration.minutes ) {
		parts.push(
			sprintf(
				/* translators: %d: number of minutes. */
				__( '%d min', 'ohmylms' ),
				duration.minutes
			)
		);
	}
	return parts.join( ' ' );
}
