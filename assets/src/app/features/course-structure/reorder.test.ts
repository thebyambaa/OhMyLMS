import { outline as base } from './fixtures';
import { TERMINAL, type Activity, type Outline } from './model';
import {
	describeMove,
	dropIndex,
	dropOn,
	moveTo,
	placeActivity,
	unitKey,
} from './reorder';

const lesson = base.program.items[ 0 ]!;
const row = ( id: string, chapter: number, extra: Partial< Activity > = {} ) =>
	( {
		id,
		type: 'lesson',
		content_id: id.length * 10 + chapter,
		chapter_id: chapter,
		required: true,
		name: id,
		...extra,
	} ) as Activity;
/** Test data only: unit 8 ends with a unit test; the course challenge is unplaced. */
const outline: Outline = {
	...base,
	units: [
		...base.units,
		{ id: 10, title: 'Trigonometry', number: '3', description: '' },
	],
	program: {
		...base.program,
		items: [
			lesson,
			row( 'a2', 8, { type: 'quiz' } ),
			row( 't8', 8, { type: 'quiz', style: 'unit-test' } ),
			row( 'b1', 9 ),
			row( 'b2', 9, { type: 'assignment' } ),
			row( 'challenge', 0, { type: 'quiz', style: 'course-challenge' } ),
		],
	},
};
const ids = ( value: Outline, unit: number ) =>
	value.program.items
		.filter( ( item ) => item.chapter_id === unit )
		.map( ( item ) => item.id );

describe( 'dropIndex', () => {
	it.each( [
		[ 0, 2, false, 1 ],
		[ 0, 2, true, 2 ],
		[ 3, 1, false, 1 ],
		[ 3, 1, true, 2 ],
		[ -1, 0, false, 0 ],
		[ -1, 1, true, 2 ],
	] )( 'from %i onto %i (after %s) ends at %i', ( from, target, after, to ) =>
		expect( dropIndex( from, target, after ) ).toBe( to )
	);
} );

describe( 'moveTo', () => {
	it( 'moves one row without mutating the input', () => {
		const rows = [ 'a', 'b', 'c', 'd' ];
		expect( moveTo( rows, 0, 2 ) ).toEqual( [ 'b', 'c', 'a', 'd' ] );
		expect( moveTo( rows, 3, 0 ) ).toEqual( [ 'd', 'a', 'b', 'c' ] );
		expect( rows ).toEqual( [ 'a', 'b', 'c', 'd' ] );
	} );
	it.each( [
		[ 1, 1 ],
		[ -1, 0 ],
		[ 0, 4 ],
	] )( 'returns the input for %i → %i', ( from, to ) => {
		const rows = [ 'a', 'b', 'c', 'd' ];
		expect( moveTo( rows, from, to ) ).toBe( rows );
	} );
} );

describe( 'dropOn', () => {
	it( 'reorders units and keeps every activity placement', () => {
		const next = dropOn( outline, unitKey( 8 ), unitKey( 10 ), true );
		expect( next.units.map( ( unit ) => unit.id ) ).toEqual( [ 9, 10, 8 ] );
		expect( next.program ).toBe( outline.program );
		expect(
			dropOn( outline, unitKey( 10 ), unitKey( 8 ), false ).units.map(
				( unit ) => unit.id
			)
		).toEqual( [ 10, 8, 9 ] );
	} );
	it( 'moves an activity within its unit, before or after a sibling', () => {
		expect( ids( dropOn( outline, 'activity-1', 'a2', true ), 8 ) ).toEqual(
			[ 'a2', 'activity-1', 't8' ]
		);
		expect(
			ids( dropOn( outline, 'a2', 'activity-1', false ), 8 )
		).toEqual( [ 'a2', 'activity-1', 't8' ] );
		expect( ids( dropOn( outline, 'b2', 'b1', false ), 9 ) ).toEqual( [
			'b2',
			'b1',
		] );
	} );
	it( 'moves an activity between units, keeping identity and skills but not the group', () => {
		const next = dropOn( outline, 'activity-1', 'b2', false );
		expect( ids( next, 8 ) ).toEqual( [ 'a2', 't8' ] );
		expect( ids( next, 9 ) ).toEqual( [ 'b1', 'activity-1', 'b2' ] );
		const moved = next.program.items.find(
			( item ) => item.id === 'activity-1'
		);
		expect( moved ).toMatchObject( {
			chapter_id: 9,
			content_id: 20,
			skill_ids: [ 7 ],
		} );
		expect( moved ).not.toHaveProperty( 'group_id' );
		expect( next.program.outcomes ).toBe( outline.program.outcomes );
		expect( next.program.groups ).toBe( outline.program.groups );
	} );
	it( 'drops onto a unit at its end, before its unit test', () => {
		expect( ids( dropOn( outline, 'b1', unitKey( 8 ), true ), 8 ) ).toEqual(
			[ 'activity-1', 'a2', 'b1', 't8' ]
		);
		expect(
			ids( dropOn( outline, 'activity-1', unitKey( 10 ), false ), 10 )
		).toEqual( [ 'activity-1' ] );
		expect( ids( dropOn( outline, 'b1', 't8', true ), 8 ) ).toEqual( [
			'activity-1',
			'a2',
			'b1',
			't8',
		] );
	} );
	it( 'keeps a unit test last in its unit', () => {
		expect( dropOn( outline, 't8', 'activity-1', false ) ).toBe( outline );
		expect( ids( dropOn( outline, 't8', 'b1', false ), 9 ) ).toEqual( [
			'b1',
			'b2',
			't8',
		] );
	} );
	it.each( [
		[ 'missing', 'b1' ],
		[ 'b1', 'missing' ],
		[ unitKey( 8 ), 'missing' ],
		[ 'b1', 'challenge' ],
		[ 'activity-1', 'activity-1' ],
		[ unitKey( 9 ), unitKey( 9 ) ],
	] )( 'returns the same outline for %s onto %s', ( source, target ) => {
		expect( dropOn( outline, source, target, true ) ).toBe( outline );
	} );
	it( 'keeps outline invariants for every source, target and side', () => {
		const frozen = JSON.stringify( outline );
		const keys = [
			...outline.units.map( ( unit ) => unitKey( unit.id ) ),
			...outline.program.items.map( ( item ) => item.id ),
		];
		const sorted = ( value: Outline ) =>
			value.program.items.map( ( item ) => item.id ).sort();
		for ( const source of keys ) {
			for ( const target of keys ) {
				for ( const after of [ false, true ] ) {
					const next = dropOn( outline, source, target, after );
					expect( sorted( next ) ).toEqual( sorted( outline ) );
					expect(
						next.units.map( ( unit ) => String( unit.id ) ).sort()
					).toEqual( [ '10', '8', '9' ] );
					for ( const unit of next.units ) {
						const styles = next.program.items
							.filter( ( item ) => item.chapter_id === unit.id )
							.map( ( item ) =>
								TERMINAL.includes( item.style ?? '' )
							);
						expect( styles ).toEqual( [ ...styles ].sort() );
					}
					for ( const item of next.program.items ) {
						const before = outline.program.items.find(
							( candidate ) => candidate.id === item.id
						)!;
						if ( item.id !== source ) {
							expect( item ).toBe( before );
						} else if ( item.chapter_id === before.chapter_id ) {
							expect( item ).toBe( before );
						} else {
							expect( item ).toEqual( {
								...before,
								chapter_id: item.chapter_id,
								group_id: undefined,
							} );
						}
					}
				}
			}
		}
		expect( JSON.stringify( outline ) ).toBe( frozen );
	} );
} );

it( 'places a new activity at the end of a unit, before its unit test', () => {
	const added = row( 'new', 0 );
	const items = placeActivity(
		[ ...outline.program.items, added ],
		'new',
		8,
		Infinity
	);
	expect(
		ids( { ...outline, program: { ...outline.program, items } }, 8 )
	).toEqual( [ 'activity-1', 'a2', 'new', 't8' ] );
} );

it( 'describes the new position of a moved activity or unit', () => {
	expect(
		describeMove(
			dropOn( outline, 'activity-1', 'b2', true ),
			'activity-1'
		)
	).toBe( 'Moved First lesson to position 3 in Geometry.' );
	expect(
		describeMove(
			dropOn( outline, unitKey( 8 ), unitKey( 9 ), true ),
			unitKey( 8 )
		)
	).toBe( 'Moved unit Algebra to position 2.' );
	expect( describeMove( outline, 'challenge' ) ).toBe( '' );
} );
