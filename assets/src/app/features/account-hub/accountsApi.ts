import { get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	parseAccount,
	parseAccountOptions,
	parseEditableAccount,
} from './accounts';
import { PER_PAGE } from './api';
import { positiveId } from './model';
import type { AccountFilters, AccountInput, NewAccountInput } from './types';

const BASE = '/ohmylms/v1/account-hub/users';

/**
 * List the site's accounts by name.
 *
 * @param page    Page number, from 1.
 * @param search  Name, username or email search.
 * @param filters Role, registration period and status.
 * @param signal  Cancels the request.
 * @return Validated accounts and totals.
 */
export function listAccounts(
	page: number,
	search: string,
	filters: AccountFilters,
	signal?: AbortSignal
) {
	return getCollection( BASE, parseAccount, {
		signal,
		query: {
			page,
			per_page: PER_PAGE,
			search,
			registered: filters.registered,
			status: filters.status,
			...( filters.role ? { role: filters.role } : {} ),
		},
	} );
}

/**
 * Role choices and the account types the current user may create.
 *
 * @param signal Cancels the request.
 * @return The options.
 */
export async function getAccountOptions( signal?: AbortSignal ) {
	return parseAccountOptions(
		await get< unknown >( `${ BASE }/options`, { signal } )
	);
}

/**
 * Create a student, teacher or parent account.
 *
 * @param input  Form values.
 * @param signal Cancels the request.
 * @return The created account as listed.
 */
export async function createAccount(
	input: NewAccountInput,
	signal?: AbortSignal
) {
	return parseAccount(
		await post< unknown >(
			BASE,
			{
				type: input.type,
				first_name: input.first_name.trim(),
				last_name: input.last_name.trim(),
				email: input.email.trim(),
				username: input.username.trim(),
				send_email: input.send_email,
			},
			{ signal }
		)
	);
}

/**
 * Read an account for editing.
 *
 * @param id     User id.
 * @param signal Cancels the request.
 * @return The account.
 */
export async function getAccount( id: number, signal?: AbortSignal ) {
	return parseEditableAccount(
		await get< unknown >( `${ BASE }/${ positiveId( id ) }`, { signal } )
	);
}

/**
 * Save an account's name and email. A stale `version` is rejected with 409.
 *
 * @param id        User id.
 * @param input     Name and email.
 * @param version   Version the form was loaded from.
 * @param sendReset Email a password reset link after saving.
 * @param signal    Cancels the request.
 * @return The saved account.
 */
export async function updateAccount(
	id: number,
	input: AccountInput,
	version: string,
	sendReset: boolean,
	signal?: AbortSignal
) {
	return parseEditableAccount(
		await patch< unknown >(
			`${ BASE }/${ positiveId( id ) }`,
			{
				first_name: input.first_name.trim(),
				last_name: input.last_name.trim(),
				email: input.email.trim(),
				send_reset: sendReset,
				version,
			},
			{ signal }
		)
	);
}
