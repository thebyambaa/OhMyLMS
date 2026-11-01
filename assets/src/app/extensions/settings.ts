import { __, sprintf } from '@wordpress/i18n';

/** A JSON schema as `ohmylms_register_extension_settings()` declares it (the parts the editor reads). */
export interface SettingsSchema {
	type?: string | string[];
	title?: string;
	description?: string;
	enum?: unknown[];
	minimum?: number;
	maximum?: number;
	minLength?: number;
	maxLength?: number;
	format?: string;
	properties?: Record< string, SettingsSchema >;
	required?: string[] | boolean;
	[ key: string ]: unknown;
}

/** Extension settings registered on the server for one content context. */
export interface SettingsDefinition {
	/** Settings ID: the namespace of the values. */
	id: string;
	label: string;
	schema: SettingsSchema;
}

/** One property of a settings schema that the generic editor can edit. */
export interface SettingsField {
	name: string;
	label: string;
	help: string;
	kind:
		| 'text'
		| 'textarea'
		| 'url'
		| 'number'
		| 'integer'
		| 'boolean'
		| 'choice';
	required: boolean;
	/** Choices of an `enum`; `stored` is the enum value itself. */
	choices: { value: string; label: string; stored: string | number }[];
	minimum?: number;
	maximum?: number;
	minLength?: number;
	maxLength?: number;
}

const LONG_TEXT = 200;

function plainObject( value: unknown ): value is Record< string, unknown > {
	return (
		!! value &&
		'object' === typeof value &&
		! Array.isArray( value ) &&
		Object.getPrototypeOf( value ) === Object.prototype
	);
}

function schemaType( schema: SettingsSchema ): string {
	const type = Array.isArray( schema.type )
		? schema.type.find( ( item ) => 'null' !== item )
		: schema.type;
	return 'string' === typeof type ? type : '';
}

function fieldKind(
	type: string,
	property: SettingsSchema
): SettingsField[ 'kind' ] | null {
	switch ( type ) {
		case 'boolean':
		case 'number':
		case 'integer':
			return type;
		case 'string':
			if ( 'uri' === property.format || 'url' === property.format ) {
				return 'url';
			}
			return undefined !== property.maxLength &&
				property.maxLength <= LONG_TEXT
				? 'text'
				: 'textarea';
		default:
			return null;
	}
}

/**
 * Extension settings the server registered for a context, from the extension manifest the admin app
 * prints (`ohmylmsExtensionManifest.settings`, the same data as `GET /ohmylms/v1/extensions`).
 *
 * @param context  Content context, for example `question`.
 * @param manifest Manifest to read; the page's by default.
 * @return Definitions sorted by ID.
 */
export function settingsDefinitions(
	context: string,
	manifest: unknown = ( window as { ohmylmsExtensionManifest?: unknown } )
		.ohmylmsExtensionManifest
): SettingsDefinition[] {
	const settings =
		manifest && 'object' === typeof manifest
			? ( manifest as Record< string, unknown > ).settings
			: undefined;
	if ( ! plainObject( settings ) ) {
		return [];
	}
	return Object.entries( settings )
		.filter(
			( [ , raw ] ) =>
				plainObject( raw ) &&
				Array.isArray( raw.contexts ) &&
				raw.contexts.includes( context ) &&
				plainObject( raw.schema )
		)
		.map( ( [ id, raw ] ) => {
			const definition = raw as Record< string, unknown >;
			const schema = definition.schema as SettingsSchema;
			const label =
				[ definition.label, schema.title ].find(
					( item ): item is string =>
						'string' === typeof item && '' !== item.trim()
				) ?? id;
			return { id, label, schema };
		} )
		.sort( ( a, b ) => a.id.localeCompare( b.id ) );
}

/**
 * The properties of a settings schema the generic editor can edit: text, URLs, numbers, on/off
 * flags and choices from an `enum`. Nested objects and lists are left to the extension's own panel.
 *
 * @param schema Object schema of the settings.
 * @return Editable fields in schema order, and the names of the other properties.
 */
export function settingsFields( schema: SettingsSchema ): {
	fields: SettingsField[];
	unsupported: string[];
} {
	const required = Array.isArray( schema.required ) ? schema.required : [];
	const fields: SettingsField[] = [];
	const unsupported: string[] = [];
	for ( const [ name, property ] of Object.entries(
		schema.properties ?? {}
	) ) {
		if ( ! plainObject( property ) ) {
			unsupported.push( name );
			continue;
		}
		const type = schemaType( property );
		const base = {
			name,
			label:
				'string' === typeof property.title && property.title.trim()
					? property.title
					: name,
			help:
				'string' === typeof property.description
					? property.description
					: '',
			required: required.includes( name ) || true === property.required,
			choices: [],
			minimum: property.minimum,
			maximum: property.maximum,
			minLength: property.minLength,
			maxLength: property.maxLength,
		};
		if ( Array.isArray( property.enum ) && property.enum.length ) {
			const choices = property.enum.filter(
				( item ): item is string | number =>
					'string' === typeof item || 'number' === typeof item
			);
			if ( choices.length === property.enum.length ) {
				fields.push( {
					...base,
					kind: 'choice',
					choices: choices.map( ( item ) => ( {
						value: String( item ),
						label: String( item ),
						stored: item,
					} ) ),
				} );
			} else {
				unsupported.push( name );
			}
			continue;
		}
		const kind = fieldKind( type, property );
		if ( kind ) {
			fields.push( { ...base, kind } );
		} else {
			unsupported.push( name );
		}
	}
	return { fields, unsupported };
}

/**
 * The value a field stores for what was typed or chosen; `undefined` removes the key.
 *
 * @param field Field.
 * @param input Typed text, chosen value or checkbox state.
 * @return Value to store.
 */
export function fieldValue(
	field: SettingsField,
	input: string | boolean
): unknown {
	if ( 'boolean' === field.kind ) {
		return Boolean( input );
	}
	const text = String( input );
	if ( '' === text.trim() ) {
		return undefined;
	}
	if ( 'number' === field.kind || 'integer' === field.kind ) {
		const number = Number( text );
		return Number.isFinite( number ) ? number : text;
	}
	if ( 'choice' === field.kind ) {
		return field.choices.find( ( item ) => item.value === text )?.stored;
	}
	return text;
}

/**
 * Check values against the editable fields, as the server's schema validation would.
 *
 * @param fields Fields of the schema.
 * @param value  Values of the namespace.
 * @return Messages by property name; empty when valid.
 */
export function validateSettings(
	fields: readonly SettingsField[],
	value: Readonly< Record< string, unknown > >
): Record< string, string > {
	const errors: Record< string, string > = {};
	for ( const field of fields ) {
		const current = value[ field.name ];
		if ( undefined === current || '' === current ) {
			if ( field.required && 'boolean' !== field.kind ) {
				errors[ field.name ] = __(
					'This field is required.',
					'ohmylms'
				);
			}
			continue;
		}
		if ( 'number' === field.kind || 'integer' === field.kind ) {
			if (
				'number' !== typeof current ||
				( 'integer' === field.kind && ! Number.isInteger( current ) )
			) {
				errors[ field.name ] =
					'integer' === field.kind
						? __( 'Enter a whole number.', 'ohmylms' )
						: __( 'Enter a number.', 'ohmylms' );
			} else if (
				( undefined !== field.minimum && current < field.minimum ) ||
				( undefined !== field.maximum && current > field.maximum )
			) {
				errors[ field.name ] = sprintf(
					/* translators: 1: smallest value, 2: largest value. */
					__( 'Enter a value from %1$s to %2$s.', 'ohmylms' ),
					String( field.minimum ?? '−∞' ),
					String( field.maximum ?? '∞' )
				);
			}
			continue;
		}
		if ( 'string' === typeof current ) {
			const length = Array.from( current ).length;
			if ( undefined !== field.maxLength && length > field.maxLength ) {
				errors[ field.name ] = sprintf(
					/* translators: %d: maximum length. */
					__( 'Use at most %d characters.', 'ohmylms' ),
					field.maxLength
				);
			} else if (
				undefined !== field.minLength &&
				length < field.minLength
			) {
				errors[ field.name ] = sprintf(
					/* translators: %d: minimum length. */
					__( 'Use at least %d characters.', 'ohmylms' ),
					field.minLength
				);
			}
		}
	}
	return errors;
}
