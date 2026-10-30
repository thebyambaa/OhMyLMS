import { guessRoles } from '../../../features/curriculum/csv.mjs';
import { mappedRows, readCsv } from './csv';

it( 'reads multilingual, reordered columns, semicolons and quoted delimiters', () => {
	const parsed = readCsv(
		'skill_code;skill;group;content\nA.1;"Тэгшитгэл; solve";Algebra;Math'
	);
	expect(
		mappedRows(
			parsed,
			guessRoles( parsed.records[ 0 ]! ),
			true,
			true
		)[ 0 ]
	).toMatchObject( {
		skill_code: 'A.1',
		skill: 'Тэгшитгэл; solve',
		group: 'Algebra',
		content: 'Math',
	} );
} );
it( 'supports quoted newlines and spreadsheet-style fill down', () => {
	const parsed = readCsv(
		'group,skill,description\nAlgebra,Solve,"first\nsecond"\n,Count,'
	);
	const rows = mappedRows(
		parsed,
		guessRoles( parsed.records[ 0 ]! ),
		true,
		true
	);
	expect( rows[ 0 ]?.description ).toBe( 'first\nsecond' );
	expect( rows[ 1 ]?.group ).toBe( 'Algebra' );
} );
it.each( [ '', 'skill\n"unfinished', 'x'.repeat( 5 * 1024 * 1024 + 1 ) ] )(
	'rejects unusable or oversized CSV',
	( source ) => {
		expect( () => readCsv( source ) ).toThrow();
	}
);
it( 'rejects missing or duplicate skill-column mappings', () => {
	const parsed = readCsv( 'a,b\nOne,Two' );
	expect( () => mappedRows( parsed, [ '', '' ], true, true ) ).toThrow();
	expect( () =>
		mappedRows( parsed, [ 'skill', 'skill' ], true, true )
	).toThrow();
} );

it( 'accepts a UTF-8 BOM before a quoted header', () => {
	const parsed = readCsv( '\uFEFF"skill",skill_code\n"Тоо",A.1' );
	expect(
		mappedRows(
			parsed,
			guessRoles( parsed.records[ 0 ]! ),
			true,
			true
		)[ 0 ]?.skill
	).toBe( 'Тоо' );
} );
it( 'rejects excessive records before allocating the parsed sheet', () => {
	expect( () => readCsv( 'skill\n' + 'Count\n'.repeat( 6000 ) ) ).toThrow(
		'Use at most 5000 data rows.'
	);
} );
