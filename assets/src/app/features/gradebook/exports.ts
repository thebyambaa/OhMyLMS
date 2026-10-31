import { __ } from '@wordpress/i18n';
import { cellCsv, toCsv } from './model';
import type {
	CourseGrid,
	MasteryView,
	RollupNode,
	RosterView,
	Transcript,
} from './types';

/**
 * CSV of Course × Students: learner, status, source, rosters, total, letter, then one column per item.
 *
 * @param grid Grid with every matching row (export request).
 * @return CSV.
 */
export function courseCsv( grid: CourseGrid ): string {
	return toCsv( [
		[
			__( 'Learner', 'ohmylms' ),
			__( 'Enrolment status', 'ohmylms' ),
			__( 'Source', 'ohmylms' ),
			__( 'Rosters', 'ohmylms' ),
			__( 'Total %', 'ohmylms' ),
			__( 'Letter', 'ohmylms' ),
			__( 'Computed total %', 'ohmylms' ),
			...grid.items.map(
				( item ) =>
					`${ item.title } (${ item.category }, /${ item.max })`
			),
		],
		...grid.rows.map( ( row ) => [
			row.name,
			row.status,
			row.sources.join( '|' ),
			row.rosters.map( ( roster ) => roster.name ).join( '|' ),
			row.total.percent,
			row.total.letter,
			row.total.computed_percent,
			...grid.items.map( ( item ) =>
				cellCsv( row.cells[ String( item.id ) ] )
			),
		] ),
	] );
}

/**
 * CSV of Roster × Assignments.
 *
 * @param view Roster view.
 * @return CSV.
 */
export function rosterCsv( view: RosterView ): string {
	const columns = view.courses.flatMap( ( course ) => [
		{
			key: `total:${ course.id }`,
			label: `${ course.title }: ${ __( 'total %', 'ohmylms' ) }`,
			course: course.id,
			item: 0,
		},
		...course.items.map( ( item ) => ( {
			key: `${ course.id }:${ item.id }`,
			label: `${ course.title }: ${ item.title }`,
			course: course.id,
			item: item.id,
		} ) ),
	] );
	return toCsv( [
		[
			__( 'Learner', 'ohmylms' ),
			...columns.map( ( column ) => column.label ),
		],
		...view.learners.map( ( learner ) => [
			learner.name,
			...columns.map( ( column ) => {
				const total = learner.totals[ String( column.course ) ];
				if ( ! column.item ) {
					return total
						? total.percent
						: __( 'not enrolled', 'ohmylms' );
				}
				return total
					? cellCsv( learner.cells[ column.key ] ?? null )
					: __( 'not enrolled', 'ohmylms' );
			} ),
		] ),
	] );
}

/**
 * CSV of a transcript: one line per course item, plus a total line per course and the skills.
 *
 * @param transcript Transcript.
 * @return CSV.
 */
export function transcriptCsv( transcript: Transcript ): string {
	const rows: ( string | number | null )[][] = [
		[
			__( 'Course', 'ohmylms' ),
			__( 'Item', 'ohmylms' ),
			__( 'Category', 'ohmylms' ),
			__( 'Result', 'ohmylms' ),
		],
	];
	for ( const enrolment of transcript.enrolments ) {
		for ( const item of enrolment.items ) {
			rows.push( [
				enrolment.course.title,
				item.title,
				item.category,
				cellCsv( enrolment.row.cells[ String( item.id ) ] ),
			] );
		}
		rows.push( [
			enrolment.course.title,
			__( 'Total', 'ohmylms' ),
			'',
			enrolment.row.total.percent,
		] );
	}
	for ( const skill of transcript.mastery.skills ) {
		rows.push( [
			__( 'Skill', 'ohmylms' ),
			skill.name,
			skill.level,
			skill.percent,
		] );
	}
	return toCsv( rows );
}

/**
 * CSV of Skill mastery (percentages; empty without evidence).
 *
 * @param view Mastery view.
 * @return CSV.
 */
export function masteryCsv( view: MasteryView ): string {
	return toCsv( [
		[
			__( 'Learner', 'ohmylms' ),
			...view.skills.map( ( skill ) => skill.name ),
		],
		...view.learners.map( ( learner ) => [
			learner.name,
			...view.skills.map(
				( skill ) => learner.cells[ String( skill.id ) ]?.percent ?? ''
			),
		] ),
	] );
}

/**
 * CSV of the roll-up (one line per node, indented by level).
 *
 * @param nodes Nodes.
 * @return CSV.
 */
export function rollupCsv( nodes: readonly RollupNode[] ): string {
	const rows: ( string | number | null )[][] = [
		[
			__( 'Level', 'ohmylms' ),
			__( 'Name', 'ohmylms' ),
			__( 'Learners', 'ohmylms' ),
			__( 'Enrolments', 'ohmylms' ),
			__( 'Average total %', 'ohmylms' ),
			__( 'Learners with missing work', 'ohmylms' ),
			__( 'Waiting for grading', 'ohmylms' ),
		],
	];
	const walk = ( list: readonly RollupNode[] ) => {
		for ( const node of list ) {
			rows.push( [
				node.type,
				node.name,
				node.learners,
				node.enrolments,
				node.average,
				node.missing_learners,
				node.pending,
			] );
			walk( node.children );
		}
	};
	walk( nodes );
	return toCsv( rows );
}
