import { __, sprintf } from '@wordpress/i18n';
import type {
	Cell,
	CellState,
	CellValue,
	ItemKind,
	ScaleStep,
	Section,
	Weights,
} from './types';

/** Address parameters of the gradebook screens (the route query; `page` is `paged` in the address). */
export interface GradebookQuery {
	scope: string;
	course: number;
	audience: string;
	status: string;
	type: string;
	module: number;
	search: string;
	orderby: 'name' | 'total' | 'source';
	order: 'asc' | 'desc';
	page: number;
	classId: number;
	learner: number;
}

const ORDERS = [ 'name', 'total', 'source' ] as const;
const AUDIENCE = /^(all|individuals|class:[1-9][0-9]{0,18})$/;
const SCOPE = /^(all|school:[1-9][0-9]{0,18}|class:[1-9][0-9]{0,18})$/;
const CATEGORY = /^[a-z0-9][a-z0-9_-]{0,39}$/;
export const STATUS_FILTERS = [
	'missing',
	'pending',
	'overridden',
	'excused',
	'active',
	'completed',
	'suspended',
] as const;

function positive( value: string | null ): number {
	const number = Number( value );
	return Number.isInteger( number ) && number > 0 ? number : 0;
}

/**
 * Read the gradebook query from the route's search parameters; unknown or malformed values fall back
 * to their defaults (the server validates again).
 *
 * @param params Search parameters.
 * @return Query.
 */
export function queryFromParams( params: URLSearchParams ): GradebookQuery {
	const scope = params.get( 'scope' ) ?? '';
	const audience = params.get( 'audience' ) ?? 'all';
	const status = params.get( 'status' ) ?? '';
	const type = params.get( 'type' ) ?? '';
	const orderby = params.get( 'orderby' ) ?? 'name';
	return {
		scope: SCOPE.test( scope ) ? scope : '',
		course: positive( params.get( 'course' ) ),
		audience: AUDIENCE.test( audience ) ? audience : 'all',
		status: ( STATUS_FILTERS as readonly string[] ).includes( status )
			? status
			: '',
		type: CATEGORY.test( type ) ? type : '',
		module: positive( params.get( 'module' ) ),
		search: ( params.get( 's' ) ?? '' ).slice( 0, 200 ),
		orderby: ( ORDERS as readonly string[] ).includes( orderby )
			? ( orderby as GradebookQuery[ 'orderby' ] )
			: 'name',
		order: 'desc' === params.get( 'order' ) ? 'desc' : 'asc',
		page: Math.max( 1, positive( params.get( 'page' ) ) ),
		classId: positive( params.get( 'class' ) ),
		learner: positive( params.get( 'learner' ) ),
	};
}

/**
 * The search parameters of a query; defaults are left out.
 *
 * @param query Query.
 * @return Search parameters.
 */
export function paramsFromQuery( query: GradebookQuery ): URLSearchParams {
	const params = new URLSearchParams();
	const set = ( name: string, value: string | number, fallback: unknown ) => {
		if ( value !== fallback && '' !== value && 0 !== value ) {
			params.set( name, String( value ) );
		}
	};
	set( 'scope', query.scope, '' );
	set( 'course', query.course, 0 );
	set( 'class', query.classId, 0 );
	set( 'learner', query.learner, 0 );
	set( 'audience', query.audience, 'all' );
	set( 'status', query.status, '' );
	set( 'type', query.type, '' );
	set( 'module', query.module, 0 );
	set( 's', query.search, '' );
	set( 'orderby', query.orderby, 'name' );
	set( 'order', query.order, 'asc' );
	set( 'page', query.page, 1 );
	return params;
}

/**
 * Format a percentage with at most one decimal.
 *
 * @param value Percentage or null.
 * @return Text such as `79.5%`, or an em dash.
 */
export function formatPercent( value: number | null | undefined ): string {
	if ( null === value || undefined === value || ! Number.isFinite( value ) ) {
		return '—';
	}
	return `${ Math.round( value * 10 ) / 10 }%`;
}

/**
 * Format marks (up to two decimals).
 *
 * @param value Marks.
 * @return Text.
 */
export function formatMarks( value: number | null | undefined ): string {
	if ( null === value || undefined === value || ! Number.isFinite( value ) ) {
		return '—';
	}
	return String( Math.round( value * 100 ) / 100 );
}

/**
 * Human name of a cell state.
 *
 * @param state State.
 * @return Label.
 */
export function stateLabel( state: CellState ): string {
	switch ( state ) {
		case 'score':
			return __( 'Graded', 'ohmylms' );
		case 'pending':
			return __( 'To grade', 'ohmylms' );
		case 'missing':
			return __( 'Missing', 'ohmylms' );
		case 'none':
			return __( 'Not submitted', 'ohmylms' );
		case 'not_assigned':
			return __( 'Not assigned', 'ohmylms' );
		case 'excused':
			return __( 'Excused', 'ohmylms' );
	}
}

/**
 * Short text shown in a grid cell.
 *
 * @param cell Cell value.
 * @return Text.
 */
export function cellText( cell: CellValue | null | undefined ): string {
	if ( ! cell ) {
		return '·';
	}
	switch ( cell.state ) {
		case 'score':
			return formatPercent( cell.percent );
		case 'pending':
			return __( 'to grade', 'ohmylms' );
		case 'missing':
			return __( 'missing', 'ohmylms' );
		case 'excused':
			return __( 'excused', 'ohmylms' );
		case 'not_assigned':
			return '—';
		case 'none':
			return '–';
	}
}

/**
 * Accessible description of a cell, with the computed value when an override replaces it.
 *
 * @param cell Cell.
 * @return Sentence.
 */
export function cellDescription( cell: Cell | null | undefined ): string {
	if ( ! cell ) {
		return __( 'Not enrolled in this course', 'ohmylms' );
	}
	const value =
		'score' === cell.state
			? sprintf(
					/* translators: 1: percentage, 2: score, 3: maximum. */
					__( '%1$s (%2$s of %3$s)', 'ohmylms' ),
					formatPercent( cell.percent ),
					formatMarks( cell.score ),
					formatMarks( cell.max )
				)
			: stateLabel( cell.state );
	if ( ! cell.overridden ) {
		return value;
	}
	return sprintf(
		/* translators: 1: the manual value, 2: the computed value. */
		__( '%1$s, set manually; computed: %2$s', 'ohmylms' ),
		value,
		'score' === cell.computed.state
			? formatPercent( cell.computed.percent )
			: stateLabel( cell.computed.state )
	);
}

/** Visual band of a cell (a class name suffix; every band also differs in text). */
export type CellTone =
	| 'high'
	| 'good'
	| 'fair'
	| 'low'
	| 'pending'
	| 'missing'
	| 'none'
	| 'not-assigned'
	| 'excused'
	| 'not-enrolled';

/**
 * Band of a cell: scores by percentage (90+, 70+, 60+, below), then the other states.
 *
 * @param cell Cell value.
 * @return Band.
 */
export function cellTone( cell: CellValue | null | undefined ): CellTone {
	if ( ! cell ) {
		return 'not-enrolled';
	}
	if ( 'score' === cell.state ) {
		const percent = cell.percent ?? 0;
		if ( percent >= 90 ) {
			return 'high';
		}
		if ( percent >= 70 ) {
			return 'good';
		}
		return percent >= 60 ? 'fair' : 'low';
	}
	return 'not_assigned' === cell.state
		? 'not-assigned'
		: ( cell.state as CellTone );
}

/**
 * Letter of a percentage on the site's scale.
 *
 * @param percent Percentage.
 * @param scale   Scale, highest first.
 * @return Letter or null.
 */
export function letterFor(
	percent: number | null,
	scale: readonly ScaleStep[]
): string | null {
	if ( null === percent ) {
		return null;
	}
	return scale.find( ( step ) => percent >= step.min )?.letter ?? null;
}

/**
 * `homework 30% · quiz 30% · test 40%`, or "total points" without weights.
 *
 * @param weights Weights.
 * @return Text.
 */
export function weightsText( weights: Weights ): string {
	const entries = Object.entries( weights );
	if ( ! entries.length ) {
		return __( 'total points (no category weights)', 'ohmylms' );
	}
	return entries
		.map( ( [ category, weight ] ) => `${ category } ${ weight }%` )
		.join( ' · ' );
}

/**
 * Validate weights like the server: slug keys, 0-100 with two decimals, summing to 100 (or none).
 *
 * @param weights Draft weights.
 * @return Error message or ''.
 */
export function weightsError( weights: Weights ): string {
	const entries = Object.entries( weights );
	if ( entries.length > 12 ) {
		return __( 'Use at most 12 categories.', 'ohmylms' );
	}
	for ( const [ key, value ] of entries ) {
		if ( ! CATEGORY.test( key ) ) {
			return __(
				'Category names use lowercase letters, digits, hyphens and underscores.',
				'ohmylms'
			);
		}
		if (
			! Number.isFinite( value ) ||
			value < 0 ||
			value > 100 ||
			Math.abs( Math.round( value * 100 ) / 100 - value ) > 1e-9
		) {
			return __(
				'Each weight is a percentage from 0 to 100 with at most two decimals.',
				'ohmylms'
			);
		}
	}
	const sum = entries.reduce( ( total, [ , value ] ) => total + value, 0 );
	if ( entries.length && Math.abs( sum - 100 ) > 0.001 ) {
		return sprintf(
			/* translators: %s: the sum of the weights. */
			__(
				'The weights add up to %s%%; they must add up to 100%%.',
				'ohmylms'
			),
			String( Math.round( sum * 100 ) / 100 )
		);
	}
	return '';
}

/**
 * Name of an item kind.
 *
 * @param kind Kind.
 * @return Label.
 */
export function kindLabel( kind: ItemKind ): string {
	switch ( kind ) {
		case 'quiz':
			return __( 'Quiz', 'ohmylms' );
		case 'exam':
			return __( 'Exam', 'ohmylms' );
		case 'assignment':
			return __( 'Assignment', 'ohmylms' );
	}
}

/**
 * Name of an enrolment source.
 *
 * @param source Source.
 * @return Label.
 */
export function sourceLabel( source: string ): string {
	const labels: Record< string, string > = {
		purchase: __( 'purchase', 'ohmylms' ),
		self: __( 'self', 'ohmylms' ),
		manual: __( 'manual', 'ohmylms' ),
		roster: __( 'roster', 'ohmylms' ),
		seat: __( 'seat', 'ohmylms' ),
		import: __( 'import', 'ohmylms' ),
		unknown: __( 'unknown', 'ohmylms' ),
	};
	return labels[ source ] ?? source;
}

/** Sources shown in the legend, in order. */
export const LEGEND_SOURCES = [
	'purchase',
	'self',
	'manual',
	'roster',
	'seat',
] as const;

/**
 * Name of a section (view).
 * @param section
 */
export function sectionLabel( section: Section ): string {
	switch ( section ) {
		case 'course':
			return __( 'Course × Students', 'ohmylms' );
		case 'roster':
			return __( 'Roster × Assignments', 'ohmylms' );
		case 'student':
			return __( 'Student transcript', 'ohmylms' );
		case 'mastery':
			return __( 'Skill mastery', 'ohmylms' );
		case 'rollup':
			return __( 'Org roll-up', 'ohmylms' );
	}
}

export interface GridPosition {
	row: number;
	col: number;
}

export interface GridKey {
	key: string;
	ctrlKey?: boolean;
	metaKey?: boolean;
}

/** Rows a Page Up / Page Down moves. */
export const PAGE_ROWS = 10;

/**
 * Next focused cell for a key in a data grid (WAI-ARIA grid pattern): arrows move one cell, Home/End go
 * to the first/last cell of the row (with Ctrl: of the grid), Page Up/Down move ten rows. Null when the
 * key does not move focus.
 *
 * @param position  Current cell.
 * @param event     Key and modifiers.
 * @param size      Rows and columns (including header row 0 and header column 0).
 * @param size.rows Rows.
 * @param size.cols Columns.
 * @return New position or null.
 */
export function moveFocus(
	position: GridPosition,
	event: GridKey,
	size: { rows: number; cols: number }
): GridPosition | null {
	const clamp = ( value: number, max: number ) =>
		Math.max( 0, Math.min( max - 1, value ) );
	const ctrl = Boolean( event.ctrlKey || event.metaKey );
	let { row, col } = position;
	switch ( event.key ) {
		case 'ArrowRight':
			col += 1;
			break;
		case 'ArrowLeft':
			col -= 1;
			break;
		case 'ArrowDown':
			row += 1;
			break;
		case 'ArrowUp':
			row -= 1;
			break;
		case 'Home':
			col = 0;
			if ( ctrl ) {
				row = 0;
			}
			break;
		case 'End':
			col = size.cols - 1;
			if ( ctrl ) {
				row = size.rows - 1;
			}
			break;
		case 'PageDown':
			row += PAGE_ROWS;
			break;
		case 'PageUp':
			row -= PAGE_ROWS;
			break;
		default:
			return null;
	}
	return { row: clamp( row, size.rows ), col: clamp( col, size.cols ) };
}

/**
 * One CSV field: quoted, quotes doubled, and a leading `=`, `+`, `-`, `@`, tab or carriage return made
 * inert with an apostrophe (spreadsheet formula injection).
 *
 * @param value Value.
 * @return Field.
 */
export function csvField( value: string | number | null | undefined ): string {
	let text = null === value || undefined === value ? '' : String( value );
	if ( 'string' === typeof value && /^[=+\-@\t\r]/.test( text ) ) {
		text = `'${ text }`;
	}
	return `"${ text.replace( /"/g, '""' ) }"`;
}

/**
 * CSV text of rows (CRLF line ends, as spreadsheets expect).
 *
 * @param rows Rows of fields.
 * @return CSV.
 */
export function toCsv(
	rows: ReadonlyArray< ReadonlyArray< string | number | null | undefined > >
): string {
	return rows
		.map( ( row ) => row.map( csvField ).join( ',' ) )
		.join( '\r\n' );
}

/**
 * Value of a cell in an export: the percentage, or the state's name.
 *
 * @param cell Cell or null (not enrolled).
 * @return Field.
 */
export function cellCsv( cell: CellValue | null | undefined ): string | number {
	if ( ! cell ) {
		return __( 'not enrolled', 'ohmylms' );
	}
	if ( 'score' === cell.state && null !== cell.percent ) {
		return cell.percent;
	}
	return stateLabel( cell.state ).toLowerCase();
}

/**
 * File-name safe slug.
 *
 * @param text Text.
 * @return Slug (at most 60 characters).
 */
export function fileSlug( text: string ): string {
	return (
		text
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' )
			.slice( 0, 60 ) || 'gradebook'
	);
}

/**
 * Offer a CSV file for download.
 *
 * @param name File name without extension.
 * @param csv  CSV text.
 */
export function downloadCsv( name: string, csv: string ) {
	const blob = new Blob( [ '\ufeff' + csv ], {
		type: 'text/csv;charset=utf-8',
	} );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = `${ fileSlug( name ) }.csv`;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.setTimeout( () => URL.revokeObjectURL( url ), 0 );
}

/**
 * Group scopes for a select: "All schools" first, then one group per school with its classes, then
 * classes outside the user's schools.
 *
 * @param scopes Scopes in picker order.
 * @return Top options and groups.
 */
export function groupScopes(
	scopes: ReadonlyArray< {
		id: string;
		type: string;
		label: string;
		school_id: number;
		school_name: string;
	} >
) {
	const top: { value: string; label: string }[] = [];
	const groups: {
		label: string;
		options: { value: string; label: string }[];
	}[] = [];
	for ( const scope of scopes ) {
		if ( 'all' === scope.type ) {
			top.push( { value: scope.id, label: scope.label } );
			continue;
		}
		const label =
			'school' === scope.type
				? scope.label
				: scope.school_name || __( 'Independent classes', 'ohmylms' );
		let group = groups.find( ( candidate ) => candidate.label === label );
		if ( ! group ) {
			group = { label, options: [] };
			groups.push( group );
		}
		group.options.push( {
			value: scope.id,
			label:
				'school' === scope.type
					? sprintf(
							/* translators: %s: school name. */
							__( 'All of %s', 'ohmylms' ),
							scope.label
						)
					: scope.label,
		} );
	}
	return { top, groups };
}
