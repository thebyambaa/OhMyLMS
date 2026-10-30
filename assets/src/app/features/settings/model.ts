import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import {
	FIELD_NAMES,
	type CodeName,
	type CurrencyOption,
	type FailureView,
	type FieldErrors,
	type FieldName,
	type GroupId,
	type GroupResponse,
	type GroupValues,
	type PageOption,
	type RestorePagesResult,
	type SettingValue,
	type TaxRate,
} from './types';

/** How a field is validated; mirrors the JSON schema in `contract.json`. */
export type FieldSpec =
	| { kind: 'boolean' }
	| { kind: 'integer'; min: number; max: number }
	| { kind: 'number'; min: number; max: number }
	| {
			kind: 'string';
			min: number;
			max: number;
			pattern?: RegExp;
			options?: readonly string[];
	  }
	| { kind: 'rates'; max: number };

const MAX_ID = 2147483647;
const COLOR = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;
const SLUG = /^[a-z0-9]+([_-][a-z0-9]+)*(\/[a-z0-9]+([_-][a-z0-9]+)*)*$/;
const page: FieldSpec = { kind: 'integer', min: 0, max: MAX_ID };
const color: FieldSpec = { kind: 'string', min: 4, max: 7, pattern: COLOR };
const toggle: FieldSpec = { kind: 'boolean' };
const slug: FieldSpec = { kind: 'string', min: 1, max: 60, pattern: SLUG };
const label: FieldSpec = { kind: 'string', min: 1, max: 50 };

export const SPECS: { [ G in GroupId ]: Record< FieldName< G >, FieldSpec > } =
	{
		general: {
			course_page_id: page,
			checkout_page_id: page,
			thank_you_page_id: page,
			student_dashboard_page_id: page,
			student_profile_page_id: page,
			student_courses_page_id: page,
			registration_page_id: page,
			terms_page_id: page,
			privacy_policy_page_id: page,
			profile_page_id: page,
		},
		branding: {
			color_preset: {
				kind: 'string',
				min: 0,
				max: 40,
				pattern: /^[a-z0-9]+(-[a-z0-9]+)*$/,
			},
			primary_color: color,
			primary_hover_color: color,
			heading_color: color,
			body_text_color: color,
			progress_color: color,
			video_player_logo: { kind: 'string', min: 0, max: 2048 },
			video_player_logo_bg_color: color,
		},
		'account-and-privacy': {
			allow_purchase_without_login: toggle,
			require_email_verification: toggle,
			checkout_phone_field: {
				kind: 'string',
				min: 0,
				max: 8,
				options: [ 'optional', 'required', 'hidden' ],
			},
			privacy_policy_message: { kind: 'string', min: 0, max: 2000 },
		},
		permalink: {
			course_base: slug,
			lesson_base: slug,
			quiz_base: slug,
			category_base: slug,
			tag_base: slug,
			membership_base: slug,
		},
		advanced: { use_custom_video_player: toggle },
		currency: {
			currency: { kind: 'string', min: 3, max: 3, pattern: /^[A-Z]{3}$/ },
			currency_pos: {
				kind: 'string',
				min: 0,
				max: 11,
				options: [ 'left', 'right', 'left_space', 'right_space' ],
			},
			price_thousand_sep: {
				kind: 'string',
				min: 0,
				max: 1,
				pattern: /^[^0-9<>&"']?$/,
			},
			price_decimal_sep: {
				kind: 'string',
				min: 1,
				max: 1,
				pattern: /^[^0-9<>&"']$/,
			},
			price_num_decimals: { kind: 'integer', min: 0, max: 8 },
		},
		tax: {
			tax_enabled: toggle,
			tax_label: label,
			prices_include_tax: toggle,
			eu_vat_enabled: toggle,
			disable_vat_validation: toggle,
			vat_number_label: label,
			fallback_tax_rate: { kind: 'number', min: 0, max: 100 },
			tax_rates: { kind: 'rates', max: 500 },
		},
	};

/** First URL segments WordPress or the web server already use (same list as the server). */
export const RESERVED_SLUGS: readonly string[] = [
	'wp-admin',
	'wp-json',
	'wp-content',
	'wp-includes',
	'wp-login',
	'feed',
	'embed',
	'trackback',
	'comments',
	'search',
	'author',
	'page',
	'attachment',
	'admin',
];

const PAGE_EXCLUSIVE: readonly string[] = [
	'course_page_id',
	'checkout_page_id',
	'thank_you_page_id',
];

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid settings data.', 'ohmylms' ),
	} );
}

function isRecord( value: unknown ): value is Record< string, unknown > {
	return (
		Boolean( value ) &&
		'object' === typeof value &&
		! Array.isArray( value )
	);
}

function asRecord( value: unknown ): Record< string, unknown > {
	if ( ! isRecord( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function asArray( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function asString( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function asNumber( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isFinite( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function asBoolean( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function parseRate( value: unknown ): TaxRate {
	const row = asRecord( value );
	return {
		country: asString( row.country ),
		state: asString( row.state ),
		rate: asNumber( row.rate ),
		country_wide: asBoolean( row.country_wide ),
	};
}

/**
 * Check only the kind of a stored value; limits are enforced on input, not on stored data.
 *
 * @param spec  Field spec.
 * @param value Untrusted value.
 * @return The value, typed.
 */
function parseValue( spec: FieldSpec, value: unknown ): SettingValue {
	switch ( spec.kind ) {
		case 'boolean':
			return asBoolean( value );
		case 'integer':
		case 'number':
			return asNumber( value );
		case 'rates':
			return asArray( value ).map( parseRate );
		default:
			return asString( value );
	}
}

/**
 * Validate the values of one group returned by the server.
 *
 * @param group Group id.
 * @param raw   Untrusted `values` member.
 * @return Values with every registered field present.
 */
export function parseValues< G extends GroupId >(
	group: G,
	raw: unknown
): GroupValues< G > {
	const source = asRecord( raw );
	const names: readonly FieldName< G >[] = FIELD_NAMES[ group ];
	const specs: Record< FieldName< G >, FieldSpec > = SPECS[ group ];
	const values: Partial< GroupValues< G > > = {};
	for ( const name of names ) {
		values[ name ] = parseValue( specs[ name ], source[ name ] );
	}
	// Every registered name was assigned above, so the partial is now complete.
	return values as GroupValues< G >;
}

/**
 * Validate a settings response for one group.
 *
 * @param group Group the request was for.
 * @param raw   Untrusted response body.
 * @return Validated response.
 */
export function parseGroupResponse< G extends GroupId >(
	group: G,
	raw: unknown
): GroupResponse< G > {
	const body = asRecord( raw );
	if ( body.group !== group ) {
		throw invalidResponse();
	}
	return {
		group,
		values: parseValues( group, body.values ),
		changed: asArray( body.changed ).map( asString ),
	};
}

export function parsePages( raw: unknown ): PageOption[] {
	return asArray( raw ).map( ( item ) => {
		const row = asRecord( item );
		return {
			id: asNumber( row.id ),
			title: asString( row.title ),
			slug: asString( row.slug ),
		};
	} );
}

export function parseCurrencies( raw: unknown ): CurrencyOption[] {
	return asArray( raw ).map( ( item ) => {
		const row = asRecord( item );
		return {
			code: asString( row.code ),
			name: asString( row.name ),
			symbol: asString( row.symbol ),
		};
	} );
}

export function parseCodeNames( raw: unknown ): CodeName[] {
	return asArray( raw ).map( ( item ) => {
		const row = asRecord( item );
		return { code: asString( row.code ), name: asString( row.name ) };
	} );
}

export function parseRestore( raw: unknown ): RestorePagesResult {
	const body = asRecord( raw );
	return {
		restored: asArray( body.restored ).map( ( item ) => {
			const row = asRecord( item );
			return {
				key: asString( row.key ),
				page_id: asNumber( row.page_id ),
				title: asString( row.title ),
			};
		} ),
		values: parseValues( 'general', body.values ),
	};
}

export function parseDeleted( raw: unknown ): number {
	return asNumber( asRecord( raw ).deleted );
}

/**
 * Validate one value against its spec.
 *
 * @param spec  Field spec.
 * @param value Value being edited (a number input may hold NaN).
 * @return A translated message, or `null` when the value is valid.
 */
export function validateValue(
	spec: FieldSpec,
	value: SettingValue
): string | null {
	switch ( spec.kind ) {
		case 'boolean':
			return 'boolean' === typeof value
				? null
				: __( 'Choose on or off.', 'ohmylms' );
		case 'integer':
		case 'number': {
			if (
				'number' !== typeof value ||
				! Number.isFinite( value ) ||
				( 'integer' === spec.kind && ! Number.isInteger( value ) )
			) {
				return 'integer' === spec.kind
					? __( 'Enter a whole number.', 'ohmylms' )
					: __( 'Enter a number.', 'ohmylms' );
			}
			if ( value < spec.min || value > spec.max ) {
				return sprintf(
					/* translators: 1: minimum, 2: maximum. */
					__( 'Enter a value from %1$s to %2$s.', 'ohmylms' ),
					String( spec.min ),
					String( spec.max )
				);
			}
			return null;
		}
		case 'rates':
			return Array.isArray( value ) && value.length <= spec.max
				? null
				: __( 'Too many tax rates.', 'ohmylms' );
		default: {
			if ( 'string' !== typeof value ) {
				return __( 'Enter text.', 'ohmylms' );
			}
			if ( spec.options && ! spec.options.includes( value ) ) {
				return __( 'Choose one of the listed options.', 'ohmylms' );
			}
			if ( value.length < spec.min ) {
				return __( 'This field is required.', 'ohmylms' );
			}
			if ( value.length > spec.max ) {
				return sprintf(
					/* translators: %d: maximum number of characters. */
					__( 'Use at most %d characters.', 'ohmylms' ),
					spec.max
				);
			}
			if ( spec.pattern && ! spec.pattern.test( value ) ) {
				return __(
					'This value is not in the expected format.',
					'ohmylms'
				);
			}
			return null;
		}
	}
}

function isHttpUrl( value: string ): boolean {
	if ( '' === value ) {
		return true;
	}
	try {
		const url = new URL( value );
		return 'http:' === url.protocol || 'https:' === url.protocol;
	} catch {
		return false;
	}
}

function rowMessage( row: number, message: string ): string {
	return sprintf(
		/* translators: 1: row number, 2: problem. */
		__( 'Row %1$d: %2$s', 'ohmylms' ),
		row,
		message
	);
}

function validateRates( rates: TaxRate[] ): string | null {
	const seen = new Set< string >();
	for ( const [ index, rate ] of rates.entries() ) {
		const row = index + 1;
		if ( ! /^[A-Z]{2}$/.test( rate.country ) ) {
			return rowMessage( row, __( 'choose a country.', 'ohmylms' ) );
		}
		if ( rate.country_wide && '' !== rate.state ) {
			return rowMessage(
				row,
				__( 'a country-wide rate has no state.', 'ohmylms' )
			);
		}
		if ( ! rate.country_wide && '' === rate.state ) {
			return rowMessage( row, __( 'choose a state.', 'ohmylms' ) );
		}
		if (
			! Number.isFinite( rate.rate ) ||
			rate.rate < 0 ||
			rate.rate > 100
		) {
			return rowMessage(
				row,
				__( 'enter a rate from 0 to 100.', 'ohmylms' )
			);
		}
		const key = `${ rate.country }|${ rate.state }`;
		if ( seen.has( key ) ) {
			return rowMessage(
				row,
				__( 'this country and state is already listed.', 'ohmylms' )
			);
		}
		seen.add( key );
	}
	return null;
}

function text( values: Record< string, SettingValue >, name: string ): string {
	const value = values[ name ];
	return 'string' === typeof value ? value : '';
}

/**
 * Validate a whole group the way the server does, so most mistakes are caught before a request.
 *
 * @param group  Group id.
 * @param values Values being edited.
 * @return Field name to message; empty when valid.
 */
export function validateGroup(
	group: GroupId,
	values: Record< string, SettingValue >
): FieldErrors {
	const errors: FieldErrors = {};
	const specs: Record< string, FieldSpec > = SPECS[ group ];
	for ( const [ name, spec ] of Object.entries( specs ) ) {
		const message = validateValue( spec, values[ name ] ?? '' );
		if ( message ) {
			errors[ name ] = message;
		}
	}

	if ( 'general' === group ) {
		for ( const name of PAGE_EXCLUSIVE ) {
			const id = values[ name ];
			if (
				! errors[ name ] &&
				'number' === typeof id &&
				id > 0 &&
				PAGE_EXCLUSIVE.some(
					( other ) => other !== name && values[ other ] === id
				)
			) {
				errors[ name ] = __(
					'The course archive, checkout and thank you pages must be different pages.',
					'ohmylms'
				);
			}
		}
	}

	if (
		'branding' === group &&
		! errors.video_player_logo &&
		! isHttpUrl( text( values, 'video_player_logo' ) )
	) {
		errors.video_player_logo = __(
			'Enter a full http or https URL.',
			'ohmylms'
		);
	}

	if ( 'permalink' === group ) {
		const counts = new Map< string, number >();
		for ( const name of FIELD_NAMES.permalink ) {
			const value = text( values, name );
			counts.set( value, ( counts.get( value ) ?? 0 ) + 1 );
		}
		for ( const name of FIELD_NAMES.permalink ) {
			const value = text( values, name );
			if ( errors[ name ] ) {
				continue;
			}
			if ( RESERVED_SLUGS.includes( value.split( '/' )[ 0 ] ?? '' ) ) {
				errors[ name ] = __(
					'This URL base is reserved by WordPress.',
					'ohmylms'
				);
			} else if ( ( counts.get( value ) ?? 0 ) > 1 ) {
				errors[ name ] = __(
					'Each URL base must be different.',
					'ohmylms'
				);
			}
		}
	}

	if (
		'currency' === group &&
		! errors.price_decimal_sep &&
		! errors.price_thousand_sep &&
		'' !== text( values, 'price_thousand_sep' ) &&
		text( values, 'price_thousand_sep' ) ===
			text( values, 'price_decimal_sep' )
	) {
		errors.price_decimal_sep = __(
			'The thousands and decimal separators must be different.',
			'ohmylms'
		);
	}

	if ( 'tax' === group && ! errors.tax_rates ) {
		const rates = values.tax_rates;
		const message = Array.isArray( rates ) ? validateRates( rates ) : null;
		if ( message ) {
			errors.tax_rates = message;
		}
	}

	return errors;
}

function sameValue( a: SettingValue, b: SettingValue ): boolean {
	if ( Array.isArray( a ) && Array.isArray( b ) ) {
		return (
			a.length === b.length &&
			a.every( ( rate, index ) => {
				const other = b[ index ];
				return (
					undefined !== other &&
					rate.country === other.country &&
					rate.state === other.state &&
					rate.rate === other.rate &&
					rate.country_wide === other.country_wide
				);
			} )
		);
	}
	return a === b;
}

/**
 * Names of the fields whose draft differs from the saved value.
 *
 * @param saved Values as last stored.
 * @param draft Values being edited.
 * @return Changed field names in registry order.
 */
export function changedFields(
	saved: Record< string, SettingValue >,
	draft: Record< string, SettingValue >
): string[] {
	return Object.keys( saved ).filter( ( name ) => {
		const before = saved[ name ];
		const after = draft[ name ];
		return (
			undefined !== before &&
			undefined !== after &&
			! sameValue( before, after )
		);
	} );
}

/**
 * Body for a partial update: only changed fields, so other settings are never overwritten.
 *
 * @param saved Values as last stored.
 * @param draft Values being edited.
 * @return Changed fields only.
 */
export function buildPatch(
	saved: Record< string, SettingValue >,
	draft: Record< string, SettingValue >
): Record< string, SettingValue > {
	const patch: Record< string, SettingValue > = {};
	for ( const name of changedFields( saved, draft ) ) {
		const value = draft[ name ];
		if ( undefined !== value ) {
			patch[ name ] = value;
		}
	}
	return patch;
}

/**
 * Normalise a colour for a native colour input (which needs `#rrggbb`).
 *
 * @param value A `#rgb` or `#rrggbb` string.
 * @return Lower-case `#rrggbb`, or black when the value is not a colour.
 */
export function toSwatch( value: string ): string {
	if ( /^#[0-9a-fA-F]{3}$/.test( value ) ) {
		const [ , r, g, b ] = value;
		return `#${ r }${ r }${ g }${ g }${ b }${ b }`.toLowerCase();
	}
	return /^#[0-9a-fA-F]{6}$/.test( value ) ? value.toLowerCase() : '#000000';
}

function messagesFrom( details: unknown ): FieldErrors {
	if ( ! isRecord( details ) || ! isRecord( details.params ) ) {
		return {};
	}
	const errors: FieldErrors = {};
	for ( const [ name, message ] of Object.entries( details.params ) ) {
		if ( 'string' === typeof message ) {
			errors[ name ] = message;
		}
	}
	return errors;
}

/**
 * Reduce a failed request to what the screen shows.
 *
 * @param error Typed API error.
 * @return Message, whether it is a permission problem and per-field messages from the server.
 */
export function describeFailure( error: ApiError ): FailureView {
	return {
		message: error.message,
		permission: 401 === error.status || 403 === error.status,
		fieldErrors: messagesFrom( error.details ),
	};
}

/**
 * Tab id from the `tab` query parameter.
 *
 * @param value Raw parameter.
 * @return A known group id, `general` otherwise.
 */
export function tabFromParam( value: string | null ): GroupId {
	const match = ( Object.keys( FIELD_NAMES ) as GroupId[] ).find(
		( id ) => id === value
	);
	return match ?? 'general';
}
