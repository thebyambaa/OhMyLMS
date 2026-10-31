import { ApiError } from '../../lib/api';
import { question, summary, types, version } from './fixtures';
import {
	answerArea,
	approvalLabel,
	canApproveCurrent,
	createInput,
	draftQuestion,
	flag,
	formFromQuestion,
	inlineBlanks,
	isDirty,
	moveOption,
	newOption,
	normalizeSkills,
	parseBanks,
	parseQuestionDetail,
	parseQuestionSummary,
	parseQuestionType,
	parseQuestionVersion,
	parseSkillRef,
	scoreMode,
	serverFieldError,
	setCorrect,
	settingsFor,
	settingsProblem,
	sharedSettings,
	skillParts,
	toCreateInput,
	toInput,
	typeLabel,
	validateForm,
	versionLabel,
} from './model';
import type { FormOption, FormState } from './types';

const saved = formFromQuestion( question );

function option( answer: string, isCorrect = false, id = 0 ): FormOption {
	return {
		key: `k-${ answer }`,
		id,
		answer,
		is_correct: isCorrect,
		image_id: 0,
		image_url: '',
		matching: null,
	};
}

const errorsFor = ( change: Partial< FormState >, type = 'single-choice' ) =>
	validateForm( { ...saved, ...change }, saved, type );

describe( 'response parsers', () => {
	it( 'accepts the documented shapes', () => {
		expect( parseQuestionSummary( summary ) ).toEqual( summary );
		expect( parseQuestionDetail( question ) ).toEqual( question );
		expect( parseQuestionVersion( version ) ).toEqual( version );
		for ( const type of types ) {
			expect( parseQuestionType( type ) ).toEqual( type );
		}
	} );

	it( 'accepts the nulls and empty objects PHP can send', () => {
		const bare = parseQuestionDetail( {
			...question,
			settings: [],
			skills: [],
			extension_settings: null,
			options: [
				{ ...question.options[ 0 ], matching_data: null },
				{
					...question.options[ 1 ],
					matching_data: { label: 'Pair' },
				},
			],
		} );
		expect( bare.settings ).toEqual( {} );
		expect( bare.skills ).toEqual( {} );
		expect( bare.extension_settings ).toEqual( {} );
		expect( bare.options[ 1 ]!.matching_data ).toEqual( { label: 'Pair' } );
		expect(
			parseQuestionType( { ...types[ 0 ], format: null, answers: null } )
		).toMatchObject( { format: null, answers: null } );
		expect(
			parseQuestionVersion( {
				...version,
				created_at: '',
				created_by: { id: 0, name: '' },
			} ).created_by
		).toEqual( { id: 0, name: '' } );
	} );

	it.each( [
		[ 'a non-object', 'question' ],
		[ 'a string id', { ...question, id: '812' } ],
		[ 'an unknown status', { ...question, status: 'trash' } ],
		[ 'a missing name', { ...question, name: undefined } ],
		[ 'a list of settings', { ...question, settings: [ 1 ] } ],
		[ 'options that are not a list', { ...question, options: {} } ],
		[
			'an option without text',
			{
				...question,
				options: [ { ...question.options[ 0 ], answer: 3 } ],
			},
		],
		[ 'a bank without difficulty', { ...question, bank: { bank_id: 0 } } ],
		[ 'a negative version', { ...question, version: -1 } ],
		[ 'a quiz without a name', { ...question, quizzes: [ { id: 7 } ] } ],
	] )( 'rejects %s', ( _label, value ) => {
		expect( () => parseQuestionDetail( value ) ).toThrow( ApiError );
		expect( () => parseQuestionDetail( value ) ).toThrowError(
			expect.objectContaining( { code: 'invalid_response', status: 502 } )
		);
	} );
} );

describe( 'labels', () => {
	it( 'describes the version state', () => {
		expect( versionLabel( summary ) ).toBe( 'v3 · draft' );
		expect(
			versionLabel( {
				...summary,
				status: 'approved',
				approved_version_id: 95,
				approved_is_current: true,
			} )
		).toBe( 'v3 · approved' );
		expect(
			versionLabel( {
				...summary,
				status: 'approved',
				approved_version_id: 90,
				approved_is_current: false,
			} )
		).toBe( 'v3 · newer than approved' );
		expect( versionLabel( { ...summary, status: 'archived' } ) ).toBe(
			'v3 · archived'
		);
	} );

	it( 'describes approval and when the current version can be approved', () => {
		const approved = {
			...question,
			status: 'approved' as const,
			approved_version_id: 95,
			approved_is_current: true,
		};
		const older = { ...approved, approved_is_current: false };
		expect( canApproveCurrent( question ) ).toBe( true );
		expect( canApproveCurrent( older ) ).toBe( true );
		expect( canApproveCurrent( approved ) ).toBe( false );
		expect(
			canApproveCurrent( {
				...question,
				permissions: { ...question.permissions, can_approve: false },
			} )
		).toBe( false );
		expect( approvalLabel( question ) ).toBe(
			'No version is approved for shared use and practice yet.'
		);
		expect( approvalLabel( older ) ).toMatch(
			/^An earlier version is approved/
		);
		expect( approvalLabel( approved ) ).toBe(
			'This version is approved for shared use and practice.'
		);
	} );

	it( 'falls back from the type list to the given label and the id', () => {
		expect( typeLabel( types, 'single-choice' ) ).toBe( 'Single Choice' );
		expect( typeLabel( [], 'single-choice', 'Single' ) ).toBe( 'Single' );
		expect( typeLabel( [], 'custom' ) ).toBe( 'custom' );
	} );
} );

describe( 'drafts', () => {
	it( 'seeds settings from the authored type defaults and options from createInput', () => {
		expect( draftQuestion( ' Clock ', 'set-clock' ) ).toMatchObject( {
			id: 0,
			name: 'Clock',
			type: 'set-clock',
			settings: {
				type: 'set-clock',
				score: { enabled: true, value: 1 },
				hour: 3,
				minute: 45,
			},
			options: [],
			version: 0,
		} );
		expect( draftQuestion( 'Q', 'graded-reader' ).settings ).toEqual( {
			type: 'graded-reader',
			score: { enabled: true, value: 1 },
		} );
		expect(
			draftQuestion( 'Q', 'true-false' ).options.map( ( item ) => [
				item.answer,
				item.is_correct,
			] )
		).toEqual( [
			[ 'True', true ],
			[ 'False', false ],
		] );
	} );

	it( 'builds the create body from the draft form', () => {
		const form = {
			...formFromQuestion( draftQuestion( 'Pick', 'multiple-choice' ) ),
			points: '3',
			description: '<p>Why?</p>',
		};
		expect( toCreateInput( form, 'multiple-choice' ) ).toEqual( {
			name: 'Pick',
			description: '<p>Why?</p>',
			settings: {
				type: 'multiple-choice',
				score: { enabled: true, value: 3 },
			},
			bank: { difficulty: 'standard' },
			options: [
				{ answer: 'Option 1', is_correct: true, order_number: 0 },
				{ answer: 'Option 2', is_correct: false, order_number: 1 },
			],
		} );
		expect(
			toCreateInput(
				formFromQuestion( draftQuestion( 'N', 'numerical' ) ),
				'numerical'
			)
		).not.toHaveProperty( 'options' );
	} );

	it( 'asks for the expected number of a numerical question', () => {
		expect( settingsProblem( 'numerical', {} ) ).toBe(
			'Enter the expected number.'
		);
		expect( settingsProblem( 'numerical', { answer: 'x' } ) ).toBe(
			'Enter the expected number.'
		);
		expect( settingsProblem( 'numerical', { answer: 4 } ) ).toBeNull();
		expect(
			settingsProblem( 'numerical', { answers: [ 1, 2 ] } )
		).toBeNull();
		expect(
			settingsProblem( 'numerical', {
				template: { variables: [ { name: 'a' } ] },
			} )
		).toBeNull();
		expect( settingsProblem( 'set-clock', {} ) ).toBeNull();
	} );
} );

describe( 'createInput', () => {
	const score = { enabled: true, value: 1 };
	it( 'adds a correct first option for choice types', () => {
		for ( const type of [ 'single-choice', 'multiple-choice' ] ) {
			expect( createInput( '  Pick one ', type ) ).toEqual( {
				name: 'Pick one',
				settings: { type, score },
				options: [
					{ answer: 'Option 1', is_correct: true, order_number: 0 },
					{ answer: 'Option 2', is_correct: false, order_number: 1 },
				],
			} );
		}
	} );
	it( 'adds True and False for true-false', () => {
		expect( createInput( 'Q', 'true-false' ).options ).toEqual( [
			{ answer: 'True', is_correct: true, order_number: 0 },
			{ answer: 'False', is_correct: false, order_number: 1 },
		] );
	} );
	it( 'adds two unmarked options for reorder and labels for matching', () => {
		expect( createInput( 'Q', 'reorder' ).options ).toEqual( [
			{ answer: 'Option 1', is_correct: false, order_number: 0 },
			{ answer: 'Option 2', is_correct: false, order_number: 1 },
		] );
		expect( createInput( 'Q', 'matching' ).options ).toEqual( [
			{
				answer: 'Option 1',
				is_correct: false,
				order_number: 0,
				matching_data: { label: 'Match 1' },
			},
			{
				answer: 'Option 2',
				is_correct: false,
				order_number: 1,
				matching_data: { label: 'Match 2' },
			},
		] );
	} );
	it( 'sends only settings for every other type', () => {
		expect( createInput( 'Q', 'graded-reader' ) ).toEqual( {
			name: 'Q',
			settings: { type: 'graded-reader', score },
		} );
	} );
} );

describe( 'form state', () => {
	it( 'starts from the question with options in order', () => {
		const reversed = formFromQuestion( {
			...question,
			options: [ ...question.options ].reverse(),
		} );
		expect( reversed.options.map( ( item ) => item.answer ) ).toEqual( [
			'3',
			'12',
		] );
		expect( saved ).toMatchObject( {
			name: 'Solve 2x = 6',
			points: '2',
			difficulty: 'easy',
		} );
		expect(
			formFromQuestion( {
				...question,
				settings: { type: 'single-choice', score: { enabled: false } },
			} ).points
		).toBe( '' );
	} );
	it( 'detects changes', () => {
		expect( isDirty( saved, formFromQuestion( question ) ) ).toBe( false );
		expect( isDirty( { ...saved, name: 'Other' }, saved ) ).toBe( true );
	} );
	it( 'moves options and keeps one correct answer for single choice', () => {
		const options = [ option( 'a', true ), option( 'b' ), option( 'c' ) ];
		expect(
			moveOption( options, 0, 1 ).map( ( item ) => item.answer )
		).toEqual( [ 'b', 'a', 'c' ] );
		expect( moveOption( options, 0, -1 ) ).toEqual( options );
		expect(
			setCorrect( options, 2, true, 'single-choice' ).map(
				( item ) => item.is_correct
			)
		).toEqual( [ false, false, true ] );
		expect(
			setCorrect( options, 2, true, 'multiple-choice' ).map(
				( item ) => item.is_correct
			)
		).toEqual( [ true, false, true ] );
		expect( newOption( 'matching' ).matching ).toEqual( { label: '' } );
		expect( newOption( 'reorder' ).key ).not.toBe(
			newOption( 'reorder' ).key
		);
	} );
} );

describe( 'validateForm', () => {
	it( 'accepts the loaded question', () => {
		expect( errorsFor( {} ) ).toEqual( {} );
	} );
	it( 'requires a name of at most 500 characters', () => {
		expect( errorsFor( { name: '  ' } ) ).toHaveProperty( 'name' );
		expect( errorsFor( { name: 'x'.repeat( 501 ) } ) ).toHaveProperty(
			'name'
		);
	} );
	it( 'requires zero or more points once the question is scored', () => {
		expect( errorsFor( { points: '' } ) ).toHaveProperty( 'points' );
		expect( errorsFor( { points: '-1' } ) ).toHaveProperty( 'points' );
		expect( errorsFor( { points: '1.5' } ) ).toEqual( {} );
		const unscored = { ...saved, points: '' };
		expect( validateForm( unscored, unscored, 'single-choice' ) ).toEqual(
			{}
		);
	} );
	it( 'needs two filled options', () => {
		expect(
			errorsFor( {
				options: [ option( 'a', true ), option( ' <b></b> ' ) ],
			} )
		).toEqual( { options: 'Add at least two answer options.' } );
	} );
	it( 'needs exactly one correct option for single choice and true-false', () => {
		const none = [ option( 'a' ), option( 'b' ) ];
		const both = [ option( 'a', true ), option( 'b', true ) ];
		expect( errorsFor( { options: none } ).options ).toBe(
			'Mark exactly one option as correct.'
		);
		expect( errorsFor( { options: both }, 'true-false' ).options ).toBe(
			'Mark exactly one option as correct.'
		);
	} );
	it( 'needs exactly two options for true-false', () => {
		expect(
			errorsFor(
				{
					options: [
						option( 'a', true ),
						option( 'b' ),
						option( 'c' ),
					],
				},
				'true-false'
			).options
		).toBe( 'A true/false question has exactly two options.' );
	} );
	it( 'needs a correct option for multiple choice but none for reorder', () => {
		const none = [ option( 'a' ), option( 'b' ) ];
		expect(
			errorsFor( { options: none }, 'multiple-choice' ).options
		).toBe( 'Mark at least one option as correct.' );
		expect( errorsFor( { options: none }, 'reorder' ) ).toEqual( {} );
	} );
	it( 'needs a label for every matching option', () => {
		const options = [
			{ ...option( 'a' ), matching: { label: 'A' } },
			{ ...option( 'b' ), matching: { label: ' ' } },
		];
		expect( errorsFor( { options }, 'matching' ) ).toEqual( {
			'options.1.label': 'Enter the matching answer for this option.',
		} );
	} );
	it( 'ignores options of types this editor does not edit', () => {
		expect( errorsFor( { options: [] }, '' ) ).toEqual( {} );
	} );
} );

describe( 'toInput', () => {
	const base = question.modified;
	it( 'sends only base_modified when nothing changed', () => {
		expect( toInput( saved, saved, 'single-choice', base ) ).toEqual( {
			base_modified: base,
		} );
	} );
	it( 'sends changed text fields and difficulty only', () => {
		expect(
			toInput(
				{
					...saved,
					name: ' Solve 2x = 8 ',
					description: '<p>New</p>',
					difficulty: 'challenge',
				},
				saved,
				'single-choice',
				base
			)
		).toEqual( {
			base_modified: base,
			name: 'Solve 2x = 8',
			description: '<p>New</p>',
			bank: { difficulty: 'challenge' },
		} );
	} );
	it( 'sends settings with the type and score when points change', () => {
		expect(
			toInput( { ...saved, points: '5' }, saved, 'single-choice', base )
		).toEqual( {
			base_modified: base,
			settings: {
				type: 'single-choice',
				score: { enabled: true, value: 5 },
			},
		} );
	} );
	it( 'sends the whole option set in order when an option changes', () => {
		const options = moveOption( saved.options, 0, 1 );
		expect(
			toInput( { ...saved, options }, saved, 'single-choice', base )
		).toEqual( {
			base_modified: base,
			options: [
				{ id: 3302, answer: '12', is_correct: false, order_number: 0 },
				{ id: 3301, answer: '3', is_correct: true, order_number: 1 },
			],
		} );
	} );
	it( 'sends matching data and image ids of options, never empty ones', () => {
		const options: FormOption[] = [
			{ ...option( 'a' ), image_id: 9, matching: { label: 'A' } },
			{
				...option( 'b' ),
				matching: { label: 'B', image_url: 'https://x.test/b.png' },
			},
		];
		expect(
			toInput( { ...saved, options }, saved, 'matching', base ).options
		).toEqual( [
			{
				answer: 'a',
				is_correct: false,
				order_number: 0,
				image_id: 9,
				matching_data: { label: 'A' },
			},
			{
				answer: 'b',
				is_correct: false,
				order_number: 1,
				matching_data: {
					label: 'B',
					image_url: 'https://x.test/b.png',
				},
			},
		] );
	} );
} );

describe( 'serverFieldError', () => {
	const error = ( code: string, details?: unknown ) =>
		new ApiError( { status: 422, code, message: 'Bad', details } );
	it( 'maps option, name, bank and param errors to fields', () => {
		expect(
			serverFieldError( error( 'ohmylms_question_invalid_name' ) )
		).toEqual( { path: 'name', message: 'Bad' } );
		expect(
			serverFieldError(
				error( 'ohmylms_question_invalid_options', {
					field: 'options',
				} )
			)
		).toEqual( { path: 'options', message: 'Bad' } );
		expect(
			serverFieldError(
				error( 'ohmylms_question_invalid_options', {
					field: 'options[2].matching_data.label',
				} )
			)
		).toEqual( { path: 'options.2.label', message: 'Bad' } );
		expect(
			serverFieldError(
				error( 'rest_invalid_param', {
					params: { bank: 'bank[difficulty] is not one of easy.' },
				} )
			)
		).toEqual( {
			path: 'difficulty',
			message: 'bank[difficulty] is not one of easy.',
		} );
		expect(
			serverFieldError( error( 'ohmylms_invalid_question_settings' ) )
		).toEqual( { path: 'settings', message: 'Bad' } );
	} );
	it( 'returns null for errors about no field', () => {
		expect(
			serverFieldError( error( 'ohmylms_question_conflict' ) )
		).toBeNull();
		expect(
			serverFieldError(
				error( 'rest_invalid_param', { params: { status: 'x' } } )
			)
		).toBeNull();
	} );
} );

describe( 'answer areas and type rules', () => {
	it( 'chooses the answer editor of every kind of type', () => {
		for ( const type of [
			'single-choice',
			'multiple-choice',
			'true-false',
			'reorder',
			'matching',
		] ) {
			expect( answerArea( type, false ) ).toBe( 'options' );
		}
		expect( answerArea( 'statement', false ) ).toBe( 'accepted' );
		expect( answerArea( 'fill-in-the-blank', false ) ).toBe( 'blanks' );
		expect( answerArea( 'short-text', false ) ).toBe( 'manual' );
		expect( answerArea( 'long-text', true ) ).toBe( 'manual' );
		expect( answerArea( 'hotspot', true ) ).toBe( 'extension' );
		expect( answerArea( 'graded-reader', false ) ).toBe( 'none' );
		expect( answerArea( 'example-number', true ) ).toBe( 'extension' );
	} );
	it( 'knows how each type is scored and which shared settings apply', () => {
		expect( scoreMode( 'single-choice' ) ).toBe( 'points' );
		expect( scoreMode( 'structured' ) ).toBe( 'derived' );
		expect( scoreMode( 'passage' ) ).toBe( 'derived' );
		for ( const type of [ 'poll', 'word-cloud', 'slide', 'flashcards' ] ) {
			expect( scoreMode( type ) ).toBe( 'unscored' );
		}
		expect( sharedSettings( 'multiple-choice' ) ).toEqual( {
			required: true,
			randomize: true,
			partialCredit: true,
			feedback: true,
		} );
		expect( sharedSettings( 'matching' ).randomize ).toBe( false );
		expect( sharedSettings( 'fill-in-the-blank' ).partialCredit ).toBe(
			true
		);
		expect( sharedSettings( 'slide' ) ).toMatchObject( {
			required: false,
			feedback: false,
		} );
	} );
	it( 'reads legacy flag values', () => {
		expect( flag( '1' ) ).toBe( true );
		expect( flag( 1 ) ).toBe( true );
		expect( flag( '0' ) ).toBe( false );
		expect( flag( '' ) ).toBe( false );
		expect( flag( undefined ) ).toBe( false );
		expect( flag( undefined, true ) ).toBe( true );
	} );
	it( 'finds the blanks in a name as the server does', () => {
		expect( inlineBlanks( 'The {cat} sat on the {mat}.' ) ).toEqual( [
			'cat',
			'mat',
		] );
		expect( inlineBlanks( 'Empty {  } and <b>{x}</b>' ) ).toEqual( [
			'x',
		] );
		expect( inlineBlanks( 'No blanks' ) ).toEqual( [] );
	} );
	it( 'maps skills per part and normalizes the map', () => {
		expect( skillParts( { type: 'single-choice' } ) ).toEqual( [
			{ key: 'p1', label: '' },
		] );
		expect(
			skillParts( {
				parts: [
					{ id: 'a', label: '(a)' },
					{ id: 'b' },
					{ id: 'bad id' },
				],
			} )
		).toEqual( [
			{ key: 'a', label: '(a)' },
			{ key: 'b', label: 'b' },
		] );
		expect(
			normalizeSkills( {
				p2: { primary: 0, supporting: [] },
				p1: { primary: 5, supporting: [ 9, 5, 3, 9 ] },
			} )
		).toEqual( { p1: { primary: 5, supporting: [ 3, 9 ] } } );
	} );
	it( 'seeds unscored drafts with zero points and statements with one expected answer', () => {
		expect( draftQuestion( 'Hi', 'slide' ).settings.score ).toEqual( {
			enabled: true,
			value: 0,
		} );
		expect( draftQuestion( 'Hi', 'poll' ).settings.score ).toEqual( {
			enabled: true,
			value: 0,
		} );
		expect( createInput( 'Hi', 'slide' ).settings.score ).toEqual( {
			enabled: true,
			value: 0,
		} );
		expect(
			draftQuestion( 'Hi', 'statement' ).options.map( ( item ) => [
				item.answer,
				item.is_correct,
			] )
		).toEqual( [ [ '', true ] ] );
		expect( newOption( 'statement' ).is_correct ).toBe( true );
		expect( newOption( 'fill-in-the-blank' ).is_correct ).toBe( true );
		expect( newOption( 'reorder' ).is_correct ).toBe( false );
	} );
	it( 'keeps unscored and multi-part points out of the points field', () => {
		const poll = {
			...formFromQuestion( draftQuestion( 'P', 'poll' ) ),
			points: '3',
		};
		expect( settingsFor( poll, 'poll' ).score ).toEqual( {
			enabled: true,
			value: 0,
		} );
		const parts = {
			...formFromQuestion( draftQuestion( 'S', 'structured' ) ),
			points: '9',
			settings: {
				type: 'structured',
				score: { enabled: true, value: 4 },
			},
		};
		expect( settingsFor( parts, 'structured' ).score ).toEqual( {
			enabled: true,
			value: 4,
		} );
		expect(
			validateForm( { ...parts, points: 'x' }, parts, 'structured' )
		).toEqual( {} );
	} );
} );

describe( 'validateForm for typed answers and shared fields', () => {
	const statement = formFromQuestion( draftQuestion( 'Q', 'statement' ) );
	it( 'needs every expected answer of a statement', () => {
		expect(
			validateForm( statement, statement, 'statement', 'accepted' )
		).toEqual( { 'options.0.answer': 'Enter the expected answer.' } );
		expect(
			validateForm(
				{ ...statement, options: [] },
				statement,
				'statement',
				'accepted'
			)
		).toMatchObject( { options: 'Add at least one expected answer.' } );
		expect(
			validateForm(
				{ ...statement, options: [ option( '4', true ) ] },
				statement,
				'statement',
				'accepted'
			)
		).toEqual( {} );
	} );
	it( 'needs blanks in the name or an accepted answer for fill-in-the-blank', () => {
		const blank = formFromQuestion(
			draftQuestion( 'Capital', 'fill-in-the-blank' )
		);
		expect(
			validateForm( blank, blank, 'fill-in-the-blank', 'blanks' )
		).toHaveProperty( 'options' );
		expect(
			validateForm(
				{ ...blank, name: 'The capital is {Paris}' },
				blank,
				'fill-in-the-blank',
				'blanks'
			)
		).toEqual( {} );
		expect(
			validateForm(
				{ ...blank, options: [ option( 'Paris', true ) ] },
				blank,
				'fill-in-the-blank',
				'blanks'
			)
		).toEqual( {} );
	} );
	it( 'bounds points, source, hint, explanation and supporting skills', () => {
		expect( errorsFor( { points: '10001' } ) ).toHaveProperty( 'points' );
		expect( errorsFor( { points: '10000' } ) ).toEqual( {} );
		expect( errorsFor( { source: 'x'.repeat( 101 ) } ) ).toHaveProperty(
			'source'
		);
		expect(
			errorsFor( {
				settings: {
					...saved.settings,
					hint: 'x'.repeat( 65536 ),
					explanation: 'ok',
				},
			} )
		).toEqual( { hint: 'Use at most 65535 characters.' } );
		expect(
			errorsFor( {
				skills: {
					p1: {
						primary: 1,
						supporting: Array.from(
							{ length: 51 },
							( _, i ) => i + 2
						),
					},
				},
			} )
		).toHaveProperty( 'skills.p1' );
	} );
} );

describe( 'writes of the new fields', () => {
	const base = question.modified;
	it( 'sends only changed bank keys, media and skills on update', () => {
		expect(
			toInput(
				{
					...saved,
					source: 'Workbook',
					secure: true,
					bankId: 4,
					image: { id: 9, url: 'https://x.test/9.png' },
					skills: { p1: { primary: 41, supporting: [ 8 ] } },
				},
				saved,
				'single-choice',
				base
			)
		).toEqual( {
			base_modified: base,
			bank: { bank_id: 4, source: 'Workbook', secure: true },
			thumbnail_id: 9,
			skills: { p1: { primary: 41, supporting: [ 8 ] } },
		} );
		expect(
			toInput(
				{ ...saved, video: { id: 0, url: '' }, skills: {} },
				{ ...saved, video: { id: 3, url: 'v' } },
				'single-choice',
				base
			)
		).toEqual( { base_modified: base, video_id: 0, skills: {} } );
	} );
	it( 'sends the options of statement and fill-in-the-blank but not of settings types', () => {
		const accepted = [ option( 'Paris', true ) ];
		expect(
			toInput(
				{ ...saved, options: accepted },
				saved,
				'fill-in-the-blank',
				base,
				'blanks'
			).options
		).toEqual( [ { answer: 'Paris', is_correct: true, order_number: 0 } ] );
		expect(
			toInput(
				{ ...saved, options: accepted },
				saved,
				'numerical',
				base,
				'extension'
			)
		).not.toHaveProperty( 'options' );
	} );
	it( 'creates with bank fields, media and skills', () => {
		const form = {
			...formFromQuestion( draftQuestion( 'Q', 'statement' ) ),
			options: [ option( '4', true ) ],
			source: ' Book ',
			secure: true,
			bankId: 2,
			image: { id: 5, url: 'i' },
			video: { id: 6, url: 'v' },
			skills: { p1: { primary: 3, supporting: [] } },
			settings: {
				type: 'statement',
				score: { enabled: true, value: 1 },
				hint: 'Count',
			},
		};
		expect( toCreateInput( form, 'statement', 'accepted' ) ).toEqual( {
			name: 'Q',
			settings: {
				type: 'statement',
				score: { enabled: true, value: 1 },
				hint: 'Count',
			},
			bank: {
				difficulty: 'standard',
				bank_id: 2,
				source: 'Book',
				secure: true,
			},
			options: [ { answer: '4', is_correct: true, order_number: 0 } ],
			skills: { p1: { primary: 3, supporting: [] } },
			thumbnail_id: 5,
			video_id: 6,
		} );
	} );
	it( 'maps the new server rejections to their fields', () => {
		const error = ( code: string, details?: unknown ) =>
			new ApiError( { status: 422, code, message: 'Bad', details } );
		expect(
			serverFieldError(
				error( 'ohmylms_question_invalid_settings', {
					field: 'settings.hint',
				} )
			)
		).toEqual( { path: 'hint', message: 'Bad' } );
		expect(
			serverFieldError(
				error( 'ohmylms_question_invalid_settings', {
					field: 'settings.score',
				} )
			)
		).toEqual( { path: 'points', message: 'Bad' } );
		expect(
			serverFieldError(
				error( 'ohmylms_question_invalid_settings', {
					field: 'settings.blank_mode',
				} )
			)
		).toEqual( { path: 'settings', message: 'Bad' } );
		expect(
			serverFieldError(
				error( 'ohmylms_question_invalid_options', {
					field: 'options[1].answer',
				} )
			)
		).toEqual( { path: 'options.1.answer', message: 'Bad' } );
		expect(
			serverFieldError(
				error( 'ohmylms_question_invalid_media', {
					field: 'thumbnail_id',
				} )
			)
		).toEqual( { path: 'image', message: 'Bad' } );
		expect( serverFieldError( error( 'ohmylms_skill_missing' ) ) ).toEqual(
			{ path: 'skills', message: 'Bad' }
		);
		expect( serverFieldError( error( 'ohmylms_forbidden' ) ) ).toEqual( {
			path: 'bank',
			message: 'Bad',
		} );
	} );
	it( 'parses skills, banks and the new editor view fields', () => {
		expect( parseSkillRef( { id: 3, name: 'Fractions' } ) ).toEqual( {
			id: 3,
			name: 'Fractions',
			code: '',
		} );
		expect(
			parseBanks( {
				banks: [ { id: '4', name: 'Shared', owner_id: '2' } ],
			} )
		).toEqual( [ { id: 4, name: 'Shared' } ] );
		expect( () => parseBanks( [] ) ).toThrow( ApiError );
		const detail = parseQuestionDetail( {
			...question,
			thumbnail_url: 'https://x.test/a.png',
			skills: { p1: { primary: 41, supporting: [ 3 ] } },
		} );
		expect( detail.thumbnail_url ).toBe( 'https://x.test/a.png' );
		expect( detail.skills ).toEqual( {
			p1: { primary: 41, supporting: [ 3 ] },
		} );
		const { thumbnail_url: _image, video_url: _video, ...older } = question;
		expect( parseQuestionDetail( older ).video_url ).toBe( '' );
		expect( () =>
			parseQuestionDetail( {
				...question,
				skills: { p1: { primary: 'x' } },
			} )
		).toThrow( ApiError );
	} );
} );
