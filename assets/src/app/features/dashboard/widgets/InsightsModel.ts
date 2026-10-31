import { invalidResponse, record } from '../model';

export type InsightSeverity = 'error' | 'warning' | 'success' | 'info';

export interface Insight {
	/** Stable key such as `revenue` or `drop_off:12`. */
	readonly key: string;
	/** Rule id: revenue, drop_off, class_gap, missed_question, quiz_lessons, completions (or a newer one). */
	readonly rule: string;
	readonly severity: InsightSeverity;
	/** Server-translated sentence with the numbers. */
	readonly text: string;
	/** Internal app route the user may open, or null. */
	readonly action: { readonly label: string; readonly route: string } | null;
}

export interface Insights {
	readonly insights: readonly Insight[];
}

const SEVERITIES: readonly InsightSeverity[] = [
	'error',
	'warning',
	'success',
	'info',
];

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function parseInsight( value: unknown ): Insight {
	const item = record( value );
	const severity = SEVERITIES.find( ( option ) => option === item.severity );
	const key = text( item.key );
	if ( ! severity || ! /^[a-z][a-z0-9_]*(:\d+)?$/.test( key ) ) {
		throw invalidResponse();
	}
	let action: Insight[ 'action' ] = null;
	if ( null !== item.action && undefined !== item.action ) {
		const raw = record( item.action );
		const route = text( raw.route );
		if ( ! route.startsWith( '/' ) || route.startsWith( '//' ) ) {
			throw invalidResponse();
		}
		action = { label: text( raw.label ), route };
	}
	return {
		key,
		rule: text( item.rule ),
		severity,
		text: text( item.text ),
		action,
	};
}

/**
 * Validate the `insights` widget data.
 *
 * @param value Decoded `data`.
 * @return The insights, most important first.
 */
export function parseInsights( value: unknown ): Insights {
	const data = record( value );
	if ( ! Array.isArray( data.insights ) ) {
		throw invalidResponse();
	}
	return { insights: data.insights.map( parseInsight ) };
}
