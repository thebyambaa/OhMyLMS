import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	cleanInput,
	invalidResponse,
	parseDelivery,
	parseOptions,
	parseWebhook,
	positiveId,
	record,
} from './model';
import type { SecretWebhook, WebhookInput } from './types';

const BASE = '/ohmylms/v1/webhooks';
const itemPath = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;

export async function listWebhooks(
	page: number,
	search: string,
	signal?: AbortSignal
) {
	return getCollection( BASE, parseWebhook, {
		signal,
		query: { page, per_page: 20, search, orderby: 'id', order: 'desc' },
	} );
}
export async function getOptions( signal?: AbortSignal ) {
	return parseOptions(
		await get< unknown >( `${ BASE }/triggers`, { signal } )
	);
}
export async function saveWebhook(
	input: WebhookInput,
	id: number | null,
	signal?: AbortSignal
) {
	const payload = cleanInput( input );
	const raw = await ( null === id
		? post< unknown >( BASE, payload, { signal } )
		: put< unknown >( itemPath( id ), payload, { signal } ) );
	const webhook = parseWebhook( raw );
	const secret = null === id ? record( raw ).secret : undefined;
	if ( null === id && ( 'string' !== typeof secret || ! secret ) ) {
		throw invalidResponse();
	}
	return { webhook, secret: 'string' === typeof secret ? secret : undefined };
}
export async function deleteWebhook( id: number, signal?: AbortSignal ) {
	const response = record(
		await del< unknown >( itemPath( id ), { signal } )
	);
	if ( true !== response.deleted ) {
		throw invalidResponse();
	}
}
export async function rotateSecret(
	id: number,
	signal?: AbortSignal
): Promise< SecretWebhook > {
	const raw = await post< unknown >(
		`${ itemPath( id ) }/rotate-secret`,
		undefined,
		{ signal }
	);
	const item = record( raw );
	if ( 'string' !== typeof item.secret || ! item.secret ) {
		throw invalidResponse();
	}
	return { ...parseWebhook( raw ), secret: item.secret };
}
export async function listDeliveries(
	id: number,
	page: number,
	signal?: AbortSignal
) {
	return getCollection( `${ itemPath( id ) }/deliveries`, parseDelivery, {
		signal,
		query: { page, per_page: 20 },
	} );
}
export async function sendTest( id: number, signal?: AbortSignal ) {
	const response = record(
		await post< unknown >(
			`${ BASE }/test`,
			{ id: positiveId( id ) },
			{ signal }
		)
	);
	if ( 'boolean' !== typeof response.success ) {
		throw invalidResponse();
	}
	return response.success;
}
