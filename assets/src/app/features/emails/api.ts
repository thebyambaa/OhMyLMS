import { get, patch, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	emailId,
	parseEmail,
	parsePreview,
	parseSettings,
	parseTestResult,
} from './model';
import type {
	EmailFilters,
	EmailInput,
	EmailSettings,
	EmailTemplate,
} from './types';

const BASE = '/ohmylms/v1/emails';
const itemPath = ( id: string ) => `${ BASE }/${ emailId( id ) }`;

/**
 * List the system emails (there are fewer than 20, so one request returns them all).
 *
 * @param filters Search text and recipient filter.
 * @param signal  Cancels the request.
 * @return Validated emails and the server's totals.
 */
export function listEmails( filters: EmailFilters, signal?: AbortSignal ) {
	return getCollection( BASE, parseEmail, {
		signal,
		query: {
			per_page: 100,
			search: filters.search.trim() || undefined,
			recipient_type:
				'all' === filters.recipient_type
					? undefined
					: filters.recipient_type,
		},
	} );
}

/**
 * Read one email.
 *
 * @param id     Email id.
 * @param signal Cancels the request.
 * @return The validated email.
 */
export async function getEmail( id: string, signal?: AbortSignal ) {
	return parseEmail( await get< unknown >( itemPath( id ), { signal } ) );
}

/**
 * Save changed fields of an email. Only the fields in `input` change.
 *
 * @param id     Email id.
 * @param input  Fields to change.
 * @param signal Cancels the request.
 * @return The saved email.
 */
export async function saveEmail(
	id: string,
	input: EmailInput,
	signal?: AbortSignal
): Promise< EmailTemplate > {
	return parseEmail(
		await put< unknown >( itemPath( id ), input, { signal } )
	);
}

/**
 * Switch an email on or off.
 *
 * @param id      Email id.
 * @param enabled New state.
 * @param signal  Cancels the request.
 * @return The saved email.
 */
export async function setEmailEnabled(
	id: string,
	enabled: boolean,
	signal?: AbortSignal
): Promise< EmailTemplate > {
	return parseEmail(
		await patch< unknown >( itemPath( id ), { enabled }, { signal } )
	);
}

/**
 * Render an email with sample data. Unsaved fields in `input` are applied over the stored email.
 *
 * @param id     Email id.
 * @param input  Draft fields.
 * @param signal Cancels the request.
 * @return Sanitized HTML, subject and unknown tags.
 */
export async function previewEmail(
	id: string,
	input: EmailInput,
	signal?: AbortSignal
) {
	return parsePreview(
		await post< unknown >( `${ itemPath( id ) }/preview`, input, {
			signal,
		} )
	);
}

/**
 * Send a test email with sample data to the signed-in administrator. The server accepts no other recipient.
 *
 * @param id     Email id.
 * @param input  Draft fields to test instead of the saved ones.
 * @param signal Cancels the request.
 * @return Where it was sent.
 */
export async function sendTestEmail(
	id: string,
	input: EmailInput,
	signal?: AbortSignal
) {
	return parseTestResult(
		await post< unknown >( `${ itemPath( id ) }/test`, input, { signal } )
	);
}

/**
 * Read the global email settings.
 *
 * @param signal Cancels the request.
 * @return The validated settings.
 */
export async function getEmailSettings( signal?: AbortSignal ) {
	return parseSettings(
		await get< unknown >( `${ BASE }/settings`, { signal } )
	);
}

/**
 * Save changed global settings. Only the fields in `input` change.
 *
 * @param input  Fields to change.
 * @param signal Cancels the request.
 * @return The saved settings.
 */
export async function saveEmailSettings(
	input: Partial< EmailSettings >,
	signal?: AbortSignal
) {
	return parseSettings(
		await put< unknown >( `${ BASE }/settings`, input, { signal } )
	);
}
