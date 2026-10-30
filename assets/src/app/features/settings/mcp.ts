import { get, post } from '../../lib/api';
import {
	asArray,
	asBoolean,
	asRecord,
	asString,
	invalidResponse,
} from './model';

const PATH = '/ohmylms/v1/mcp/settings';

export type McpProviderId = 'openai' | 'anthropic' | 'gemini';
const PROVIDERS: readonly McpProviderId[] = [ 'openai', 'anthropic', 'gemini' ];

export interface McpProvider {
	id: McpProviderId;
	label: string;
	configured: boolean;
	created: string | null;
}

/** `GET /mcp/settings`. Tokens are stored hashed; only `configured` comes back. */
export interface McpSnapshot {
	serverUrl: string;
	guideUrl: string;
	providers: McpProvider[];
	tools: string[];
	ready: boolean;
	message: string;
}

export function parseMcp( raw: unknown ): McpSnapshot {
	const body = asRecord( raw );
	const status = asRecord( body.status );
	return {
		serverUrl: asString( body.server_url ),
		guideUrl: asString( body.guide_url ),
		providers: asArray( body.providers ).map( ( item ) => {
			const row = asRecord( item );
			const id = asString( row.id ) as McpProviderId;
			if ( ! PROVIDERS.includes( id ) ) {
				throw invalidResponse();
			}
			return {
				id,
				label: asString( row.label ),
				configured: asBoolean( row.configured ),
				created: 'string' === typeof row.created ? row.created : null,
			};
		} ),
		tools: asArray( body.tools ).map( asString ),
		ready: asBoolean( status.ready ),
		message: 'string' === typeof status.message ? status.message : '',
	};
}

export async function getMcp( signal?: AbortSignal ): Promise< McpSnapshot > {
	return parseMcp( await get< unknown >( PATH, { signal } ) );
}

/**
 * Generate (or replace) or revoke the access token of one AI client.
 *
 * @param provider Client.
 * @param action   `generate` or `revoke`.
 * @param signal   Cancels the request.
 * @return The settings and, after `generate`, the token, which is shown once and never again.
 */
export async function manageMcp(
	provider: McpProviderId,
	action: 'generate' | 'revoke',
	signal?: AbortSignal
): Promise< { snapshot: McpSnapshot; token: string | null } > {
	const body = asRecord(
		await post< unknown >(
			`${ PATH }/${ provider }`,
			{ action },
			{ signal }
		)
	);
	return {
		snapshot: parseMcp( body ),
		token: 'string' === typeof body.token ? body.token : null,
	};
}
