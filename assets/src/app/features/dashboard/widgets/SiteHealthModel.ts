import { invalidResponse, record } from '../model';

export type CheckStatus = 'ok' | 'warning' | 'error';

export interface SiteCheck {
	/** payment_gateway, email_sender, certificate_template, cron, privacy_policy (or a newer one). */
	readonly id: string;
	readonly status: CheckStatus;
	readonly label: string;
	readonly detail: string;
	/** Where to fix it: an app `route` or a wp-admin `href`. */
	readonly action: {
		readonly label: string;
		readonly route: string | null;
		readonly href: string | null;
	} | null;
}

export interface SiteHealth {
	readonly checks: readonly SiteCheck[];
	readonly passed: number;
	readonly total: number;
}

const STATUSES: readonly CheckStatus[] = [ 'ok', 'warning', 'error' ];

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function optional( value: unknown ): string | null {
	return null === value || undefined === value ? null : text( value );
}

function whole( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

function parseCheck( value: unknown ): SiteCheck {
	const item = record( value );
	const status = STATUSES.find( ( option ) => option === item.status );
	if ( ! status ) {
		throw invalidResponse();
	}
	let action: SiteCheck[ 'action' ] = null;
	if ( null !== item.action && undefined !== item.action ) {
		const raw = record( item.action );
		const route = optional( raw.route );
		const href = optional( raw.href );
		if (
			( null !== route &&
				( ! route.startsWith( '/' ) || route.startsWith( '//' ) ) ) ||
			( null !== href && ! /^https?:\/\//.test( href ) ) ||
			( null === route && null === href )
		) {
			throw invalidResponse();
		}
		action = { label: text( raw.label ), route, href };
	}
	return {
		id: text( item.id ),
		status,
		label: text( item.label ),
		detail: text( item.detail ),
		action,
	};
}

/**
 * Validate the `site-health` widget data.
 *
 * @param value Decoded `data`.
 * @return The checks.
 */
export function parseSiteHealth( value: unknown ): SiteHealth {
	const data = record( value );
	if ( ! Array.isArray( data.checks ) ) {
		throw invalidResponse();
	}
	return {
		checks: data.checks.map( parseCheck ),
		passed: whole( data.passed ),
		total: whole( data.total ),
	};
}
