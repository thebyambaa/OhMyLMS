import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type {
	BillingPeriod,
	CourseRef,
	FormErrors,
	FormState,
	Member,
	Membership,
	MembershipDetail,
	MembershipInput,
	MembershipOptions,
	MembershipStatus,
	Option,
} from './types';

const STATUSES: readonly MembershipStatus[] = [ 'draft', 'publish' ];
const BILLING_PERIODS: readonly BillingPeriod[] = [
	'one_time',
	'year',
	'month',
	'week',
	'day',
];
const PRICE_FORMAT = /^\d{1,8}(\.\d{1,4})?$/;
const DATE_FORMAT = /^\d{4}-\d{2}-\d{2}$/;

/** Largest price the server accepts. */
export const MAX_PRICE = 99999999.99;

/**
 * Build the error thrown when the server answers with data that does not match the contract.
 *
 * @return A 502 `invalid_response` error.
 */
export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __(
			'The server returned invalid membership data.',
			'ohmylms'
		),
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

function price( value: unknown ): string {
	if ( 'string' !== typeof value || ! /^\d+(\.\d+)?$/.test( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function nullablePrice( value: unknown ): string | null {
	return null === value ? null : price( value );
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

/**
 * Validate a course reference (also used for the course search).
 *
 * @param value Parsed JSON.
 * @return The course reference.
 */
export function parseCourseRef( value: unknown ): CourseRef {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		status: text( item.status ),
	};
}

/**
 * Validate a listed plan.
 *
 * @param value Parsed JSON.
 * @return The plan.
 */
export function parseMembership( value: unknown ): Membership {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		name: text( item.name ),
		slug: text( item.slug ),
		status: oneOf( item.status, STATUSES ),
		permalink: text( item.permalink ),
		regular_price: price( item.regular_price ),
		sale_price: nullablePrice( item.sale_price ),
		sale_start_date: nullableDate( item.sale_start_date ),
		sale_end_date: nullableDate( item.sale_end_date ),
		price: price( item.price ),
		sign_up_fee: nullablePrice( item.sign_up_fee ),
		billing_period: oneOf( item.billing_period, BILLING_PERIODS ),
		billing_interval: positiveId( item.billing_interval ),
		currency: text( item.currency ),
		all_learning_content: boolean( item.all_learning_content ),
		course_count: count( item.course_count ),
		member_count: count( item.member_count ),
		created_at: text( item.created_at ),
		updated_at: text( item.updated_at ),
	};
}

/**
 * Validate a plan read with `context=edit`.
 *
 * @param value Parsed JSON.
 * @return The plan with its courses and rules.
 */
export function parseMembershipDetail( value: unknown ): MembershipDetail {
	const item = record( value );
	return {
		...parseMembership( value ),
		description: text( item.description ),
		course_ids: list( item.course_ids ).map( positiveId ),
		courses: list( item.courses ).map( parseCourseRef ),
		excluded_course_ids: list( item.excluded_course_ids ).map( positiveId ),
		excluded_courses: list( item.excluded_courses ).map( parseCourseRef ),
		curriculum_item_ids: list( item.curriculum_item_ids ).map( positiveId ),
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
export function parseOptions( value: unknown ): MembershipOptions {
	const item = record( value );
	const currency = record( item.currency );
	const limits = record( item.limits );
	if ( 'number' !== typeof limits.max_price ) {
		throw invalidResponse();
	}
	return {
		statuses: parseOptionList( item.statuses ),
		billing_periods: parseOptionList( item.billing_periods ),
		currency: {
			code: text( currency.code ),
			symbol: text( currency.symbol ),
			position: text( currency.position ),
			decimals: count( currency.decimals ),
		},
		limits: {
			max_courses: positiveId( limits.max_courses ),
			max_curriculum_items: positiveId( limits.max_curriculum_items ),
			max_price: limits.max_price,
			max_name: positiveId( limits.max_name ),
		},
	};
}

function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}

/**
 * Validate a plan member.
 *
 * @param value Parsed JSON.
 * @return The member.
 */
export function parseMember( value: unknown ): Member {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		user_id: positiveId( item.user_id ),
		display_name: text( item.display_name ),
		email: text( item.email ),
		status: text( item.status ),
		order_id: count( item.order_id ),
		start_date: nullableText( item.start_date ),
		end_date: nullableText( item.end_date ),
	};
}

/**
 * Start state of the editor form.
 *
 * @param plan Plan read with `context=edit`.
 * @return Form state with all values as strings.
 */
export function formFromMembership( plan: MembershipDetail ): FormState {
	return {
		name: plan.name,
		status: plan.status,
		description: plan.description,
		regular_price: plan.regular_price,
		sale_price: plan.sale_price ?? '',
		sale_start_date: plan.sale_start_date ?? '',
		sale_end_date: plan.sale_end_date ?? '',
		sign_up_fee: plan.sign_up_fee ?? '',
		billing_period: plan.billing_period,
		billing_interval: String( plan.billing_interval ),
		all_learning_content: plan.all_learning_content,
		courses: plan.courses,
		excluded_courses: plan.excluded_courses,
		curriculum_item_ids: plan.curriculum_item_ids,
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
 * Validate the form with the same rules as the server (which stays authoritative).
 *
 * @param form    Form state.
 * @param options Server options (currency decimals and limits).
 * @return Errors keyed by field; empty when valid.
 */
export function validateForm(
	form: FormState,
	options: MembershipOptions
): FormErrors {
	const errors: FormErrors = {};
	const { decimals } = options.currency;
	const name = form.name.trim();
	if ( ! name || Array.from( name ).length > options.limits.max_name ) {
		errors.name = sprintf(
			/* translators: %d: maximum name length. */
			__( 'Enter a name of 1 to %d characters.', 'ohmylms' ),
			options.limits.max_name
		);
	}
	const priceMessage = sprintf(
		/* translators: %d: number of decimal places. */
		__(
			'Enter an amount of zero or more with at most %d decimal places.',
			'ohmylms'
		),
		decimals
	);
	const regular = form.regular_price.trim();
	const sale = form.sale_price.trim();
	const fee = form.sign_up_fee.trim();
	const regularOk = validPrice( regular, decimals, options.limits.max_price );
	if ( ! regularOk ) {
		errors.regular_price = priceMessage;
	}
	if ( sale && ! validPrice( sale, decimals, options.limits.max_price ) ) {
		errors.sale_price = priceMessage;
	} else if ( sale && regularOk && Number( sale ) > Number( regular ) ) {
		errors.sale_price = __(
			'The sale price cannot be higher than the regular price.',
			'ohmylms'
		);
	}
	if ( fee && ! validPrice( fee, decimals, options.limits.max_price ) ) {
		errors.sign_up_fee = priceMessage;
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
		} else if ( sale && ! errors.sale_price ) {
			if ( 0 === Number( sale ) && ! to ) {
				errors.sale_end_date = __(
					'A free promotion (sale price 0) needs an end date.',
					'ohmylms'
				);
			} else if (
				Number( sale ) > 0 &&
				Boolean( from ) !== Boolean( to )
			) {
				errors.sale_end_date = __(
					'A discounted sale needs both a start and an end date, or neither.',
					'ohmylms'
				);
			}
		}
	}

	const interval = Number( form.billing_interval );
	if (
		! /^\d+$/.test( form.billing_interval.trim() ) ||
		interval < 1 ||
		interval > 365
	) {
		errors.billing_interval = __(
			'Enter a whole number from 1 to 365.',
			'ohmylms'
		);
	}

	if ( form.courses.length > options.limits.max_courses ) {
		errors.courses = sprintf(
			/* translators: %d: maximum number of courses. */
			__( 'Select at most %d courses.', 'ohmylms' ),
			options.limits.max_courses
		);
	}
	if ( form.excluded_courses.length > options.limits.max_courses ) {
		errors.excluded_courses = sprintf(
			/* translators: %d: maximum number of courses. */
			__( 'Exclude at most %d courses.', 'ohmylms' ),
			options.limits.max_courses
		);
	}
	const excluded = new Set( form.excluded_courses.map( ( c ) => c.id ) );
	if ( form.courses.some( ( course ) => excluded.has( course.id ) ) ) {
		errors.excluded_courses = __(
			'A course cannot be both included and excluded.',
			'ohmylms'
		);
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
export function toInput( form: FormState, decimals: number ): MembershipInput {
	const optionalPrice = ( value: string ) =>
		value.trim() ? normalizePrice( value.trim(), decimals ) : null;
	return {
		name: form.name.trim(),
		status: form.status,
		description: form.description,
		regular_price: normalizePrice( form.regular_price.trim(), decimals ),
		sale_price: optionalPrice( form.sale_price ),
		sale_start_date: form.sale_start_date.trim() || null,
		sale_end_date: form.sale_end_date.trim() || null,
		sign_up_fee: optionalPrice( form.sign_up_fee ),
		billing_period: form.billing_period,
		billing_interval: Number( form.billing_interval ),
		all_learning_content: form.all_learning_content,
		course_ids: form.courses.map( ( course ) => course.id ),
		excluded_course_ids: form.excluded_courses.map(
			( course ) => course.id
		),
		curriculum_item_ids: form.curriculum_item_ids,
	};
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
	const fields: Record< string, string > = {
		ohmylms_membership_invalid_name: 'name',
		ohmylms_membership_sale_price_too_high: 'sale_price',
		ohmylms_membership_sale_dates_without_price: 'sale_price',
		ohmylms_membership_invalid_sale_dates: 'sale_end_date',
		ohmylms_membership_sale_dates_incomplete: 'sale_end_date',
		ohmylms_membership_free_needs_end: 'sale_end_date',
		ohmylms_membership_invalid_courses: 'courses',
		ohmylms_membership_course_conflict: 'excluded_courses',
	};
	if ( 'ohmylms_membership_invalid_price' === error.code ) {
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
	const path = fields[ error.code ];
	return path ? { path, message: error.message } : null;
}

/**
 * Format an amount for display with the plan's currency code.
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
 * Describe how a plan is billed, for lists.
 *
 * @param plan Listed plan.
 * @return Translated text such as "Every 3 months" or "One time".
 */
export function billingLabel( plan: Membership ): string {
	const every = plan.billing_interval;
	switch ( plan.billing_period ) {
		case 'one_time':
			return __( 'One time', 'ohmylms' );
		case 'year':
			return 1 === every
				? __( 'Every year', 'ohmylms' )
				: sprintf(
						/* translators: %d: number of years. */
						__( 'Every %d years', 'ohmylms' ),
						every
					);
		case 'month':
			return 1 === every
				? __( 'Every month', 'ohmylms' )
				: sprintf(
						/* translators: %d: number of months. */
						__( 'Every %d months', 'ohmylms' ),
						every
					);
		case 'week':
			return 1 === every
				? __( 'Every week', 'ohmylms' )
				: sprintf(
						/* translators: %d: number of weeks. */
						__( 'Every %d weeks', 'ohmylms' ),
						every
					);
		default:
			return 1 === every
				? __( 'Every day', 'ohmylms' )
				: sprintf(
						/* translators: %d: number of days. */
						__( 'Every %d days', 'ohmylms' ),
						every
					);
	}
}
