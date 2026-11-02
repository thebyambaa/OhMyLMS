import { del, get, patch, post } from '../../../lib/api';
import { getCollection } from '../../../lib/api/collection';
import {
	RULE_PER_PAGE,
	parseChoices,
	parseLogEntry,
	parseOptions,
	parseRule,
	type ChoiceType,
	type RuleStatus,
} from './model';

const BASE = '/ohmylms/v1/automations';
const route = ( id: number ) =>
	`${ BASE }/${ Math.max( 1, Math.trunc( id ) ) }`;

export interface RuleQuery {
	page: number;
	search: string;
	status: 'any' | RuleStatus;
}

export function listRules( query: RuleQuery, signal?: AbortSignal ) {
	return getCollection( BASE, parseRule, {
		signal,
		query: {
			page: query.page,
			per_page: RULE_PER_PAGE,
			search: query.search || undefined,
			status: query.status,
		},
	} );
}
export async function getRule( id: number, signal?: AbortSignal ) {
	return parseRule( await get< unknown >( route( id ), { signal } ) );
}
export async function getRuleOptions( signal?: AbortSignal ) {
	return parseOptions(
		await get< unknown >( `${ BASE }/options`, { signal } )
	);
}
export async function createRule(
	body: Record< string, unknown >,
	signal?: AbortSignal
) {
	return parseRule( await post< unknown >( BASE, body, { signal } ) );
}
export async function updateRule(
	id: number,
	body: Record< string, unknown >,
	signal?: AbortSignal
) {
	return parseRule( await patch< unknown >( route( id ), body, { signal } ) );
}
export async function deleteRule( id: number, signal?: AbortSignal ) {
	await del< unknown >( route( id ), { signal } );
}
export function listLog(
	page: number,
	status: string,
	ruleId: number,
	signal?: AbortSignal
) {
	return getCollection(
		ruleId ? `${ route( ruleId ) }/log` : `${ BASE }/log`,
		parseLogEntry,
		{ signal, query: { page, per_page: RULE_PER_PAGE, status } }
	);
}
export async function searchChoices(
	type: ChoiceType,
	search: string,
	include: readonly number[] = [],
	signal?: AbortSignal
) {
	return parseChoices(
		await get< unknown >( `${ BASE }/choices`, {
			signal,
			query: {
				type,
				search,
				include: include.length ? include : undefined,
			},
		} )
	);
}
