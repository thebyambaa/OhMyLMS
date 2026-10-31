import { __, sprintf } from '@wordpress/i18n';
import { invalidResponse, record } from '../model';

/** Lengths of the dashboard period, in days (`period=` on every widget endpoint). */
export const PERIODS = [ 7, 30, 90 ] as const;
export type PeriodDays = ( typeof PERIODS )[ number ];
export const DEFAULT_PERIOD: PeriodDays = 30;

export type ScopeType = 'all' | 'school' | 'class';

/** One audience the user may pick (`GET /dashboard/scopes`). */
export interface DashboardScope {
	/** `all`, `school:<id>` or `class:<id>`. */
	readonly id: string;
	readonly type: ScopeType;
	readonly label: string;
	/** The school itself, a class's school, or 0. */
	readonly schoolId: number;
	readonly schoolName: string;
	/** Whether commerce and site-wide figures belong in this scope (`all` for site administrators only). */
	readonly commerce: boolean;
}

export interface ScopeList {
	readonly scopes: readonly DashboardScope[];
	readonly defaultScope: string | null;
	readonly siteAdmin: boolean;
}

/** What every widget is rendered for. */
export interface WidgetContext {
	readonly scope: DashboardScope;
	readonly period: PeriodDays;
	readonly siteAdmin: boolean;
}

export interface PeriodInfo {
	readonly days: number;
	readonly start: string;
	readonly end: string;
	readonly previousStart: string;
	readonly previousEnd: string;
}

/** Envelope of `GET /dashboard/widgets/<id>`. */
export interface WidgetEnvelope< T > {
	readonly widget: string;
	readonly scope: string;
	readonly period: PeriodInfo;
	readonly generatedAt: string;
	readonly data: T;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
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

const SCOPE_TYPES: readonly ScopeType[] = [ 'all', 'school', 'class' ];

/**
 * Validate one scope.
 *
 * @param value Decoded JSON.
 * @return The scope.
 */
export function parseScope( value: unknown ): DashboardScope {
	const item = record( value );
	const type = SCOPE_TYPES.find( ( option ) => option === item.type );
	const id = text( item.id );
	if ( ! type || ! /^(all|school:[1-9]\d*|class:[1-9]\d*)$/.test( id ) ) {
		throw invalidResponse();
	}
	return {
		id,
		type,
		label: text( item.label ),
		schoolId: whole( item.school_id ),
		schoolName: text( item.school_name ),
		commerce: true === item.commerce,
	};
}

/**
 * Validate `GET /dashboard/scopes`.
 *
 * @param value Decoded JSON.
 * @return The scopes.
 */
export function parseScopes( value: unknown ): ScopeList {
	const item = record( value );
	if ( ! Array.isArray( item.scopes ) ) {
		throw invalidResponse();
	}
	return {
		scopes: item.scopes.map( parseScope ),
		defaultScope:
			null === item.default || undefined === item.default
				? null
				: text( item.default ),
		siteAdmin: true === item.site_admin,
	};
}

/**
 * Validate a widget envelope; the widget validates its own `data`.
 *
 * @param value Decoded JSON.
 * @param parse Validates `data`.
 * @return The envelope.
 */
export function parseEnvelope< T >(
	value: unknown,
	parse: ( data: unknown ) => T
): WidgetEnvelope< T > {
	const item = record( value );
	const period = record( item.period );
	return {
		widget: text( item.widget ),
		scope: text( record( item.scope ).id ),
		period: {
			days: whole( period.days ),
			start: text( period.start ),
			end: text( period.end ),
			previousStart: text( period.previous_start ),
			previousEnd: text( period.previous_end ),
		},
		generatedAt: text( item.generated_at ),
		data: parse( item.data ),
	};
}

/**
 * Period from the `period` search parameter; anything else is the default.
 *
 * @param value Raw parameter.
 * @return Period length.
 */
export function parsePeriod( value: string | null ): PeriodDays {
	const days = Number( value );
	return PERIODS.find( ( option ) => option === days ) ?? DEFAULT_PERIOD;
}

/**
 * The scope to show: the requested one when the user may use it, otherwise the server's default.
 *
 * @param list      Scopes of the user.
 * @param requested `scope` search parameter.
 * @return The scope, or null when the user has none.
 */
export function chooseScope(
	list: ScopeList,
	requested: string | null
): DashboardScope | null {
	return (
		list.scopes.find( ( scope ) => scope.id === requested ) ??
		list.scopes.find( ( scope ) => scope.id === list.defaultScope ) ??
		list.scopes[ 0 ] ??
		null
	);
}

/** Options of the scope picker: the site-wide scope, then one group per school. */
export interface ScopeGroup {
	readonly label: string;
	readonly options: readonly { value: string; label: string }[];
}

/**
 * Group scopes for the picker: `All schools` alone, then per school its own scope ("whole school") and
 * its classes, then classes without a school.
 *
 * @param scopes Scopes in server order.
 * @return Ungrouped options and groups.
 */
export function groupScopes( scopes: readonly DashboardScope[] ): {
	top: { value: string; label: string }[];
	groups: ScopeGroup[];
} {
	const top: { value: string; label: string }[] = [];
	const groups = new Map<
		string,
		{ label: string; options: { value: string; label: string }[] }
	>();
	const groupOf = ( key: string, label: string ) => {
		let group = groups.get( key );
		if ( ! group ) {
			group = { label, options: [] };
			groups.set( key, group );
		}
		return group;
	};
	for ( const scope of scopes ) {
		if ( 'all' === scope.type ) {
			top.push( { value: scope.id, label: scope.label } );
		} else if ( 'school' === scope.type ) {
			groupOf( `school:${ scope.schoolId }`, scope.label ).options.push( {
				value: scope.id,
				label: sprintf(
					/* translators: %s: school name. */
					__( '%s (whole school)', 'ohmylms' ),
					scope.label
				),
			} );
		} else {
			const group =
				scope.schoolId > 0
					? groupOf( `school:${ scope.schoolId }`, scope.schoolName )
					: groupOf(
							'independent',
							__( 'Classes without a school', 'ohmylms' )
						);
			group.options.push( { value: scope.id, label: scope.label } );
		}
	}
	return { top, groups: [ ...groups.values() ] };
}

/**
 * Label of a period length.
 *
 * @param days Period length.
 * @return For example `30 days`.
 */
export function periodLabel( days: number ): string {
	return sprintf(
		/* translators: %d: number of days. */
		__( '%d days', 'ohmylms' ),
		days
	);
}
