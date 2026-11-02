import {
	buildQuery,
	emptyDraft,
	parseRouteId,
	parseSession,
	parseSummary,
	toPayload,
	validateDraft,
} from './model';
import { sessionBody } from './fixtures';

describe( 'session parsing', () => {
	it( 'accepts the documented shape and drops unknown fields', () => {
		const session = parseSession( { ...sessionBody, start_url: 'x' } );
		expect( session.course ).toEqual( { id: 40, title: 'Algebra' } );
		expect( session ).not.toHaveProperty( 'start_url' );
	} );

	it( 'rejects unknown enums and non-https join links', () => {
		expect( () =>
			parseSummary( { ...sessionBody, provider: 'teams' } )
		).toThrow();
		expect( () =>
			parseSummary( {
				...sessionBody,
				meeting: { created: true, join_url: 'javascript:alert(1)' },
			} )
		).toThrow();
	} );
} );

describe( 'session drafts', () => {
	it( 'validates title, start, duration and Zoom passcodes', () => {
		const draft = {
			...emptyDraft(),
			title: ' ',
			start: '2030-02-30T10:00',
			duration: 2,
			password: 'bad pass!',
		};
		expect( Object.keys( validateDraft( draft ) ).sort() ).toEqual( [
			'duration',
			'password',
			'start',
			'title',
		] );
		expect(
			validateDraft( {
				...emptyDraft(),
				title: 'Review',
				start: '2030-03-04T10:00',
			} )
		).toEqual( {} );
	} );

	it( 'never sends a passcode for Google Meet or a provider on update', () => {
		const draft = {
			...emptyDraft(),
			provider: 'googlemeet' as const,
			password: 'abc',
			title: ' Review ',
			start: '2030-03-04T10:00',
		};
		expect( toPayload( draft, false ) ).toMatchObject( {
			title: 'Review',
			provider: 'googlemeet',
			password: '',
		} );
		expect( toPayload( draft, true ) ).not.toHaveProperty( 'provider' );
	} );

	it( 'builds bounded queries and parses route ids', () => {
		expect(
			buildQuery( { page: 2, search: ' ', provider: 'zoom' } )
		).toEqual( { page: 2, per_page: 20, provider: 'zoom' } );
		expect( parseRouteId( 'new' ) ).toBe( 'new' );
		expect( parseRouteId( '12' ) ).toBe( 12 );
		expect( parseRouteId( '0' ) ).toBeNull();
		expect( parseRouteId( 'x' ) ).toBeNull();
	} );
} );
