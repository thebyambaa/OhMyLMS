import { outline, report } from './fixtures';
import { parseOutline, parseReport } from './model';

it( 'validates a full outline and reads course relationships without side effects', () => {
	expect( parseOutline( outline ) ).toEqual( outline );
	expect( parseOutline( { ...outline, course: null } ).course ).toBeNull();
} );
it.each( [
	null,
	{ ...outline, contents: {} },
	{ ...outline, course: { id: 12, title: 'Math', published: 'yes' } },
	{ ...outline, contents: [ { ...outline.contents[ 0 ], groups: 'bad' } ] },
] )( 'rejects invalid outlines', ( raw ) => {
	expect( () => parseOutline( raw ) ).toThrow();
} );
it( 'requires typed preview results before offering an import', () => {
	expect( parseReport( report ) ).toMatchObject( {
		valid: true,
		applied: false,
		used: 1,
	} );
	expect( () => parseReport( { ...report, valid: 'true' } ) ).toThrow();
	expect( () =>
		parseReport( { ...report, skills: { create: -1, update: 0 } } )
	).toThrow();
} );
