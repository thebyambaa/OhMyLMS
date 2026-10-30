import { ApiError } from '../../lib/api';
import { everyStepDone, rawState } from './fixtures';
import {
	FINISH,
	canComplete,
	describeFailure,
	isCompleted,
	nextStep,
	parseState,
	parseStateResponse,
	parseStepResponse,
	parseTopics,
	pendingSteps,
	previousStep,
	progressOf,
	resolveStep,
	validateExperience,
	validateNiche,
	validatePreferences,
} from './model';

describe( 'parseState', () => {
	it( 'maps the server state to the typed state', () => {
		const state = parseState( rawState( { welcome: 'done' } ) );
		expect( state.currentStep ).toBe( 'experience' );
		expect( state.steps ).toHaveLength( 6 );
		expect( state.starterPages[ 1 ] ).toMatchObject( {
			status: 'adoptable',
			page_id: 9,
		} );
		expect( state.preferences.currency ).toBe( 'USD' );
	} );

	it( 'accepts a finished wizard with no current step', () => {
		const state = parseState(
			rawState( everyStepDone, { status: 'completed' } )
		);
		expect( state.currentStep ).toBeNull();
		expect( isCompleted( state ) ).toBe( true );
	} );

	it.each( [
		[ 'not an object', 'x' ],
		[ 'unknown status', rawState( {}, { status: 'weird' } ) ],
		[ 'unknown step', rawState( {}, { current_step: 'nope' } ) ],
		[ 'bad answers', rawState( {}, { answers: { experience: 'guru' } } ) ],
		[ 'steps not a list', rawState( {}, { steps: {} } ) ],
		[
			'page without status',
			rawState( {}, { starter_pages: [ { key: 'a' } ] } ),
		],
		[ 'version not a number', rawState( {}, { version: 'one' } ) ],
	] )( 'rejects %s', ( _label, raw ) => {
		expect( () => parseState( raw ) ).toThrow( ApiError );
		try {
			parseState( raw );
		} catch ( error ) {
			expect( error ).toMatchObject( { code: 'invalid_response' } );
		}
	} );

	it( 'validates wrapped responses', () => {
		expect(
			parseStepResponse( {
				state: rawState(),
				result: {
					changed: [ 'currency' ],
					pages: [ { key: 'course', page_id: 1, action: 'kept' } ],
				},
			} ).result.pages[ 0 ]
		).toMatchObject( { action: 'kept' } );
		expect( () =>
			parseStepResponse( { state: rawState(), result: { changed: [] } } )
		).toThrow( ApiError );
		expect( parseStateResponse( { state: rawState() } ).version ).toBe( 1 );
		expect( () => parseStateResponse( {} ) ).toThrow( ApiError );
		expect( () =>
			parseStepResponse( {
				state: rawState(),
				result: {
					changed: [],
					pages: [ { key: 'a', page_id: 1, action: 'burn' } ],
				},
			} )
		).toThrow( ApiError );
	} );
} );

describe( 'step state machine', () => {
	const fresh = parseState( rawState() );

	it( 'follows the URL, else the first open step, else the finish screen', () => {
		expect( resolveStep( null, fresh ) ).toBe( 'welcome' );
		expect( resolveStep( 'pages', fresh ) ).toBe( 'pages' );
		expect( resolveStep( 'finish', fresh ) ).toBe( FINISH );
		expect( resolveStep( 'bogus', fresh ) ).toBe( 'welcome' );
		const done = parseState( rawState( everyStepDone ) );
		expect( resolveStep( null, done ) ).toBe( FINISH );
	} );

	it( 'moves forward and back through the screens', () => {
		expect( nextStep( 'welcome' ) ).toBe( 'experience' );
		expect( nextStep( 'import' ) ).toBe( FINISH );
		expect( nextStep( FINISH ) ).toBeNull();
		expect( previousStep( 'welcome' ) ).toBeNull();
		expect( previousStep( FINISH ) ).toBe( 'import' );
		expect( previousStep( 'niche' ) ).toBe( 'preferences' );
	} );

	it( 'counts progress and allows completion only when nothing is pending', () => {
		expect( progressOf( fresh ) ).toEqual( { resolved: 0, total: 6 } );
		expect( canComplete( fresh ) ).toBe( false );
		const partial = parseState(
			rawState( { welcome: 'done', import: 'skipped' } )
		);
		expect( progressOf( partial ).resolved ).toBe( 2 );
		expect( pendingSteps( partial ) ).toEqual( [
			'experience',
			'preferences',
			'niche',
			'pages',
		] );
		const done = parseState( rawState( everyStepDone ) );
		expect( canComplete( done ) ).toBe( true );
		expect( pendingSteps( done ) ).toEqual( [] );
	} );
} );

describe( 'input validation (mirrors the server schema)', () => {
	it( 'validates experience', () => {
		expect( validateExperience( 'beginner' ) ).toEqual( {} );
		expect( validateExperience( '' ) ).toHaveProperty( 'experience' );
	} );

	it( 'validates preferences', () => {
		const ok = { currency: 'EUR', position: 'right', decimals: '2' };
		expect( validatePreferences( ok ) ).toEqual( {} );
		expect(
			validatePreferences( { ...ok, currency: 'eur' } )
		).toHaveProperty( 'currency' );
		expect(
			validatePreferences( { ...ok, position: 'mid' } )
		).toHaveProperty( 'currency_pos' );
		for ( const decimals of [ '', '9', '-1', '1.5', 'x' ] ) {
			expect( validatePreferences( { ...ok, decimals } ) ).toHaveProperty(
				'price_num_decimals'
			);
		}
		expect( validatePreferences( { ...ok, decimals: '0' } ) ).toEqual( {} );
		expect( validatePreferences( { ...ok, decimals: '8' } ) ).toEqual( {} );
	} );

	it( 'parses topics into unique, trimmed names', () => {
		expect( parseTopics( ' Algebra\n,algebra, Geometry ,\n\n' ) ).toEqual( [
			'Algebra',
			'Geometry',
		] );
		expect( parseTopics( '' ) ).toEqual( [] );
	} );

	it( 'validates niche and topics', () => {
		expect( validateNiche( 'other', [] ) ).toEqual( {} );
		expect( validateNiche( '', [] ) ).toHaveProperty( 'niche' );
		expect(
			validateNiche(
				'other',
				Array.from( { length: 11 }, ( _, i ) => `t${ i }` )
			)
		).toHaveProperty( 'topics' );
		expect( validateNiche( 'other', [ 'x'.repeat( 61 ) ] ) ).toHaveProperty(
			'topics'
		);
		expect( validateNiche( 'other', [ 'x'.repeat( 60 ) ] ) ).toEqual( {} );
	} );
} );

describe( 'describeFailure', () => {
	it( 'extracts field messages and flags permission problems', () => {
		const view = describeFailure(
			new ApiError( {
				status: 403,
				code: 'ohmylms_setup_cannot_manage',
				message: 'No.',
				details: { params: { currency: 'Bad', n: 3 } },
			} )
		);
		expect( view ).toEqual( {
			message: 'No.',
			permission: true,
			fieldErrors: { currency: 'Bad' },
		} );
		expect(
			describeFailure(
				new ApiError( { status: 500, code: 'x', message: 'm' } )
			).permission
		).toBe( false );
	} );
} );
