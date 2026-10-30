import { moved, parseAudit, parseGroups, sharedSkillProblem } from './compare';

describe( 'compare model', () => {
	it( 'accepts an empty PHP group map and requires a revision', () => {
		const body = { items: [], selected: [], revision: 'abcdef0123456789' };
		expect( parseGroups( body ).selected ).toEqual( {} );
		expect( () => parseGroups( { ...body, revision: 'x' } ) ).toThrow();
		expect( () =>
			parseGroups( { ...body, selected: { Math: [ 'x' ] } } )
		).toThrow();
	} );

	it( 'reads source folder paths as name lists', () => {
		const source = {
			id: 1,
			name: 'Digital SAT Mathematics',
			code: 'DSAT-MATH',
			version: 'v4',
			item_type: 'syllabus',
		};
		const body = {
			items: [
				{ ...source, path: [] },
				{ ...source, id: 2, path: [ 'Exams', 'SAT' ] },
			],
			selected: [],
			revision: 'abcdef0123456789',
		};
		expect(
			parseGroups( body ).items.map( ( item ) => item.path )
		).toEqual( [ [], [ 'Exams', 'SAT' ] ] );
		expect( () =>
			parseGroups( { ...body, items: [ { ...source, path: 'SAT' } ] } )
		).toThrow();
	} );

	it( 'reads an empty audit and moves steps within bounds', () => {
		expect( parseAudit( [] ) ).toEqual( [] );
		expect( moved( [ 1, 2, 3 ], 0, -1 ) ).toEqual( [ 1, 2, 3 ] );
		expect( moved( [ 1, 2, 3 ], 2, -1 ) ).toEqual( [ 1, 3, 2 ] );
	} );

	it( 'checks shared skill lengths', () => {
		const input = { name: ' ', description: '', topic: '', subject: '' };
		expect( sharedSkillProblem( input ) ).toMatch( /name/ );
		expect(
			sharedSkillProblem( {
				...input,
				name: 'A',
				topic: 'x'.repeat( 101 ),
			} )
		).toMatch( /100/ );
		expect( sharedSkillProblem( { ...input, name: 'A' } ) ).toBeNull();
	} );
} );
