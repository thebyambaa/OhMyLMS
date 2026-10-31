import { invalidResponse, record } from '../model';
import type { Currency } from '../types';

export type PulseMetricId =
	'revenue' | 'active_learners' | 'completions' | 'renewals_at_risk';

export interface PulseMetric {
	readonly id: PulseMetricId;
	readonly format: 'money' | 'number';
	readonly value: number;
	readonly previous: number;
	/** One value per day of the period (`dates`). */
	readonly series: readonly number[];
	/** Subscriptions on hold now (`renewals_at_risk` only). */
	readonly onHold?: number;
}

export interface Pulse {
	readonly dates: readonly string[];
	readonly currency: Currency | null;
	readonly metrics: readonly PulseMetric[];
}

const IDS: readonly PulseMetricId[] = [
	'revenue',
	'active_learners',
	'completions',
	'renewals_at_risk',
];

function amount( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isFinite( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function parseCurrency( value: unknown ): Currency | null {
	if ( null === value || undefined === value ) {
		return null;
	}
	const item = record( value );
	const decimals = amount( item.decimals );
	return {
		code: text( item.code ),
		symbol: text( item.symbol ),
		decimals: Math.max( 0, Math.min( 6, Math.round( decimals ) ) ),
		position: text( item.position ),
	};
}

function parseMetric( value: unknown, days: number ): PulseMetric | null {
	const item = record( value );
	const id = IDS.find( ( option ) => option === item.id );
	if ( ! id ) {
		// A metric this client does not know yet is skipped, not an error.
		return null;
	}
	if ( ! Array.isArray( item.series ) || item.series.length !== days ) {
		throw invalidResponse();
	}
	return {
		id,
		format: 'money' === item.format ? 'money' : 'number',
		value: amount( item.value ),
		previous: amount( item.previous ),
		series: item.series.map( amount ),
		...( undefined === item.on_hold
			? {}
			: { onHold: amount( item.on_hold ) } ),
	};
}

/**
 * Validate the `pulse` widget data.
 *
 * @param value Decoded `data`.
 * @return The metrics.
 */
export function parsePulse( value: unknown ): Pulse {
	const data = record( value );
	if ( ! Array.isArray( data.dates ) || ! Array.isArray( data.metrics ) ) {
		throw invalidResponse();
	}
	const dates = data.dates.map( text );
	return {
		dates,
		currency: parseCurrency( data.currency ),
		metrics: data.metrics
			.map( ( metric ) => parseMetric( metric, dates.length ) )
			.filter( ( metric ): metric is PulseMetric => null !== metric ),
	};
}

/**
 * Points of a sparkline polyline in a `width` × `height` box with `pad` pixels of padding; a flat line
 * through the middle when every value is equal.
 *
 * @param values Series.
 * @param width  Box width.
 * @param height Box height.
 * @param pad    Vertical padding.
 * @return `points` attribute value.
 */
export function sparklinePoints(
	values: readonly number[],
	width: number,
	height: number,
	pad = 3
): string {
	if ( 0 === values.length ) {
		return '';
	}
	const max = Math.max( ...values );
	const min = Math.min( ...values );
	const span = max - min;
	const step = values.length > 1 ? width / ( values.length - 1 ) : 0;
	const round = ( n: number ) => Math.round( n * 10 ) / 10;
	const points = values.map( ( value, index ) => {
		const y =
			0 === span
				? height / 2
				: height -
					pad -
					( ( value - min ) / span ) * ( height - 2 * pad );
		return `${ round( index * step ) },${ round( y ) }`;
	} );
	return 1 === values.length
		? `0,${ height / 2 } ${ width },${ height / 2 }`
		: points.join( ' ' );
}
