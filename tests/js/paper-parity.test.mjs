import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {
	paperQuestion,
	paperSettings,
} from '../../assets/src/features/paper/paperModel.mjs';

/*
 * The editor's paper canvas prints with paperModel.mjs; the print page and the learner's Paper View
 * print with OhMyLMS\Exams\PaperModel. tests/fixtures/paper-models.json holds the models both must
 * produce; tests/php/paper-render-unit.php checks the PHP side against the same file.
 * Run with UPDATE_PAPER_FIXTURE=1 to rewrite it after a deliberate change.
 */

const fixture = path.resolve(
	import.meta.dirname,
	'../fixtures/paper-models.json'
);
const marks = ( value ) => ( { enabled: true, value } );
const options = ( list, correct = [] ) =>
	list.map( ( answer, index ) => ( {
		answer,
		is_correct: correct.includes( answer ) ? 1 : 0,
		order_number: index + 1,
	} ) );

const questions = {
	choice: {
		id: 31,
		settings: { type: 'single-choice', score: marks( 2 ) },
		questions: options( [ 'Venus', 'Mars', 'Jupiter', 'Mercury' ], [
			'Mars',
		] ),
	},
	multiple: {
		id: 33,
		settings: { type: 'multiple-choice', score: marks( 1 ) },
		questions: options( [ 'Two', 'Three', 'Four' ], [ 'Two', 'Four' ] ),
	},
	trueFalse: {
		id: 32,
		settings: { type: 'true-false', score: marks( 1 ) },
		questions: options( [ 'True', 'False' ], [ 'False' ] ),
	},
	numerical: {
		id: 40,
		settings: {
			type: 'numerical',
			score: marks( 2 ),
			unit: 'cm',
			answer: [ '12.5' ],
			tolerance: 0.5,
		},
	},
	shortText: {
		id: 41,
		settings: { type: 'short-text', score: marks( 1 ) },
		questions: [ { answer: 'Paris', order_number: 1 } ],
	},
	textEntry: {
		id: 42,
		settings: {
			type: 'text-entry',
			score: marks( 2 ),
			accepted: [ 'H2O', 'h2o' ],
		},
	},
	longText: {
		id: 43,
		settings: { type: 'long-text', score: marks( 3 ) },
	},
	structured: {
		id: 44,
		settings: {
			type: 'structured',
			parts: [
				{
					id: 'p1',
					label: '(a)',
					prompt: 'Work out 1 + 1.',
					kind: 'numerical',
					marks: 2,
					answer: 2,
					unit: 'kg',
				},
				{
					id: 'p2',
					label: 'b',
					prompt: 'Explain.',
					kind: 'written',
					marks: 3,
					rubric: 'M1 method, A1 answer.',
				},
			],
		},
	},
	statement: {
		id: 45,
		settings: { type: 'statement' },
	},
	blanks: {
		id: 46,
		settings: { type: 'fill-in-the-blank', score: marks( 2 ) },
	},
	unsupported: {
		id: 47,
		settings: { type: 'graphing', score: marks( 4 ) },
	},
	matching: {
		id: 9,
		settings: { type: 'matching', score: marks( 2 ) },
		questions: [
			[ 'France', 'Paris' ],
			[ 'Italy', 'Rome' ],
			[ 'Spain', 'Madrid' ],
		].map( ( [ answer, label ], index ) => ( {
			answer,
			order_number: index + 1,
			matching_data: { label },
		} ) ),
	},
	matchingPublic: {
		id: 9,
		public: true,
		settings: {
			type: 'matching',
			score: marks( 2 ),
			match_right: [ 'Rome', 'Madrid', 'Paris' ],
		},
		questions: [
			{ answer: 'France' },
			{ answer: 'Italy' },
			{ answer: 'Spain' },
		],
	},
	reorder: {
		id: 5,
		settings: { type: 'reorder', score: marks( 2 ) },
		questions: options( [ 'egg', 'larva', 'pupa', 'butterfly' ] ),
	},
	dropdown: {
		id: 3,
		settings: {
			type: 'dropdown-blanks',
			score: marks( 2 ),
			text: 'Water boils at {1} degrees and freezes at {2}.',
			slots: [
				{ id: '1', choices: [ '100', '50', '0' ], answer: '100' },
				{
					id: '2',
					choices: [ '0', '32', '-10' ],
					answers: [ '0' ],
					multiple: true,
				},
			],
		},
	},
	multiBlank: {
		id: 4,
		settings: {
			type: 'multi-blank',
			score: marks( 2 ),
			text: 'Speed {a}, time {b}',
			blanks: {
				a: { kind: 'numerical', answer: 12, unit: 'm/s' },
				b: { kind: 'text', accepted: [ 'ten seconds' ] },
			},
		},
	},
	multiBlankTable: {
		id: 14,
		settings: {
			type: 'multi-blank',
			score: marks( 2 ),
			layout: 'table',
			columns: [ 'x', 'y' ],
			rows: [
				[ '1', '{a}' ],
				[ '2', '{b} cm' ],
			],
			blanks: {
				a: { kind: 'numerical', answer: 3 },
				b: { kind: 'expression', answer: '2x', unit: 'cm' },
			},
		},
	},
	categorize: {
		id: 8,
		settings: {
			type: 'categorize',
			score: marks( 2 ),
			buckets: [
				{ id: 'm', label: 'Mammals' },
				{ id: 'b', label: 'Birds' },
			],
			items: [
				{ id: 'i1', text: 'Dog' },
				{ id: 'i2', text: 'Eagle' },
				{ id: 'i3', text: 'Cat' },
			],
			key: { i1: 'm', i2: 'b', i3: 'm' },
		},
	},
	tiles: {
		id: 2,
		settings: {
			type: 'build-expression',
			score: marks( 2 ),
			correct: [ '2', 'x', '+', '3' ],
			distractors: [ '-' ],
		},
	},
	expression: {
		id: 1,
		settings: { type: 'expression', score: marks( 2 ), answer: '3x+9' },
	},
	crossword: {
		id: 6,
		settings: {
			type: 'crossword',
			score: marks( 2 ),
			size: 5,
			entries: [
				{
					id: 'w1',
					clue: 'Feline',
					answer: 'CAT',
					row: 0,
					col: 0,
					direction: 'across',
				},
				{
					id: 'w2',
					clue: 'Not hot',
					answer: 'COLD',
					row: 0,
					col: 0,
					direction: 'down',
				},
			],
		},
	},
	wordsearch: {
		id: 7,
		settings: {
			type: 'wordsearch',
			score: marks( 2 ),
			size: 4,
			with_clues: false,
			grid: [
				[ 'C', 'A', 'T', 'X' ],
				[ 'Q', 'W', 'E', 'R' ],
				[ 'D', 'O', 'G', 'Z' ],
				[ 'M', 'N', 'B', 'V' ],
			],
			entries: [
				{
					id: 'w1',
					clue: '',
					answer: 'CAT',
					row: 0,
					col: 0,
					direction: 'across',
				},
				{
					id: 'w2',
					clue: '',
					answer: 'DOG',
					row: 2,
					col: 0,
					direction: 'across',
				},
			],
		},
	},
	numberLine: {
		id: 20,
		settings: {
			type: 'number-line',
			score: marks( 1 ),
			min: 0,
			max: 2,
			step: 0.25,
			target: 1.5,
		},
	},
	jug: {
		id: 21,
		settings: { type: 'fill-level', score: marks( 1 ), target: 350 },
	},
	shade: {
		id: 22,
		settings: {
			type: 'shade-model',
			score: marks( 1 ),
			shape: 'grid',
			parts: 12,
			cols: 4,
			answer: 5,
		},
	},
	blocks: {
		id: 23,
		settings: {
			type: 'count-blocks',
			score: marks( 1 ),
			places: [ 'ones', 'thousands', 'tens' ],
			target: 2047,
		},
	},
	clock: {
		id: 24,
		settings: {
			type: 'set-clock',
			score: marks( 1 ),
			show_digital: true,
			hour: 15,
			minute: 5,
		},
	},
	money: {
		id: 25,
		settings: {
			type: 'make-amount',
			score: marks( 1 ),
			denominations: [ 5, 20, '10', 5, 0 ],
			symbol: '$',
			target: 35,
		},
	},
	chart: {
		id: 26,
		settings: {
			type: 'build-chart',
			score: marks( 1 ),
			categories: [
				{ id: 'cats!', label: 'Cats' },
				{ id: 'dogs', label: 'Dogs' },
			],
			max: 8,
			step: 2,
			show_table: true,
			values: { cats: 3, dogs: 6 },
		},
	},
	grid: {
		id: 27,
		settings: {
			type: 'grid-build',
			score: marks( 1 ),
			rows: 30,
			cols: 5,
			constraints: { area: 6, rectangle: true },
		},
	},
};

const prompts = {
	blanks: '<p>The capital of {France} is {Paris}.</p>',
	dropdown: '<p>Water boils at {1} degrees and freezes at {2}.</p>',
	multiBlank: '<p>Speed {a}, time {b}</p>',
};

/**
 * Sets become sorted lists, so both implementations compare as JSON.
 *
 * @param {Object} model Paper model.
 * @return {Object} Plain model.
 */
const plain = ( model ) =>
	JSON.parse(
		JSON.stringify( model, ( key, value ) =>
			value instanceof Set ? [ ...value ].sort() : value
		)
	);

function models() {
	const settings = paperSettings( { lines_per_mark: 3 } );
	const cases = [];
	for ( const [ name, question ] of Object.entries( questions ) ) {
		for ( const [ variant, answers, version ] of [
			[ 'paper', false, 'A' ],
			[ 'key', true, 'A' ],
			[ 'key-b', true, 'B' ],
		] ) {
			const prompt = prompts[ name ] || '';
			cases.push( {
				name: `${ name } ${ variant }`,
				question,
				prompt,
				answers,
				version,
				expected: plain(
					paperQuestion( question, {
						settings,
						showAnswers: answers,
						prompt,
						version,
					} )
				),
			} );
		}
	}
	return { settings: { lines_per_mark: 3 }, cases };
}

test( 'the editor model matches the shared paper fixture', () => {
	const current = models();
	if ( process.env.UPDATE_PAPER_FIXTURE === '1' ) {
		fs.writeFileSync(
			fixture,
			JSON.stringify( current, null, '\t' ) + '\n'
		);
	}
	const saved = JSON.parse( fs.readFileSync( fixture, 'utf8' ) );
	assert.equal( saved.cases.length, current.cases.length );
	for ( const [ index, entry ] of current.cases.entries() ) {
		assert.deepEqual( saved.cases[ index ], entry, entry.name );
	}
} );

test( 'student models in the fixture carry no answers', () => {
	const text = JSON.stringify(
		models()
			.cases.filter( ( entry ) => ! entry.answers )
			.map( ( entry ) => entry.expected )
	);
	for ( const secret of [
		'"correct"',
		'"answers"',
		'"key"',
		'"letters":{"',
		'3x+9',
		'ten seconds',
		'"rubric"',
	] ) {
		assert.ok( ! text.includes( secret ), secret );
	}
} );
