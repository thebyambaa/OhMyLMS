import { get } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

export interface MapSubject {
	subject: string;
	skills: number;
	syllabuses: { id: number; name: string; code: string }[];
}
export interface MapSkill {
	id: number;
	name: string;
	code: string;
	domain: string;
	grade_from: number;
	grade_to: number;
	prerequisites: number[];
}
export interface MapIssue {
	type: 'later_prerequisite' | 'long_gap' | 'no_prerequisites';
	skill: number;
	other: number;
}
export interface SkillMap {
	subject: string;
	domains: { id: string; label: string }[];
	grades: { value: number; label: string }[];
	skills: MapSkill[];
	unplaced: number[];
	issues: MapIssue[];
	syllabus: {
		id: number;
		name: string;
		objectives: number;
		unmapped: number;
		covered: number[];
	} | null;
}
const BASE = '/ohmylms/v1/syllabus-comparison';
function count( value: unknown ) {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}
function list< T >( value: unknown, parse: ( row: unknown ) => T ): T[] {
	return Array.isArray( value ) ? value.map( parse ) : invalid();
}
export function parseSubjects( value: unknown ): MapSubject[] {
	return list( value, ( item ) => {
		const row = record( item );
		return {
			subject: text( row.subject ),
			skills: count( row.skills ),
			syllabuses: list( row.syllabuses, ( entry ) => {
				const syllabus = record( entry );
				return {
					id: positiveId( syllabus.id ),
					name: text( syllabus.name ),
					code:
						typeof syllabus.code === 'string' ? syllabus.code : '',
				};
			} ),
		};
	} );
}
const ISSUES = [ 'later_prerequisite', 'long_gap', 'no_prerequisites' ];
export function parseMap( value: unknown ): SkillMap {
	const row = record( value );
	const overlay = row.syllabus === null ? null : record( row.syllabus );
	return {
		subject: text( row.subject ),
		domains: list( row.domains, ( item ) => {
			const domain = record( item );
			return { id: text( domain.id ), label: text( domain.label ) };
		} ),
		grades: list( row.grades, ( item ) => {
			const grade = record( item );
			return { value: count( grade.value ), label: text( grade.label ) };
		} ),
		skills: list( row.skills, ( item ) => {
			const skill = record( item );
			return {
				id: positiveId( skill.id ),
				name: text( skill.name ),
				code: text( skill.code ),
				domain: text( skill.domain ),
				grade_from: count( skill.grade_from ),
				grade_to: count( skill.grade_to ),
				prerequisites: list( skill.prerequisites, positiveId ),
			};
		} ),
		unplaced: list( row.unplaced, positiveId ),
		issues: list( row.issues, ( item ) => {
			const issue = record( item );
			if ( ! ISSUES.includes( text( issue.type ) ) ) {
				return invalid();
			}
			return {
				type: issue.type as MapIssue[ 'type' ],
				skill: positiveId( issue.skill ),
				other: count( issue.other ),
			};
		} ),
		syllabus: overlay && {
			id: positiveId( overlay.id ),
			name: text( overlay.name ),
			objectives: count( overlay.objectives ),
			unmapped: count( overlay.unmapped ),
			covered: Object.keys( record( overlay.covered ) ).map( ( id ) =>
				positiveId( Number( id ) )
			),
		},
	};
}
export async function readSubjects( signal?: AbortSignal ) {
	return parseSubjects(
		await get< unknown >( `${ BASE }/skill-map/subjects`, { signal } )
	);
}
export async function readMap(
	subject: string,
	syllabus: number,
	signal?: AbortSignal
) {
	return parseMap(
		await get< unknown >( `${ BASE }/skill-map`, {
			signal,
			query: { subject, syllabus_id: syllabus },
		} )
	);
}
/**
 * Skills that start in each domain and level cell, keyed `domain:grade`.
 * @param map
 */
export function cells( map: SkillMap ) {
	const result = new Map< string, MapSkill[] >();
	for ( const skill of map.skills ) {
		if ( ! skill.domain || skill.grade_from < 1 ) {
			continue;
		}
		const key = `${ skill.domain }:${ skill.grade_from }`;
		result.set( key, [ ...( result.get( key ) ?? [] ), skill ] );
	}
	return result;
}
