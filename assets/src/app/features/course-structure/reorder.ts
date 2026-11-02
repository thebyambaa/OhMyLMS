import { __, sprintf } from '@wordpress/i18n';
import { TERMINAL, type Activity, type Outline } from './model';

/**
 * Drag and focus key of a unit; activities use their placement ID.
 * @param id Unit ID.
 */
export const unitKey = ( id: number | string ) => `unit:${ id }`;

/**
 * Final index of a row moved from `from` to before, or after, the row at `target` of the same list.
 * `from` is -1 when the row comes from another list.
 * @param from
 * @param target
 * @param after
 */
export function dropIndex( from: number, target: number, after: boolean ) {
	const at = target + ( after ? 1 : 0 );
	return from >= 0 && from < at ? at - 1 : at;
}

/**
 * Move one row to a final index; an out-of-range or empty move returns the input list.
 * @param rows
 * @param from
 * @param to
 */
export function moveTo< T >( rows: T[], from: number, to: number ): T[] {
	if (
		from === to ||
		from < 0 ||
		to < 0 ||
		from >= rows.length ||
		to >= rows.length
	) {
		return rows;
	}
	const next = [ ...rows ];
	next.splice( to, 0, ...next.splice( from, 1 ) );
	return next;
}

/**
 * Place an activity at `index` among the activities of a unit (its own or another). Terminal assessments
 * stay after the other activities, a change of unit drops the lesson-group link, and every other row
 * keeps its identity and relative order. A move that changes nothing returns the input list.
 * @param items
 * @param id
 * @param unit
 * @param index
 */
export function placeActivity(
	items: Activity[],
	id: string,
	unit: number | string,
	index: number
): Activity[] {
	const current = items.find( ( item ) => item.id === id );
	if ( ! current ) {
		return items;
	}
	const terminal = ( item: Activity ) =>
		TERMINAL.includes( item.style ?? '' );
	const others = items.filter( ( item ) => item !== current );
	const siblings = others.filter( ( item ) => item.chapter_id === unit );
	const open = siblings.filter( ( item ) => ! terminal( item ) ).length;
	const at = terminal( current )
		? Math.min( Math.max( index, open ), siblings.length )
		: Math.min( Math.max( index, 0 ), open );
	if (
		current.chapter_id === unit &&
		items
			.filter( ( item ) => item.chapter_id === unit )
			.indexOf( current ) === at
	) {
		return items;
	}
	let moved = current;
	if ( current.chapter_id !== unit ) {
		moved = { ...current, chapter_id: unit };
		delete moved.group_id;
	}
	let position = others.length;
	if ( siblings[ at ] ) {
		position = others.indexOf( siblings[ at ] );
	} else if ( siblings.length ) {
		position = others.indexOf( siblings[ siblings.length - 1 ]! ) + 1;
	}
	return [
		...others.slice( 0, position ),
		moved,
		...others.slice( position ),
	];
}

/**
 * Apply a drop: a unit onto another unit, or an activity before/after another activity or onto a
 * unit (its end, before terminal assessments). Unknown keys and empty moves return the input outline.
 * @param outline
 * @param source  Unit key or activity ID being moved.
 * @param target  Unit key or activity ID under the pointer.
 * @param after   Dropped on the lower half of the target.
 */
export function dropOn(
	outline: Outline,
	source: string,
	target: string,
	after: boolean
): Outline {
	const { units, program } = outline;
	const unitAt = ( key: string ) =>
		units.findIndex( ( unit ) => unitKey( unit.id ) === key );
	const from = unitAt( source );
	if ( from >= 0 ) {
		const to = unitAt( target );
		const next =
			to < 0
				? units
				: moveTo( units, from, dropIndex( from, to, after ) );
		return next === units ? outline : { ...outline, units: next };
	}
	const row = program.items.find( ( item ) => item.id === target );
	const unit = row ? row.chapter_id : units[ unitAt( target ) ]?.id;
	if ( ! units.some( ( candidate ) => candidate.id === unit ) ) {
		return outline;
	}
	const siblings = program.items.filter(
		( item ) => item.chapter_id === unit
	);
	const index = siblings.findIndex( ( item ) => item.id === source );
	const items = placeActivity(
		program.items,
		source,
		unit!,
		row
			? dropIndex( index, siblings.indexOf( row ), after )
			: dropIndex( index, siblings.length - 1, true )
	);
	return items === program.items
		? outline
		: { ...outline, program: { ...program, items } };
}

/**
 * Screen reader text for the position of a moved unit or activity in an outline.
 * @param outline
 * @param key     Unit key or activity ID.
 */
export function describeMove( outline: Outline, key: string ) {
	const item = outline.program.items.find( ( row ) => row.id === key );
	const unit = outline.units.find( ( row ) =>
		item ? row.id === item.chapter_id : unitKey( row.id ) === key
	);
	if ( ! unit ) {
		return '';
	}
	const title = unit.title || __( 'New unit', 'ohmylms' );
	return item
		? sprintf(
				/* translators: 1: activity name, 2: position from 1, 3: unit title. */
				__( 'Moved %1$s to position %2$d in %3$s.', 'ohmylms' ),
				item.name || String( item.content_id ),
				outline.program.items
					.filter( ( row ) => row.chapter_id === unit.id )
					.indexOf( item ) + 1,
				title
			)
		: sprintf(
				/* translators: 1: unit title, 2: position from 1. */
				__( 'Moved unit %1$s to position %2$d.', 'ohmylms' ),
				title,
				outline.units.indexOf( unit ) + 1
			);
}
