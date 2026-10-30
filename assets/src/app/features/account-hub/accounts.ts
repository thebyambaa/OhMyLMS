import { __, sprintf } from '@wordpress/i18n';
import { invalidResponse, positiveId, record } from './model';
import {
	ACCOUNT_TYPES,
	type Account,
	type AccountInput,
	type AccountOptions,
	type AccountType,
	type EditableAccount,
	type FormErrors,
	type NewAccountInput,
	type RoleRef,
} from './types';

/** Longest name part the server accepts. */
export const MAX_ACCOUNT_NAME = 190;

/** Longest email the server accepts. */
export const MAX_EMAIL = 100;

/** Longest username the server accepts. */
export const MAX_USERNAME = 60;

const VERSION = /^[a-f0-9]{16}$/;
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const USERNAME = /^[A-Za-z0-9 _.\-@]+$/;

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}

function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
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

function parseRole( value: unknown ): RoleRef {
	const item = record( value );
	return { slug: text( item.slug ), label: text( item.label ) };
}

/**
 * Parse one listed account.
 *
 * @param value Parsed JSON.
 * @return The account.
 */
export function parseAccount( value: unknown ): Account {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		display_name: text( item.display_name ),
		username: text( item.username ),
		email: text( item.email ),
		roles: list( item.roles ).map( parseRole ),
		registered_at: nullableText( item.registered_at ),
		last_login: nullableText( item.last_login ),
		blocked: flag( item.blocked ),
		can_edit: flag( item.can_edit ),
	};
}

/**
 * Parse an account opened for editing.
 *
 * @param value Parsed JSON.
 * @return The account.
 */
export function parseEditableAccount( value: unknown ): EditableAccount {
	const item = record( value );
	const version = text( item.version );
	if ( ! VERSION.test( version ) ) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		username: text( item.username ),
		display_name: text( item.display_name ),
		first_name: text( item.first_name ),
		last_name: text( item.last_name ),
		email: text( item.email ),
		roles: list( item.roles ).map( parseRole ),
		version,
	};
}

/**
 * Parse the filter choices and creatable account types.
 *
 * @param value Parsed JSON.
 * @return The options.
 */
export function parseAccountOptions( value: unknown ): AccountOptions {
	const item = record( value );
	return {
		roles: list( item.roles ).map( parseRole ),
		types: list( item.types ).map( ( entry ) => {
			const type = record( entry );
			const found = ACCOUNT_TYPES.find( ( id ) => id === type.value );
			if ( undefined === found ) {
				throw invalidResponse();
			}
			return { value: found as AccountType, label: text( type.label ) };
		} ),
		can_create: flag( item.can_create ),
	};
}

function length( value: string ): number {
	return Array.from( value.trim() ).length;
}

/**
 * Validate the name and email of an account form.
 *
 * @param input Form values.
 * @return Field errors; empty when valid.
 */
export function validateAccount(
	input: AccountInput
): FormErrors< AccountInput > {
	const errors: FormErrors< AccountInput > = {};
	if ( 0 === length( input.first_name ) ) {
		errors.first_name = __( 'Enter a first name.', 'ohmylms' );
	} else if ( length( input.first_name ) > MAX_ACCOUNT_NAME ) {
		errors.first_name = sprintf(
			/* translators: %d: maximum length. */
			__( 'Use at most %d characters.', 'ohmylms' ),
			MAX_ACCOUNT_NAME
		);
	}
	if ( length( input.last_name ) > MAX_ACCOUNT_NAME ) {
		errors.last_name = sprintf(
			/* translators: %d: maximum length. */
			__( 'Use at most %d characters.', 'ohmylms' ),
			MAX_ACCOUNT_NAME
		);
	}
	const email = input.email.trim();
	if ( ! EMAIL.test( email ) || email.length > MAX_EMAIL ) {
		errors.email = __( 'Enter a valid email address.', 'ohmylms' );
	}
	return errors;
}

/**
 * Validate the create-account form.
 *
 * @param input Form values.
 * @return Field errors; empty when valid.
 */
export function validateNewAccount(
	input: NewAccountInput
): FormErrors< NewAccountInput > {
	const errors: FormErrors< NewAccountInput > = validateAccount( input );
	const username = input.username.trim();
	if (
		username &&
		( ! USERNAME.test( username ) || username.length > MAX_USERNAME )
	) {
		errors.username = __(
			'Use only letters, numbers, spaces and . - _ @ in the username.',
			'ohmylms'
		);
	}
	return errors;
}

/**
 * Format a UTC ISO time as a local date.
 *
 * @param value ISO string, or null.
 * @return A localised date, or an empty string.
 */
export function formatDate( value: string | null ): string {
	if ( ! value ) {
		return '';
	}
	const date = new Date( value );
	return Number.isNaN( date.getTime() )
		? ''
		: new Intl.DateTimeFormat( undefined, { dateStyle: 'medium' } ).format(
				date
			);
}

/**
 * The server field named by a failed save, if it is one of the form's fields.
 *
 * @param details Error details (`data`).
 * @param fields  Field names of the form.
 * @return The field, or undefined.
 */
export function failedField< T extends string >(
	details: unknown,
	fields: readonly T[]
): T | undefined {
	if ( ! details || 'object' !== typeof details ) {
		return undefined;
	}
	const field = ( details as Record< string, unknown > ).field;
	return fields.find( ( name ) => name === field );
}
