import { __, sprintf } from '@wordpress/i18n';
import { get, patch } from '../../lib/api';
import { changedValues } from './designSettings';
import {
	asArray,
	asBoolean,
	asNumber,
	asRecord,
	asString,
	invalidResponse,
} from './model';
import type { FieldErrors } from './types';

/** Values of `/settings/at-risk`. */
export interface AtRiskValues {
	inactive_enabled: boolean;
	inactive_days: number;
	inactive_severe_days: number;
	mastery_enabled: boolean;
	mastery_drop: number;
	mastery_window_days: number;
	mastery_severe_drop: number;
	overdue_enabled: boolean;
	overdue_items: number;
	overdue_severe_items: number;
	nudge_guardians: boolean;
	nudge_daily_cap: number;
}

export type AtRiskNumber = {
	[ K in keyof AtRiskValues ]: AtRiskValues[ K ] extends number ? K : never;
}[ keyof AtRiskValues ];
export type AtRiskToggle = Exclude< keyof AtRiskValues, AtRiskNumber >;

/** Allowed range of every number field (the server answers 422 outside it). */
export const AT_RISK_RANGES: Readonly<
	Record< AtRiskNumber, readonly [ number, number ] >
> = {
	inactive_days: [ 1, 365 ],
	inactive_severe_days: [ 1, 365 ],
	mastery_drop: [ 1, 100 ],
	mastery_window_days: [ 1, 90 ],
	mastery_severe_drop: [ 1, 100 ],
	overdue_items: [ 1, 100 ],
	overdue_severe_items: [ 1, 100 ],
	nudge_daily_cap: [ 1, 200 ],
};

const TOGGLES: readonly AtRiskToggle[] = [
	'inactive_enabled',
	'mastery_enabled',
	'overdue_enabled',
	'nudge_guardians',
];

/** Severe threshold => the rule threshold it must not be below. */
export const SEVERE_OF: Readonly<
	Partial< Record< AtRiskNumber, AtRiskNumber > >
> = {
	inactive_severe_days: 'inactive_days',
	mastery_severe_drop: 'mastery_drop',
	overdue_severe_items: 'overdue_items',
};

function integer( value: unknown ): number {
	const number = asNumber( value );
	if ( ! Number.isInteger( number ) ) {
		throw invalidResponse();
	}
	return number;
}

/**
 * Validate a `/settings/at-risk` response.
 *
 * @param raw Decoded body.
 * @return Values and the names that changed.
 */
export function parseAtRiskSettings( raw: unknown ): {
	values: AtRiskValues;
	changed: string[];
} {
	const body = asRecord( raw );
	if ( 'at-risk' !== body.group ) {
		throw invalidResponse();
	}
	const values = asRecord( body.values );
	const parsed = {} as Record< string, number | boolean >;
	for ( const name of TOGGLES ) {
		parsed[ name ] = asBoolean( values[ name ] );
	}
	for ( const name of Object.keys( AT_RISK_RANGES ) ) {
		parsed[ name ] = integer( values[ name ] );
	}
	return {
		values: parsed as unknown as AtRiskValues,
		changed: asArray( body.changed ).map( asString ),
	};
}

/**
 * Validate a draft like the server does.
 *
 * @param draft Edited values.
 * @return Field name => message.
 */
export function validateAtRisk( draft: AtRiskValues ): FieldErrors {
	const errors: FieldErrors = {};
	for ( const [ name, [ min, max ] ] of Object.entries( AT_RISK_RANGES ) ) {
		const value = draft[ name as AtRiskNumber ];
		if ( ! Number.isInteger( value ) || value < min || value > max ) {
			errors[ name ] = sprintf(
				/* translators: 1: smallest value, 2: largest value. */
				__( 'Enter a number from %1$d to %2$d.', 'ohmylms' ),
				min,
				max
			);
		}
	}
	for ( const [ severe, base ] of Object.entries( SEVERE_OF ) ) {
		if (
			! errors[ severe ] &&
			base &&
			! errors[ base ] &&
			draft[ severe as AtRiskNumber ] < draft[ base ]
		) {
			errors[ severe ] = __(
				'The high-risk threshold cannot be below the rule’s threshold.',
				'ohmylms'
			);
		}
	}
	return errors;
}

export async function getAtRiskSettings( signal?: AbortSignal ) {
	return parseAtRiskSettings(
		await get< unknown >( '/ohmylms/v1/settings/at-risk', { signal } )
	);
}

export async function saveAtRiskSettings(
	stored: AtRiskValues,
	draft: AtRiskValues,
	signal?: AbortSignal
) {
	return parseAtRiskSettings(
		await patch< unknown >(
			'/ohmylms/v1/settings/at-risk',
			changedValues( stored, draft ),
			{ signal }
		)
	);
}
