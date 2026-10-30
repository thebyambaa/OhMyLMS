import { del, get, post, put } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

export type MemberType = 'course' | 'curriculum';
export interface Bundle {
	id: number;
	title: string;
	description: string;
	status: 'draft' | 'published';
	member_count: number;
	follower_count: number;
	updated_at: string;
}
export interface Member {
	type: MemberType;
	id: number;
	title: string;
	available: boolean;
}
export interface BundleDetail {
	bundle: Bundle;
	members: Member[];
}
const BASE = '/ohmylms/v1/bundles';
function count( value: unknown ) {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}
function memberType( value: unknown ): MemberType {
	return value === 'course' || value === 'curriculum' ? value : invalid();
}
export function parseBundle( value: unknown ): Bundle {
	const row = record( value );
	if ( row.status !== 'draft' && row.status !== 'published' ) {
		return invalid();
	}
	return {
		id: positiveId( row.id ),
		title: text( row.title ),
		description: text( row.description ),
		status: row.status,
		member_count: count( row.member_count ),
		follower_count: count( row.follower_count ),
		updated_at: text( row.updated_at ),
	};
}
export function parseDetail( value: unknown ): BundleDetail {
	const row = record( value );
	return {
		bundle: parseBundle( row.bundle ),
		members: Array.isArray( row.members )
			? row.members.map( ( item ) => {
					const member = record( item );
					if ( typeof member.available !== 'boolean' ) {
						return invalid();
					}
					return {
						type: memberType( member.type ),
						id: positiveId( member.id ),
						title: text( member.title ),
						available: member.available,
					};
				} )
			: invalid(),
	};
}
export async function listBundles( signal?: AbortSignal ) {
	const row = record( await get< unknown >( BASE, { signal } ) );
	const limits = record( row.limits );
	return {
		bundles: Array.isArray( row.bundles )
			? row.bundles.map( parseBundle )
			: invalid(),
		maxMembers: count( limits.max_members ),
	};
}
export async function readBundle( id: number, signal?: AbortSignal ) {
	return parseDetail(
		await get< unknown >( `${ BASE }/${ id }`, { signal } )
	);
}
export async function createBundle(
	title: string,
	description: string,
	signal?: AbortSignal
) {
	return parseDetail(
		await post< unknown >(
			BASE,
			{ title: title.trim(), description },
			{ signal }
		)
	);
}
export async function updateBundle(
	bundle: Bundle,
	fields: { title: string; description: string },
	signal?: AbortSignal
) {
	return parseDetail(
		await put< unknown >(
			`${ BASE }/${ bundle.id }`,
			{
				title: fields.title.trim(),
				description: fields.description,
				expected_updated_at: bundle.updated_at,
			},
			{ signal }
		)
	);
}
export async function saveMembers(
	bundle: Bundle,
	members: Member[],
	signal?: AbortSignal
) {
	return parseDetail(
		await put< unknown >(
			`${ BASE }/${ bundle.id }/items`,
			{
				items: members.map( ( member ) => ( {
					type: member.type,
					id: member.id,
				} ) ),
				expected_updated_at: bundle.updated_at,
			},
			{ signal }
		)
	);
}
export async function publishBundle(
	bundle: Bundle,
	published: boolean,
	signal?: AbortSignal
) {
	return parseDetail(
		await post< unknown >(
			`${ BASE }/${ bundle.id }/publish`,
			{ published, expected_updated_at: bundle.updated_at },
			{ signal }
		)
	);
}
export async function deleteBundle(
	bundle: Bundle,
	force: boolean,
	signal?: AbortSignal
) {
	const row = record(
		await del< unknown >( `${ BASE }/${ bundle.id }`, {
			signal,
			query: { force, expected_updated_at: bundle.updated_at },
		} )
	);
	if ( row.deleted !== true ) {
		invalid();
	}
}
export async function searchTargets(
	type: MemberType,
	search: string,
	signal?: AbortSignal
): Promise< Member[] > {
	const rows = await get< unknown >( `${ BASE }/targets`, {
		signal,
		query: { type, search },
	} );
	return Array.isArray( rows )
		? rows.map( ( item ) => {
				const row = record( item );
				return {
					type,
					id: positiveId( row.id ),
					title: text( row.title ),
					available: true,
				};
			} )
		: invalid();
}
/**
 * Move one member up or down, keeping the others in order.
 * @param members
 * @param index
 * @param offset
 */
export function moveMember( members: Member[], index: number, offset: -1 | 1 ) {
	const target = index + offset;
	if ( target < 0 || target >= members.length ) {
		return members;
	}
	const next = [ ...members ];
	const [ moved ] = next.splice( index, 1 );
	next.splice( target, 0, moved! );
	return next;
}
