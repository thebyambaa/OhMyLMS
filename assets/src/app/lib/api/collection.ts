import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { ApiError, normalizeApiError } from './errors';
import { withQuery } from './query';
import type { RequestExtras } from './client';

export interface Collection< T > {
	items: T[];
	total: number;
	totalPages: number;
}

/**
 * Read a WordPress collection and pagination through the nonce-aware client.
 *
 * @param path      REST collection route.
 * @param parseItem Runtime validator for each item.
 * @param extras    Query parameters, cancellation and headers.
 * @return Validated items and pagination totals.
 */
export async function getCollection< T >(
	path: string,
	parseItem: ( value: unknown ) => T,
	extras: RequestExtras = {}
): Promise< Collection< T > > {
	try {
		const response = await apiFetch< unknown, false >( {
			path: withQuery( path, extras.query ),
			method: 'GET',
			parse: false,
			signal: extras.signal,
			headers: extras.headers ? { ...extras.headers } : undefined,
		} );
		const body: unknown = await response.json();
		const header = ( name: string ) => {
			const value = response.headers.get( name );
			if (
				null === value ||
				! /^\d+$/.test( value ) ||
				! Number.isSafeInteger( Number( value ) )
			) {
				throw new ApiError( {
					code: 'invalid_response',
					status: 502,
					message: __(
						'The server returned invalid pagination.',
						'ohmylms'
					),
				} );
			}
			return Number( value );
		};
		if ( ! Array.isArray( body ) ) {
			throw new ApiError( {
				code: 'invalid_response',
				status: 502,
				message: __(
					'The server returned an invalid collection.',
					'ohmylms'
				),
			} );
		}
		return {
			items: body.map( parseItem ),
			total: header( 'X-WP-Total' ),
			totalPages: header( 'X-WP-TotalPages' ),
		};
	} catch ( error ) {
		throw normalizeApiError( error );
	}
}
