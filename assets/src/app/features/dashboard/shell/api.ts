import { del, get, post } from '../../../lib/api';
import { parseEnvelope, parseScopes } from './model';
import type { PeriodDays } from './model';

const NAMESPACE = '/ohmylms/v1/dashboard';

/**
 * Scopes the current user may pick.
 *
 * @param signal Cancels the request.
 */
export async function getScopes( signal?: AbortSignal ) {
	return parseScopes(
		await get< unknown >( `${ NAMESPACE }/scopes`, { signal } )
	);
}

/**
 * Data of one widget for a scope and period.
 *
 * @param widget Widget id (`needs-attention`).
 * @param scope  Scope id.
 * @param period Period length.
 * @param parse  Validates the widget's `data`.
 * @param signal Cancels the request.
 */
export async function getWidget< T >(
	widget: string,
	scope: string,
	period: PeriodDays,
	parse: ( data: unknown ) => T,
	signal?: AbortSignal
) {
	return parseEnvelope(
		await get< unknown >(
			`${ NAMESPACE }/widgets/${ encodeURIComponent( widget ) }`,
			{ signal, query: { scope, period } }
		),
		parse
	);
}

/**
 * Snooze a "needs attention" item for the current user (seven days).
 *
 * @param scope Scope the item was shown in.
 * @param key   Item key.
 */
export function snoozeItem( scope: string, key: string ) {
	return post< unknown >( `${ NAMESPACE }/widgets/needs-attention/snoozes`, {
		scope,
		key,
	} );
}

/**
 * Undo a snooze.
 *
 * @param scope Scope the item was shown in.
 * @param key   Item key.
 */
export function wakeItem( scope: string, key: string ) {
	return del< unknown >( `${ NAMESPACE }/widgets/needs-attention/snoozes`, {
		query: { scope, key },
	} );
}
