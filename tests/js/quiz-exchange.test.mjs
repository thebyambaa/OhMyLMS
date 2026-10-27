import test from 'node:test';
import assert from 'node:assert/strict';
import {
	IMPORT_ACCEPT,
	downloadText,
	exampleFile,
	problemLines,
	typeCounts,
} from '../../assets/src/features/content-hub/quizExchange.mjs';
import {
	exportQuiz,
	importQuiz,
} from '../../assets/src/features/content-hub/api.mjs';

test( 'A refused file lists every problem with its question number', () => {
	const error = {
		code: 'ohmylms_quiz_import_invalid',
		message: '2 problems found, nothing was imported. First: x',
		data: {
			status: 400,
			errors: [
				{ question: 3, message: 'Mark exactly one option as correct.' },
				{ question: 0, message: 'The file has no questions.' },
				{ question: 12, message: 'Randomized numbers: too strict.' },
			],
		},
	};
	assert.deepEqual( problemLines( error ), [
		'Question 3: Mark exactly one option as correct.',
		'The file has no questions.',
		'Question 12: Randomized numbers: too strict.',
	] );
} );

test( 'Other failures fall back to their message, and an empty error gives no lines', () => {
	assert.deepEqual( problemLines( { message: 'Network down' } ), [
		'Network down',
	] );
	assert.deepEqual(
		problemLines( { data: { errors: [] }, message: 'Bad' } ),
		[ 'Bad' ]
	);
	assert.deepEqual( problemLines( null ), [] );
	assert.deepEqual( problemLines( {} ), [] );
} );

test( 'Question types are counted most common first, with readable names', () => {
	assert.deepEqual(
		typeCounts( {
			types: {
				'single-choice': 2,
				'fill-in-the-blank': 5,
				numerical: 2,
				'build-expression': 1,
			},
		} ),
		[
			{ type: 'fill-in-the-blank', count: 5, label: 'Fill in the blank' },
			{ type: 'numerical', count: 2, label: 'Numerical' },
			{ type: 'single-choice', count: 2, label: 'Single choice' },
			{ type: 'build-expression', count: 1, label: 'Build expression' },
		]
	);
	assert.deepEqual( typeCounts( undefined ), [] );
} );

test( 'The example file is a valid quiz file that shows a randomized question', () => {
	const text = exampleFile();
	assert.ok( text.endsWith( '\n' ) );
	const file = JSON.parse( text );
	assert.equal( file.format, 'ohmylms.quiz' );
	assert.equal( file.version, 1 );
	assert.deepEqual(
		file.questions.map( ( question ) => question.settings.type ),
		[ 'single-choice', 'fill-in-the-blank', 'numerical' ]
	);
	const randomized = file.questions[ 2 ];
	assert.ok( randomized.settings.template.variables.length >= 2 );
	assert.match( randomized.description, /\{\{a\}\}.*\{\{x:2\}\}/ );
	assert.equal( randomized.settings.template.set[ 0 ].path, 'answer' );
	// Hints, explanations and the score belong inside "settings", the place the importer reads.
	assert.equal( file.questions[ 0 ].settings.hint, 'Count on your fingers.' );
	for ( const question of file.questions ) {
		assert.ok( question.settings.score.value > 0 );
		assert.ok( Array.isArray( question.options ) );
	}
	assert.equal(
		file.questions[ 0 ].options.filter( ( option ) => option.is_correct )
			.length,
		1
	);
} );

test( 'The dialog offers the quiz file and the older CSV layout', () => {
	assert.match( IMPORT_ACCEPT, /\.json/ );
	assert.match( IMPORT_ACCEPT, /\.csv/ );
} );

test( 'Importing sends the file text, and a dry run asks for nothing to be created', async () => {
	const calls = [];
	const fetch = ( options ) => {
		calls.push( options );
		return Promise.resolve( { ok: true } );
	};
	await importQuiz( 'My quiz', '{"questions":[]}', fetch );
	await importQuiz( '', 'text', fetch, { dryRun: true } );
	await importQuiz( '', 'text', fetch, { copyMedia: false } );
	assert.deepEqual( calls[ 0 ], {
		path: '/ohmylms/v1/quiz/import/',
		method: 'POST',
		data: { name: 'My quiz', content: '{"questions":[]}' },
	} );
	assert.deepEqual( calls[ 1 ].data, {
		name: '',
		content: 'text',
		dry_run: true,
	} );
	assert.deepEqual( calls[ 2 ].data, {
		name: '',
		content: 'text',
		copy_media: false,
	} );
	assert.equal( 'csv' in calls[ 0 ].data, false );
} );

test( 'Exporting asks for one quiz by ID', async () => {
	let seen;
	await exportQuiz( 42, ( options ) => {
		seen = options;
		return Promise.resolve( {} );
	} );
	assert.deepEqual( seen, { path: '/ohmylms/v1/quiz/42/export' } );
} );

test( 'A download is a link to a text file that is released afterwards', async () => {
	const events = [];
	const link = {
		click() {
			events.push( `click ${ this.download } ${ this.href }` );
		},
	};
	let blob;
	downloadText( 'quiz.ohmylms.json', 'application/json', '{"a":"Хоёр"}', {
		document: { createElement: ( tag ) => ( events.push( tag ), link ) },
		URL: {
			createObjectURL: ( value ) => {
				blob = value;
				return 'blob:one';
			},
			revokeObjectURL: ( url ) => events.push( `revoke ${ url }` ),
		},
	} );
	assert.deepEqual( events, [
		'a',
		'click quiz.ohmylms.json blob:one',
		'revoke blob:one',
	] );
	assert.equal( blob.type, 'application/json;charset=utf-8' );
	assert.equal( await blob.text(), '{"a":"Хоёр"}' );
} );
