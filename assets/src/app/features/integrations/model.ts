import { __ } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type {
	FieldErrors,
	Integration,
	IntegrationFilters,
	IntegrationSettings,
	LoadError,
	Option,
	ReadonlyValue,
	SettingsChanges,
	SettingsDraft,
	SettingsField,
} from './types';

export const ALL_CATEGORIES = 'all';
export const EMPTY_FILTERS: IntegrationFilters = {
	search: '',
	category: ALL_CATEGORIES,
};

const ID_PATTERN = /^[a-z0-9_-]+$/;
/** Same rule as the server: printable ASCII without spaces, stored byte for byte. */
const SECRET_PATTERN = /^[\x21-\x7E]+$/;

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __(
			'The server returned invalid integration data.',
			'ohmylms'
		),
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

function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}

function textList( value: unknown ): string[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value.map( text );
}

/**
 * Validate an integration id before it is placed in a URL.
 *
 * @param  id Candidate id.
 * @return The same id.
 * @throws {ApiError} When the id could not be an integration id.
 */
export function integrationId( id: string ): string {
	if ( ! ID_PATTERN.test( id ) ) {
		throw new ApiError( {
			code: 'invalid_request',
			status: 0,
			message: __( 'Unknown integration.', 'ohmylms' ),
		} );
	}
	return id;
}

export function parseIntegration( value: unknown ): Integration {
	const item = record( value );
	return {
		id: integrationId( text( item.id ) ),
		label: text( item.label ),
		description: text( item.description ),
		icon: text( item.icon ),
		categories: textList( item.categories ),
		dependency: nullableText( item.dependency ),
		required_plan: nullableText( item.required_plan ),
		enabled: flag( item.enabled ),
		available: flag( item.available ),
		unavailable_reason: nullableText( item.unavailable_reason ),
		configurable: flag( item.configurable ),
		requires_reload: flag( item.requires_reload ),
	};
}

function parseField( value: unknown ): SettingsField {
	const item = record( value );
	const base = {
		name: text( item.name ),
		label: text( item.label ),
		help: text( item.help ),
		required: flag( item.required ),
		max_length: item.max_length,
	};
	if (
		'number' !== typeof base.max_length ||
		! Number.isSafeInteger( base.max_length ) ||
		base.max_length < 1
	) {
		throw invalidResponse();
	}
	const field = { ...base, max_length: base.max_length };
	if ( 'text' === item.kind ) {
		return { ...field, kind: 'text', value: text( item.value ) };
	}
	if ( 'secret' === item.kind ) {
		return {
			...field,
			kind: 'secret',
			configured: flag( item.configured ),
			hint: text( item.hint ),
		};
	}
	throw invalidResponse();
}

function parseReadonly( value: unknown ): ReadonlyValue {
	const item = record( value );
	return {
		name: text( item.name ),
		label: text( item.label ),
		help: text( item.help ),
		value: text( item.value ),
	};
}

export function parseSettings( value: unknown ): IntegrationSettings {
	const item = record( value );
	if ( ! Array.isArray( item.fields ) || ! Array.isArray( item.readonly ) ) {
		throw invalidResponse();
	}
	const fields = item.fields.map( parseField );
	if (
		new Set( fields.map( ( field ) => field.name ) ).size !== fields.length
	) {
		throw invalidResponse();
	}
	return {
		integration: text( item.integration ),
		configured: flag( item.configured ),
		fields,
		readonly: item.readonly.map( parseReadonly ),
	};
}

/**
 * Replace one integration in a list, keeping its position.
 *
 * @param items Current list.
 * @param next  Updated integration.
 * @return A new list.
 */
export function replaceIntegration(
	items: readonly Integration[],
	next: Integration
): Integration[] {
	return items.map( ( item ) => ( item.id === next.id ? next : item ) );
}

/**
 * Human label for a category slug. Unknown slugs (added by extensions) are title-cased.
 *
 * @param slug Category slug from the server.
 * @return Translated label.
 */
export function categoryLabel( slug: string ): string {
	const known: Record< string, string > = {
		connections: __( 'Connections', 'ohmylms' ),
		'course-enhancements': __( 'Course enhancements', 'ohmylms' ),
		'course-engagement': __( 'Course engagement', 'ohmylms' ),
		'live-classes': __( 'Live classes', 'ohmylms' ),
		sales: __( 'Sales', 'ohmylms' ),
		automation: __( 'Automation', 'ohmylms' ),
		crm: __( 'CRM', 'ohmylms' ),
	};
	if ( Object.prototype.hasOwnProperty.call( known, slug ) ) {
		return known[ slug ] ?? slug;
	}
	const words = slug.replace( /[-_]+/g, ' ' ).trim();
	return words.charAt( 0 ).toUpperCase() + words.slice( 1 );
}

/**
 * Category choices for the filter, derived from the loaded integrations.
 *
 * @param items Loaded integrations.
 * @return "All" first, then the categories in use, sorted by label.
 */
export function categoryOptions( items: readonly Integration[] ): Option[] {
	const slugs = new Set< string >();
	for ( const item of items ) {
		item.categories.forEach( ( slug ) => slugs.add( slug ) );
	}
	const options = [ ...slugs ]
		.map( ( slug ) => ( { value: slug, label: categoryLabel( slug ) } ) )
		.sort( ( a, b ) => a.label.localeCompare( b.label ) );
	return [
		{ value: ALL_CATEGORIES, label: __( 'All', 'ohmylms' ) },
		...options,
	];
}

/**
 * Filter integrations by name/description text and category.
 *
 * @param items   Loaded integrations.
 * @param filters Search text and category slug.
 * @return Matching integrations, in the original order.
 */
export function filterIntegrations(
	items: readonly Integration[],
	filters: IntegrationFilters
): Integration[] {
	const search = filters.search.trim().toLowerCase();
	return items.filter( ( item ) => {
		if (
			ALL_CATEGORIES !== filters.category &&
			! item.categories.includes( filters.category )
		) {
			return false;
		}
		return (
			'' === search ||
			`${ item.label } ${ item.description }`
				.toLowerCase()
				.includes( search )
		);
	} );
}

/**
 * Initial form state: stored text values, empty secret inputs, nothing marked for removal.
 *
 * @param settings Settings from the server.
 * @return Draft.
 */
export function createDraft( settings: IntegrationSettings ): SettingsDraft {
	const values: Record< string, string > = {};
	const cleared: Record< string, boolean > = {};
	for ( const field of settings.fields ) {
		values[ field.name ] = 'text' === field.kind ? field.value : '';
		cleared[ field.name ] = false;
	}
	return { values, cleared };
}

/**
 * Validate a draft the way the server will, so mistakes are reported before a request is made.
 *
 * @param settings Settings from the server.
 * @param draft    Current form state.
 * @return Message per invalid field name; empty when valid.
 */
export function validateDraft(
	settings: IntegrationSettings,
	draft: SettingsDraft
): FieldErrors {
	const errors: FieldErrors = {};
	for ( const field of settings.fields ) {
		const entered = draft.values[ field.name ] ?? '';
		const tooLong = __( 'Use fewer characters.', 'ohmylms' );
		if ( 'text' === field.kind ) {
			const value = entered.trim();
			if ( field.required && ! value ) {
				errors[ field.name ] = __(
					'This field is required.',
					'ohmylms'
				);
			} else if ( value.length > field.max_length ) {
				errors[ field.name ] = tooLong;
			}
			continue;
		}
		if ( draft.cleared[ field.name ] ) {
			if ( field.required ) {
				errors[ field.name ] = __(
					'A required secret cannot be removed.',
					'ohmylms'
				);
			}
		} else if ( '' === entered ) {
			if ( field.required && ! field.configured ) {
				errors[ field.name ] = __(
					'This field is required.',
					'ohmylms'
				);
			}
		} else if ( entered.length > field.max_length ) {
			errors[ field.name ] = tooLong;
		} else if ( ! SECRET_PATTERN.test( entered ) ) {
			errors[ field.name ] = __(
				'Use printable characters without spaces.',
				'ohmylms'
			);
		}
	}
	return errors;
}

/**
 * Build the request body: only fields that changed. Untouched secrets are omitted, so the stored
 * secret is kept; a removed secret is sent as null.
 *
 * @param settings Settings from the server.
 * @param draft    Current form state.
 * @return Changes to send; empty when nothing changed.
 */
export function buildChanges(
	settings: IntegrationSettings,
	draft: SettingsDraft
): SettingsChanges {
	const changes: SettingsChanges = {};
	for ( const field of settings.fields ) {
		const entered = draft.values[ field.name ] ?? '';
		if ( 'text' === field.kind ) {
			if ( entered.trim() !== field.value ) {
				changes[ field.name ] = entered.trim();
			}
		} else if ( draft.cleared[ field.name ] ) {
			if ( field.configured ) {
				changes[ field.name ] = null;
			}
		} else if ( '' !== entered ) {
			changes[ field.name ] = entered;
		}
	}
	return changes;
}

/**
 * Field messages from a 422 response (`data.params`), for display next to the inputs.
 *
 * @param error Thrown value.
 * @return Message per field; empty when the error carries none.
 */
export function fieldErrorsFrom( error: ApiError ): FieldErrors {
	const details = error.details;
	if (
		! details ||
		'object' !== typeof details ||
		! ( 'params' in details )
	) {
		return {};
	}
	const params = details.params;
	if ( ! params || 'object' !== typeof params || Array.isArray( params ) ) {
		return {};
	}
	const errors: FieldErrors = {};
	for ( const [ name, message ] of Object.entries( params ) ) {
		if ( 'string' === typeof message ) {
			errors[ name ] = message;
		}
	}
	return errors;
}

/**
 * Whether a failure means the user lacks access, so retrying is pointless.
 *
 * @param error Normalised API error.
 * @return True for 401 and 403.
 */
export function isPermissionError( error: ApiError ): boolean {
	return 401 === error.status || 403 === error.status;
}

/**
 * Convert an API error into what a list screen shows.
 *
 * @param error Normalised API error.
 * @return Message and whether retrying can help.
 */
export function toLoadError( error: ApiError ): LoadError {
	return {
		message: error.message,
		permission: isPermissionError( error ),
	};
}
