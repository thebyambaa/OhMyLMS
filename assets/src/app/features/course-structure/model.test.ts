import { outline } from './fixtures';
import {
	move,
	moveActivity,
	moveToUnit,
	parseOutline,
	removeNewUnit,
	savePayload,
} from './model';

it( 'round-trips a draft without dropping modes, evidence, groups or skills', () => {
	const parsed = parseOutline( {
		...outline,
		revision: 'old',
		structure_revision: 'fresh',
		units: [
			...outline.units,
			{ id: 0, title: 'Unplaced activities', number: '' },
		],
	} );
	expect( parsed.revision ).toBe( 'fresh' );
	expect( parsed.units ).toEqual( outline.units );
	expect( savePayload( parsed ).program ).toEqual( outline.program );
	expect( savePayload( parsed ) ).not.toHaveProperty( 'activate' );
} );
it( 'accepts a new course without optional groups', () => {
	const { groups, ...program } = outline.program;
	expect( parseOutline( { ...outline, program } ).program.groups ).toEqual(
		[]
	);
} );
it.each( [
	null,
	{ ...outline, revision: '' },
	{ ...outline, course_id: -1 },
	{
		...outline,
		program: {
			...outline.program,
			items: [ { id: 'x', type: 'lesson', content_id: '20' } ],
		},
	},
	{ ...outline, units: [ outline.units[ 0 ], outline.units[ 0 ] ] },
	{ ...outline, program: { ...outline.program, recognize_prior: 'yes' } },
	{
		...outline,
		program: {
			...outline.program,
			outcomes: [ { term_id: 7, target: 'unknown', required: true } ],
		},
	},
] )( 'rejects malformed or ambiguous snapshots', ( value ) => {
	expect( () => parseOutline( value ) ).toThrow();
} );
it( 'moves units without mutating the original or reassigning activity identities', () => {
	const units = move( outline.units, 0, 1 );
	expect( units.map( ( unit ) => unit.id ) ).toEqual( [ 9, 8 ] );
	expect( outline.units.map( ( unit ) => unit.id ) ).toEqual( [ 8, 9 ] );
	expect( savePayload( { ...outline, units } ).program ).toBe(
		outline.program
	);
} );
it( 'blocks blank unit names before sending a mutation', () => {
	expect( () =>
		savePayload( {
			...outline,
			units: [ { ...outline.units[ 0 ]!, title: ' ' } ],
		} )
	).toThrow( 'Every unit needs a title.' );
} );

it( 'moves lessons within their unit without moving terminal assessments', () => {
	const first = outline.program.items[ 0 ]!;
	const second = { ...first, id: 'second', content_id: 21 };
	const test = { ...first, id: 'test', type: 'quiz', style: 'unit-test' };
	const foreign = { ...first, id: 'foreign', chapter_id: 9 };
	const items = [ first, test, foreign, second ];
	expect( moveActivity( items, first.id, 1 ) ).toEqual( [
		second,
		test,
		foreign,
		first,
	] );
	expect( items[ 0 ] ).toBe( first );
} );

it( 'moves an activity to another unit before its terminal assessment and drops the group link', () => {
	const first = outline.program.items[ 0 ]!;
	const other = { ...first, id: 'other', chapter_id: 9, group_id: undefined };
	const test = {
		...first,
		id: 'test',
		type: 'quiz',
		style: 'unit-test',
		chapter_id: 9,
	};
	const moved = moveToUnit( [ first, other, test ], first.id, 9 );
	expect( moved.map( ( item ) => item.id ) ).toEqual( [
		'other',
		'activity-1',
		'test',
	] );
	expect( moved[ 1 ] ).toMatchObject( {
		id: 'activity-1',
		chapter_id: 9,
		content_id: 20,
		skill_ids: [ 7 ],
	} );
	expect( moved[ 1 ] ).not.toHaveProperty( 'group_id' );
	expect( moveToUnit( [ first ], first.id, 8 )[ 0 ] ).toBe( first );
	expect(
		moveToUnit( [ first, other ], first.id, 'new-unit' ).map(
			( item ) => item.id
		)
	).toEqual( [ 'other', 'activity-1' ] );
} );

it( 'removes an unsaved unit and keeps its activities and targets unplaced', () => {
	const draft = {
		...outline,
		units: [
			...outline.units,
			{ id: 'new-unit', title: 'New', number: '', description: '' },
		],
		program: {
			...outline.program,
			items: [
				...outline.program.items,
				{
					id: 'placed',
					type: 'quiz',
					content_id: 30,
					chapter_id: 'new-unit',
				},
			],
			outcomes: [
				...outline.program.outcomes,
				{
					term_id: 8,
					chapter_id: 'new-unit',
					target: 'proficient',
					required: false,
				},
			],
		},
	};
	const result = removeNewUnit( draft, 'new-unit' );
	expect( result.units ).toEqual( outline.units );
	expect( result.program.items[ 1 ] ).toMatchObject( {
		id: 'placed',
		chapter_id: 0,
	} );
	expect( result.program.outcomes[ 1 ] ).toMatchObject( {
		term_id: 8,
		chapter_id: 0,
	} );
	expect( result.program.items[ 0 ] ).toBe( outline.program.items[ 0 ] );
} );
