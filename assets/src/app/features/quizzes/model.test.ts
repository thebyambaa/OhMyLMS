import { ApiError } from '../../lib/api';
import { attempt, attemptDetail, quiz, summary } from './fixtures';
import {
	buildGrades,
	draftsFromQuestions,
	formFromQuiz,
	fromLocalInput,
	hasGradeChanges,
	isDirty,
	isValid,
	parseAttempt,
	parseAttemptDetail,
	parseGradeResult,
	parseMarks,
	parseQuiz,
	parseQuizDetail,
	previewTotal,
	serverFieldError,
	toInput,
	toLocalInput,
	validateForm,
	wholeNumber,
} from './model';
import type { QuizForm } from './types';

const form = formFromQuiz( quiz );

describe( 'response parsers', () => {
	it( 'accept the documented shapes', () => {
		expect( parseQuiz( summary ) ).toEqual( summary );
		expect( parseQuizDetail( quiz ) ).toEqual( quiz );
		expect( parseAttempt( attempt ) ).toEqual( attempt );
		expect( parseAttemptDetail( attemptDetail ) ).toEqual( attemptDetail );
		expect(
			parseGradeResult( { ...attemptDetail, changed: 1 } ).changed
		).toBe( 1 );
	} );
	it( 'treat a missing editor link as none', () => {
		const { native_editor_url: ignored, ...rest } = summary;
		expect( ignored ).toBeTruthy();
		expect( parseQuiz( rest ).native_editor_url ).toBeNull();
	} );
	it.each( [
		[ 'a string id', { ...summary, id: '11' } ],
		[ 'an unknown status', { ...summary, status: 'archived' } ],
		[ 'a missing count', { ...summary, attempt_count: undefined } ],
		[ 'a non boolean flag', { ...summary, can_grade: 'yes' } ],
		[ 'a null body', null ],
		[ 'an array body', [] ],
	] )( 'reject %s', ( _name, value ) => {
		expect( () => parseQuiz( value ) ).toThrowError(
			expect.objectContaining( { code: 'invalid_response' } )
		);
	} );
	it( 'reject attempts with bad students, statuses or questions', () => {
		expect( () =>
			parseAttempt( {
				...attempt,
				student: { id: 1, name: 5, email: '' },
			} )
		).toThrowError(
			expect.objectContaining( { code: 'invalid_response' } )
		);
		expect( () =>
			parseAttempt( { ...attempt, status: 'graded' } )
		).toThrow();
		expect( () =>
			parseAttemptDetail( { ...attemptDetail, questions: [ { id: 1 } ] } )
		).toThrow();
		expect( () => parseGradeResult( attemptDetail ) ).toThrow();
	} );
	it( 'reject settings outside the contract', () => {
		expect( () =>
			parseQuizDetail( {
				...quiz,
				settings: { ...quiz.settings, layout: 'grid' },
			} )
		).toThrow();
		expect( () =>
			parseQuizDetail( {
				...quiz,
				settings: { ...quiz.settings, feedback_mode: 'never' },
			} )
		).toThrow();
	} );
} );

describe( 'dates', () => {
	it( 'round trip between UTC and the local input value', () => {
		const local = toLocalInput( '2026-12-01T09:00:00Z' );
		expect( local ).toMatch( /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/ );
		expect( fromLocalInput( local ) ).toBe( '2026-12-01T09:00:00Z' );
	} );
	it( 'handle empty and invalid values', () => {
		expect( toLocalInput( null ) ).toBe( '' );
		expect( toLocalInput( 'nonsense' ) ).toBe( '' );
		expect( fromLocalInput( '' ) ).toBeNull();
		expect( fromLocalInput( 'tomorrow' ) ).toBeUndefined();
		expect( fromLocalInput( '2026-13-45T10:00' ) ).toBeUndefined();
	} );
} );

describe( 'settings form', () => {
	it( 'starts from the stored quiz and detects changes', () => {
		expect( form.time_limit_minutes ).toBe( '30' );
		expect( form.available_from ).toBe( '' );
		expect( isDirty( form, form ) ).toBe( false );
		expect( isDirty( { ...form, title: 'Other' }, form ) ).toBe( true );
	} );
	it( 'accepts a valid form', () => {
		expect( validateForm( form ) ).toEqual( {} );
		expect( isValid( {} ) ).toBe( true );
	} );
	it.each( [
		[ 'title', { title: '   ' } ],
		[ 'title', { title: 'x'.repeat( 201 ) } ],
		[ 'time_limit_minutes', { time_limit_minutes: '-1' } ],
		[ 'time_limit_minutes', { time_limit_minutes: '10081' } ],
		[ 'time_limit_minutes', { time_limit_minutes: '1.5' } ],
		[ 'passing_score', { passing_score: '101' } ],
		[ 'passing_score', { passing_score: '-5' } ],
		[ 'passing_score', { passing_score: 'abc' } ],
		[ 'attempts_allowed', { attempts_allowed: '-1' } ],
		[ 'attempts_allowed', { attempts_allowed: '1001' } ],
		[ 'questions_per_page', { questions_per_page: '0' } ],
		[ 'available_from', { available_from: 'soon' } ],
	] )( 'flags %s', ( field, change ) => {
		const errors = validateForm( { ...form, ...change } as QuizForm );
		expect( Object.keys( errors ) ).toContain( field );
		expect( isValid( errors ) ).toBe( false );
	} );
	it( 'requires the closing time to be after the opening time', () => {
		const errors = validateForm( {
			...form,
			available_from: '2026-12-02T10:00',
			available_until: '2026-12-01T10:00',
		} );
		expect( errors.available_until ).toMatch( /after the opening time/ );
		expect(
			validateForm( {
				...form,
				available_from: '2026-12-01T10:00',
				available_until: '2026-12-02T10:00',
			} )
		).toEqual( {} );
	} );
	it( 'accepts the boundary values', () => {
		expect(
			validateForm( {
				...form,
				time_limit_minutes: '0',
				passing_score: '0',
				attempts_allowed: '0',
			} )
		).toEqual( {} );
		expect(
			validateForm( {
				...form,
				time_limit_minutes: '10080',
				passing_score: '100',
				attempts_allowed: '1000',
			} )
		).toEqual( {} );
	} );
	it( 'builds the request body from a valid form', () => {
		const input = toInput(
			{ ...form, title: '  New title ', passing_score: '75.5' },
			'2026-10-30T09:30:00Z'
		);
		expect( input.title ).toBe( 'New title' );
		expect( input.base_modified ).toBe( '2026-10-30T09:30:00Z' );
		expect( input.settings ).toMatchObject( {
			time_limit_minutes: 30,
			passing_score: 75.5,
			attempts_allowed: 2,
			available_from: null,
		} );
	} );
	it( 'refuses to build a body from an invalid form', () => {
		expect( () =>
			toInput( { ...form, attempts_allowed: 'x' }, '' )
		).toThrow();
	} );
	it( 'parses whole numbers strictly', () => {
		expect( wholeNumber( ' 12 ', 0, 20 ) ).toBe( 12 );
		expect( wholeNumber( '21', 0, 20 ) ).toBeNull();
		expect( wholeNumber( '1e3', 0, 2000 ) ).toBeNull();
		expect( wholeNumber( '', 0, 20 ) ).toBeNull();
	} );
	it( 'maps server errors to fields', () => {
		const known = new ApiError( {
			status: 422,
			code: 'ohmylms_quiz_invalid_availability',
			message: 'The closing time must be after the opening time.',
		} );
		expect( serverFieldError( known ) ).toEqual( {
			field: 'available_until',
			message: known.message,
		} );
		expect(
			serverFieldError(
				new ApiError( { status: 500, code: 'other', message: 'x' } )
			)
		).toBeNull();
	} );
} );

describe( 'grading', () => {
	const questions = attemptDetail.questions;
	it( 'parses marks within the maximum', () => {
		expect( parseMarks( '2', 4 ) ).toEqual( { marks: 2 } );
		expect( parseMarks( ' 1.5 ', 4 ) ).toEqual( { marks: 1.5 } );
		expect( parseMarks( '4', 4 ) ).toEqual( { marks: 4 } );
		expect( parseMarks( '0', 4 ) ).toEqual( { marks: 0 } );
	} );
	it( 'rejects marks above the maximum and malformed marks', () => {
		expect( parseMarks( '4.1', 4 ) ).toEqual( {
			error: 'Marks cannot be more than 4.',
		} );
		for ( const bad of [ '', '-1', 'abc', '1,5', '1e2', '1.23456' ] ) {
			expect( parseMarks( bad, 4 ) ).toHaveProperty( 'error' );
		}
	} );
	it( 'starts pending questions empty and graded ones with their marks', () => {
		const drafts = draftsFromQuestions( questions );
		expect( drafts[ 101 ]?.total ).toBe( '2' );
		expect( drafts[ 102 ]?.total ).toBe( '' );
	} );
	it( 'sends nothing for untouched drafts', () => {
		const drafts = draftsFromQuestions( questions );
		expect( buildGrades( questions, drafts ) ).toEqual( {
			grades: [],
			errors: {},
		} );
		expect( hasGradeChanges( questions, drafts ) ).toBe( false );
	} );
	it( 'sends a pending question once it has marks, including zero', () => {
		const drafts = draftsFromQuestions( questions );
		drafts[ 102 ] = { total: '0', parts: {} };
		expect( buildGrades( questions, drafts ).grades ).toEqual( [
			{ question_id: 102, marks: 0 },
		] );
	} );
	it( 'sends changed marks only', () => {
		const drafts = draftsFromQuestions( questions );
		drafts[ 101 ] = { total: '1', parts: {} };
		drafts[ 102 ] = { total: '3.5', parts: {} };
		expect( buildGrades( questions, drafts ).grades ).toEqual( [
			{ question_id: 101, marks: 1 },
			{ question_id: 102, marks: 3.5 },
		] );
	} );
	it( 'reports bounds errors per question and sends no grade for them', () => {
		const drafts = draftsFromQuestions( questions );
		drafts[ 102 ] = { total: '5', parts: {} };
		const result = buildGrades( questions, drafts );
		expect( result.grades ).toEqual( [] );
		expect( result.errors[ 102 ] ).toMatch( /more than 4/ );
		expect( hasGradeChanges( questions, drafts ) ).toBe( true );
	} );
	it( 'grades structured questions by part', () => {
		const structured = [
			{
				...questions[ 1 ]!,
				parts: [
					{
						id: 'a',
						kind: 'written',
						max: 2,
						awarded: null,
						grader: null,
					},
					{
						id: 'b',
						kind: 'auto',
						max: 2,
						awarded: 2,
						grader: 'auto',
					},
				],
			},
		];
		const drafts = draftsFromQuestions( structured );
		expect( drafts[ 102 ]?.parts ).toEqual( { a: '', b: '' } );
		drafts[ 102 ] = { total: '', parts: { a: '1.5', b: '' } };
		expect( buildGrades( structured, drafts ).grades ).toEqual( [
			{ question_id: 102, marks: { a: 1.5 } },
		] );
		drafts[ 102 ] = { total: '', parts: { a: '3', b: '' } };
		expect( buildGrades( structured, drafts ).errors[ 102 ] ).toMatch(
			/more than 2/
		);
	} );
	it( 'previews the total with the typed marks', () => {
		const drafts = draftsFromQuestions( questions );
		expect( previewTotal( questions, drafts ) ).toBe( 2 );
		drafts[ 102 ] = { total: '3', parts: {} };
		expect( previewTotal( questions, drafts ) ).toBe( 5 );
		drafts[ 102 ] = { total: '99', parts: {} };
		expect( previewTotal( questions, drafts ) ).toBe( 2 );
	} );
} );
