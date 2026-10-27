import test from 'node:test';
import assert from 'node:assert/strict';
import {
	generatePuzzle,
	publicPuzzle,
	puzzleCells,
	puzzleDefaults,
	puzzleIssues,
	puzzleLetters,
	puzzlePath,
} from '../../assets/src/features/question-editor/puzzleModel.mjs';

for ( const type of [ 'crossword', 'wordsearch' ] ) {
	test( `${ type } generates repeatable, bounded layouts with private keys excluded`, () => {
		const settings = puzzleDefaults( type );
		assert.deepEqual( puzzleIssues( type, settings ), [] );
		assert.deepEqual(
			generatePuzzle( type, settings, 23 ),
			generatePuzzle( type, settings, 23 )
		);
		assert.equal( settings.entries.length, 2 );
		const view = publicPuzzle( type, settings );
		assert.equal( view.entries, undefined );
		assert.equal( view.partial_credit, undefined );
		if ( type === 'crossword' )
			assert.ok( ! JSON.stringify( view ).includes( 'CAT' ) );
		else
			assert.ok(
				view.clues.every(
					( clue ) =>
						clue.row === undefined &&
						clue.col === undefined &&
						clue.direction === undefined
				)
			);
	} );
	test( `${ type } rejects duplicate, oversized and incomplete content`, () => {
		const settings = puzzleDefaults( type );
		assert.throws( () =>
			generatePuzzle( type, { ...settings, size: 40 } )
		);
		assert.throws( () =>
			generatePuzzle( type, {
				...settings,
				entries: [
					settings.entries[ 0 ],
					{ ...settings.entries[ 1 ], answer: 'CAT' },
				],
			} )
		);
		assert.ok(
			puzzleIssues( type, { ...settings, puzzle_version: 0 } ).length
		);
		assert.throws( () =>
			generatePuzzle( type, {
				...settings,
				entries: [ { id: 'w1', answer: 'TWO WORDS', clue: '' } ],
			} )
		);
	} );
	test( `${ type } supports repeated Mongolian letters`, () => {
		const settings = {
			...puzzleDefaults( type ),
			alphabet: 'АБВГДЕЁЖЗИЙКЛМНОӨПРСТУҮФХЦЧШЩЪЫЬЭЮЯ',
			entries: [
				{ id: 'w1', answer: 'үүл', clue: 'Cloud' },
				{ id: 'w2', answer: 'үхэр', clue: 'Cow' },
			],
		};
		const layout = generatePuzzle( type, settings );
		assert.equal( layout.entries[ 0 ].answer, 'ҮҮЛ' );
		assert.deepEqual(
			puzzleIssues( type, { ...settings, ...layout } ),
			[]
		);
	} );
}

test( 'crossword reports impossible connections and conflicting manual positions', () => {
	const settings = puzzleDefaults( 'crossword' );
	assert.throws(
		() =>
			generatePuzzle( 'crossword', {
				...settings,
				entries: [
					{ id: 'w1', answer: 'CAT', clue: 'Pet' },
					{ id: 'w2', answer: 'DOG', clue: 'Pet' },
				],
			} ),
		/could not fit/
	);
	assert.ok(
		puzzleIssues( 'crossword', {
			...settings,
			entries: settings.entries.map( ( entry ) => ( {
				...entry,
				row: 0,
				col: 0,
				direction: 'across',
			} ) ),
		} ).length
	);
} );

test( 'wordsearch directions and grid changes are validated', () => {
	const settings = puzzleDefaults( 'wordsearch' );
	assert.throws(
		() =>
			generatePuzzle( 'wordsearch', {
				...settings,
				entries: [
					{ id: 'w1', answer: 'CAT', clue: '' },
					{ id: 'w2', answer: 'TAC', clue: '' },
				],
			} ),
		/distinct words/
	);
	const modified = structuredClone( settings );
	const entry = modified.entries[ 0 ];
	modified.grid[ entry.row ][ entry.col ] = 'Z';
	assert.ok( puzzleIssues( 'wordsearch', modified ).length );
	const diagonal = {
		...settings,
		allow_diagonal: true,
		allow_backward: true,
	};
	const layout = generatePuzzle( 'wordsearch', diagonal, 17 );
	assert.deepEqual(
		puzzleIssues( 'wordsearch', { ...diagonal, ...layout } ),
		[]
	);
} );

test( 'graphemes and endpoint identities preserve repeated letters and reverse paths', () => {
	assert.deepEqual( puzzleLetters( 'үүл' ), [ 'Ү', 'Ү', 'Л' ] );
	assert.deepEqual( puzzleLetters( 'e\u0301e\u0301' ), [ 'É', 'É' ] );
	const cells = puzzleCells( {
		answer: 'ҮҮЛ',
		row: 1,
		col: 1,
		direction: 'across',
	} );
	assert.deepEqual( cells, [ 'r1c1', 'r1c2', 'r1c3' ] );
	assert.equal( puzzlePath( cells ), puzzlePath( cells.toReversed() ) );
} );
