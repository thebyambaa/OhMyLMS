import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { transformSync } from '@babel/core';
import { normalizeQuizReport } from '../../assets/src/features/quiz-reports/model.mjs';

const generate = generatorModule.default || generatorModule;
const source = 'assets/src/';
const manifest = JSON.parse( fs.readFileSync( source + 'manifest.json' ) );
const factory = manifest.assets
	.find( ( a ) => a.output === 'assets/dist/admin/ohmylms.js' )
	.factories.find( ( f ) => f.id === '1841' );
const ast = parse(
	factory.fragments
		.map( ( file ) => fs.readFileSync( source + file, 'utf8' ) )
		.join( '\n' )
);
const declarations = new Map();
for ( const statement of ast.program.body ) {
	if ( statement.type === 'VariableDeclaration' )
		for ( const declaration of statement.declarations )
			declarations.set( declaration.id.name, declaration.init );
	if ( statement.type === 'FunctionDeclaration' )
		declarations.set( statement.id.name, statement );
}
const rows = JSON.parse(
	fs.readFileSync( 'assets/src/features/quiz-reports/components.json' )
);
const compiled = new Map(
	rows.map( ( row ) => {
		const input = fs
			.readFileSync(
				'assets/src/features/quiz-reports/' + row.file,
				'utf8'
			)
			.replace( /^import .*;$/gm, '' )
			.replace( 'export function', 'function' );
		return [
			row.name,
			transformSync( input, {
				configFile: false,
				babelrc: false,
				presets: [
					[
						'@babel/preset-react',
						{ runtime: 'classic', pragma: 'React.createElement' },
					],
				],
			} ).code,
		];
	} )
);
const plain = ( value ) =>
	JSON.parse(
		JSON.stringify( value, ( key, item ) =>
			typeof item === 'function' ? '[callback]' : item
		)
	);
const find = ( tree, type ) => {
	if ( ! tree || typeof tree !== 'object' ) return undefined;
	if ( tree.type === type ) return tree;
	for ( const child of Array.isArray( tree ) ? tree : tree.children || [] ) {
		const result = find( child, type );
		if ( result ) return result;
	}
};

function harness( states = [] ) {
	let cursor = 0;
	const updates = new Map(),
		effects = [],
		requests = [],
		navigations = [];
	const React = {
		Fragment: 'Fragment',
		createElement: ( type, props, ...children ) => ( {
			type,
			props: props || {},
			children,
		} ),
	};
	const controls = new Proxy( {}, { get: ( _, name ) => String( name ) } );
	const globals = {
		React,
		console,
		normalizeQuizReport,
		window: {
			location: {
				reload() {
					globals.reloaded = true;
				},
			},
		},
		I: controls,
		b: { __: ( text ) => text },
		HG() {},
		Ge: ( text ) => text,
		g: {
			memo: ( fn ) => fn,
			useState( initial ) {
				const index = cursor++;
				return [
					index in states ? states[ index ] : initial,
					( value ) => updates.set( index, value ),
				];
			},
			useEffect: ( fn ) => effects.push( fn ),
			useMemo: ( fn ) => fn(),
			useCallback: ( fn ) => fn,
		},
		f: {
			g: () => ( { id: '987', quizId: '123' } ),
			Zp: () => ( target ) => navigations.push( target ),
		},
		v: { Link: 'Link' },
		sn: () => ( value ) => ( { format: () => value } ),
		l: () => async ( request ) => {
			requests.push( request );
			return request.method === 'POST'
				? { status: 'success' }
				: globals.response;
		},
		sN: { A: 'Table' },
		wn: { A: 'NumberInput' },
		Mt: { A: 'InfoIcon' },
		V: { A: 'Tooltip' },
	};
	for ( const name of [
		'Cm',
		'QZ',
		'fN',
		'uf',
		'df',
		'p$',
		'v$',
		'h$',
		'E$',
		'C$',
		'M$',
		'I$',
		'N$',
		'r$',
		'o$',
		'l$',
		'b$',
	] )
		globals[ name ] = name;
	const context = vm.createContext( globals );
	for ( const [ name, declaration ] of declarations ) {
		if ( name in globals ) continue;
		Object.defineProperty( globals, name, {
			configurable: true,
			get() {
				const expression =
					declaration.type === 'FunctionDeclaration'
						? {
								...declaration,
								type: 'FunctionExpression',
								id: null,
							}
						: declaration;
				const value = vm.runInContext(
					'(' +
						generate( expression, { comments: false } ).code +
						'\n)',
					context
				);
				Object.defineProperty( globals, name, {
					value,
					writable: true,
					configurable: true,
				} );
				return value;
			},
			set( value ) {
				Object.defineProperty( globals, name, {
					value,
					writable: true,
					configurable: true,
				} );
			},
		} );
	}
	return {
		globals,
		updates,
		effects,
		requests,
		navigations,
		render( name, props = {}, original = false ) {
			cursor = 0;
			const row = rows.find( ( row ) => row.name === name );
			if ( original ) return globals[ row.binding ]( props );
			const create = vm.runInContext(
				compiled.get( name ) + '\ncreate' + name,
				context
			);
			return create( () => globals )( props );
		},
	};
}

const question = ( type ) => ( {
	id: 7,
	name: 'Example question',
	settings: { type, score: { value: 10 } },
	questions: [
		{ id: 1, answer: 'A', is_correct: '1', order_number: 1 },
		{ id: 2, answer: 'B', is_correct: '0', order_number: 2 },
	],
	given_answer: [ 1 ],
	achive_mark: 3,
	status: 'in-review',
} );
const attempt = {
	student: { name: 'Student' },
	course: { name: 'Course' },
	score: 3,
	passing_mark: 5,
	total_question: 1,
	end_date: '2026-09-24',
	report: { status: 'in-review', questions: [ question( 'short-text' ) ] },
};

test( 'extracted quiz result views preserve original rendering for all question types', () => {
	for ( const type of [
		'single-choice',
		'multiple-choice',
		'true-false',
		'short-text',
		'long-text',
		'statement',
		'fill-in-the-blank',
		'reorder',
		'matching',
	] ) {
		const data = question( type );
		if ( type === 'matching' ) data.given_answer = { 1: '1' };
		const name =
			type === 'matching'
				? 'MatchingResult'
				: type === 'reorder'
					? 'ReorderResult'
					: type === 'multiple-choice'
						? 'MultipleChoiceResult'
						: [ 'single-choice', 'true-false' ].includes( type )
							? 'SingleChoiceResult'
							: 'TextAnswerResult';
		for ( const [ component, props ] of [
			[ name, { data, index: 0 } ],
			[ 'QuizQuestionHeader', { data, index: 0, type: 'text-type' } ],
			[ 'QuizQuestionResults', { data: [ data ] } ],
		] ) {
			const h = harness();
			assert.deepEqual(
				plain( h.render( component, props ) ),
				plain( h.render( component, props, true ) ),
				component + ': ' + type
			);
		}
	}
	for ( const status of [ 'in-review', 'completed', 'failed' ] ) {
		const h = harness();
		const props = {
			data: {
				score: 3,
				correct: '1/2',
				isPass: status === 'completed',
				status,
			},
		};
		assert.deepEqual(
			plain( h.render( 'QuizResultSummary', props ) ),
			plain( h.render( 'QuizResultSummary', props, true ) )
		);
	}
} );

test( 'report preserves filtering, pagination, grade navigation, and request contract', async () => {
	const submissions = Array.from( { length: 12 }, ( _, index ) => ( {
		quiz_attempt_id: index,
		student_name: 'Student ' + index,
		total_marks: index,
		status: 'completed',
	} ) );
	const h = harness( [ '', 2, submissions, false, 10, 5, 10 ] );
	const tree = h.render( 'QuizReport' );
	assert.deepEqual(
		plain( tree ),
		plain( h.render( 'QuizReport', {}, true ) )
	);
	const table = find( tree, 'Table' );
	find( tree, 'Cm' ).props.onChange( 'student 11' );
	assert.equal(
		h.updates.get( 1 ),
		1,
		'Searching from page two resets pagination'
	);
	assert.deepEqual(
		plain( table.props.dataSource ),
		submissions.slice( 10 )
	);
	const action = table.props.columns
		.at( -1 )
		.render( null, submissions[ 0 ] );
	action.props.onClick();
	assert.deepEqual( h.navigations, [ 'grade-quiz/0' ] );
	h.globals.response = {
		report: submissions,
		passing_mark: 5,
		question_total_marks: 10,
	};
	h.effects[ 0 ]();
	await new Promise( ( resolve ) => setImmediate( resolve ) );
	assert.equal( h.requests[ 0 ].path, '/ohmylms/v1/quiz/987/report' );
	assert.deepEqual( h.updates.get( 2 ), submissions );
	const filtered = harness( [
		'student 11',
		1,
		submissions,
		false,
		10,
		5,
		10,
	] );
	assert.equal(
		find( filtered.render( 'QuizReport' ), 'Table' ).props.dataSource
			.length,
		1
	);
} );

test( 'manual marks update only the selected question without mutating prior state', () => {
	const h = harness();
	let update;
	const tree = h.render( 'QuizQuestionHeader', {
		data: attempt.report.questions[ 0 ],
		index: 0,
		type: 'text-type',
		setData: ( fn ) => {
			update = fn;
		},
	} );
	find( tree, 'NumberInput' ).props.onChange( 8 );
	const before = JSON.stringify( attempt );
	const next = update( attempt );
	assert.equal( next.report.questions[ 0 ].achive_mark, 8 );
	assert.equal( JSON.stringify( attempt ), before );
	assert.notEqual(
		next.report.questions[ 0 ],
		attempt.report.questions[ 0 ]
	);
} );

test( 'grading preserves attempt loading, edited payload, success reload and failed-save behavior', async () => {
	const edited = structuredClone( attempt );
	edited.report.questions[ 0 ].achive_mark = 8;
	const h = harness( [ edited, false ] );
	const tree = h.render( 'QuizGrading' );
	assert.deepEqual(
		plain( tree ),
		plain( h.render( 'QuizGrading', {}, true ) )
	);
	h.globals.response = attempt;
	h.effects[ 0 ]();
	await new Promise( ( resolve ) => setImmediate( resolve ) );
	assert.equal( h.requests[ 0 ].path, '/ohmylms/v1/quiz/987/report/123' );
	assert.deepEqual( h.updates.get( 0 ), normalizeQuizReport( attempt ) );
	await find( tree, 'ButtonWP' ).props.onClick();
	assert.deepEqual( JSON.parse( h.requests[ 1 ].body ), edited );
	assert.equal( h.requests[ 1 ].method, 'POST' );
	assert.equal( h.globals.reloaded, true );
	h.globals.reloaded = false;
	h.globals.l = () => async () => ( { status: 'error' } );
	await find( h.render( 'QuizGrading' ), 'ButtonWP' ).props.onClick();
	assert.equal( h.globals.reloaded, false );
} );

test( 'live REST ID types produce checked answers and correct result badges without mutating the payload', () => {
	const source = {
		...attempt,
		report: {
			status: 'in-review',
			questions: [
				question( 'single-choice' ),
				question( 'multiple-choice' ),
				{ ...question( 'reorder' ), given_answer: [ 1, 2 ] },
				{ ...question( 'matching' ), given_answer: { 1: 1 } },
			],
		},
	};
	const before = JSON.stringify( source );
	const normalized = normalizeQuizReport( source );
	const h = harness();
	const single = h.render( 'SingleChoiceResult', {
		data: normalized.report.questions[ 0 ],
		index: 0,
	} );
	assert.equal( find( single, 'RadioWP' ).props.selected, '1' );
	assert.match( find( single, 'div' ).props.className, /ohmylms-correct$/ );
	const multiple = h.render( 'MultipleChoiceResult', {
		data: normalized.report.questions[ 1 ],
		index: 0,
	} );
	assert.match( find( multiple, 'div' ).props.className, /ohmylms-correct$/ );
	for ( const [ name, index ] of [
		[ 'ReorderResult', 2 ],
		[ 'MatchingResult', 3 ],
	] ) {
		assert.equal(
			find(
				h.render( name, {
					data: normalized.report.questions[ index ],
					index,
				} ),
				'p$'
			).props.isCorrect,
			true
		);
	}
	assert.equal( JSON.stringify( source ), before );
	assert.equal( normalized.report.status, 'in-review' );
	assert.equal(
		normalized.report.questions[ 0 ].id,
		source.report.questions[ 0 ].id,
		'Question IDs stay unchanged for grade writes'
	);
} );
