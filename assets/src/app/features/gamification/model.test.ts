import { ApiError } from '../../lib/api';
import {
	bronze,
	lessonRule,
	options,
	points,
	quizRule,
	silver,
	starter,
	at,
} from './fixtures';
import {
	badgePayload,
	badgeToDraft,
	buildPayload,
	describeCriterion,
	describeFailure,
	isDirty,
	levelPayload,
	levelToDraft,
	parseBadge,
	parseLevel,
	parseMedia,
	parseOptions,
	parseSection,
	tabFromParam,
	toDraft,
	toNumber,
	validateBadge,
	validateLeaderboard,
	validateLevel,
	validatePoints,
} from './model';
import type { PointsDraft } from './types';

const wire = ( value: unknown ) => JSON.parse( JSON.stringify( value ) );

describe( 'parsing', () => {
	it( 'accepts valid sections and rejects malformed ones', () => {
		expect( parseSection( 'points', wire( points ) ) ).toEqual( points );
		expect( parseSection( 'levels', { enabled: true } ) ).toEqual( {
			enabled: true,
		} );
		expect(
			parseSection( 'rewards', {
				enabled: false,
				purchase_with_points: true,
				revision: '0123456789abcdef',
			} )
		).toEqual( {
			enabled: false,
			purchase_with_points: true,
			revision: '0123456789abcdef',
		} );
		expect( () =>
			parseSection( 'rewards', {
				enabled: false,
				purchase_with_points: true,
			} )
		).toThrow( ApiError );
		expect( () =>
			parseSection( 'rewards', {
				enabled: false,
				purchase_with_points: true,
				revision: 'not-a-revision',
			} )
		).toThrow( ApiError );
		expect(
			buildPayload( 'rewards', {
				enabled: true,
				purchase_with_points: false,
				revision: '0123456789abcdef',
			} )
		).toEqual( {
			enabled: true,
			purchase_with_points: false,
			revision: '0123456789abcdef',
		} );
		expect( () =>
			parseSection( 'leaderboard', {
				enabled: true,
				mode: 'vibes',
				threshold: 0,
				limit: 10,
			} )
		).toThrow( ApiError );
		expect( () =>
			parseSection( 'points', { enabled: true, rules: [ { event: 1 } ] } )
		).toThrow( ApiError );
		expect( () => parseSection( 'badges', [] ) ).toThrow( ApiError );
		expect( () => parseSection( 'levels', { enabled: 'yes' } ) ).toThrow(
			ApiError
		);
	} );

	it( 'validates levels, badges and options', () => {
		expect( parseLevel( wire( silver ) ) ).toEqual( silver );
		expect( () => parseLevel( { ...silver, points: 1.5 } ) ).toThrow(
			ApiError
		);
		expect( parseBadge( wire( starter ) ) ).toEqual( starter );
		expect( () =>
			parseBadge( {
				...starter,
				criteria: [ { metric: 'passwords', operator: '>=', value: 1 } ],
			} )
		).toThrow( ApiError );
		expect( () => parseBadge( { ...starter, award_source: 'x' } ) ).toThrow(
			ApiError
		);
		expect( parseOptions( wire( options ) ) ).toEqual( options );
		expect( () => parseOptions( { ...options, limits: {} } ) ).toThrow(
			ApiError
		);
	} );

	it( 'keeps only allowed image types from the media route', () => {
		const item = {
			id: 5,
			source_url: 'https://e.test/a.png',
			mime_type: 'image/png',
			title: { rendered: 'A' },
			media_details: {
				sizes: {
					thumbnail: { source_url: 'https://e.test/a-150.png' },
				},
			},
		};
		expect( parseMedia( item ) ).toEqual( {
			id: 5,
			url: 'https://e.test/a.png',
			thumbnail_url: 'https://e.test/a-150.png',
			title: 'A',
		} );
		expect( () =>
			parseMedia( { ...item, mime_type: 'image/svg+xml' } )
		).toThrow( ApiError );
	} );
} );

describe( 'drafts and payloads', () => {
	it( 'sends only editable point fields and thresholds where supported', () => {
		const payload = buildPayload( 'points', toDraft< 'points' >( points ) );
		expect( payload ).toEqual( {
			enabled: true,
			rules: [
				{
					event: 'lesson_complete',
					enabled: true,
					points: 5,
					email: lessonRule.email,
				},
				{
					event: 'quiz_passing_mark',
					enabled: false,
					points: 5,
					threshold: 30,
					email: quizRule.email,
				},
			],
		} );
		expect( JSON.stringify( payload ) ).not.toContain( 'label' );
	} );

	it( 'detects changes', () => {
		const draft = toDraft< 'points' >( points );
		expect( isDirty( draft, toDraft< 'points' >( points ) ) ).toBe( false );
		at( draft.rules, 0 ).points = 6;
		expect( isDirty( toDraft< 'points' >( points ), draft ) ).toBe( true );
	} );

	it( 'builds level and badge payloads', () => {
		expect(
			levelPayload( { ...levelToDraft( silver ), name: ' Silver ' } )
		).toEqual( {
			name: 'Silver',
			description: '',
			color: '#999999',
			text_color: '#000000',
			points: 100,
		} );
		const draft = badgeToDraft( starter );
		expect( badgePayload( draft ) ).toMatchObject( {
			image_id: 41,
			criteria: [ { metric: 'points', operator: '>=', value: 10 } ],
		} );
		expect(
			badgePayload( { ...draft, image_id: 0, award_source: 'streak' } )
		).toMatchObject( { image_id: null, criteria: [] } );
		expect( badgeToDraft().criteria ).toHaveLength( 1 );
	} );

	it( 'parses number fields', () => {
		expect( toNumber( '' ) ).toBeNull();
		expect( toNumber( undefined ) ).toBeNull();
		expect( toNumber( '12' ) ).toBe( 12 );
		expect( toNumber( 'x' ) ).toBeNull();
	} );
} );

describe( 'validation', () => {
	it( 'checks point rules like the server', () => {
		const draft = toDraft< 'points' >( points ) as PointsDraft;
		expect( validatePoints( draft ) ).toEqual( {} );
		at( draft.rules, 0 ).points = -1;
		at( draft.rules, 1 ).points = 100001;
		at( draft.rules, 1 ).threshold = 101;
		expect( Object.keys( validatePoints( draft ) ).sort() ).toEqual( [
			'rules.lesson_complete.points',
			'rules.quiz_passing_mark.points',
			'rules.quiz_passing_mark.threshold',
		] );
		at( draft.rules, 0 ).points = 2.5;
		expect(
			validatePoints( draft )[ 'rules.lesson_complete.points' ]
		).toBeDefined();
		at( draft.rules, 0 ).points = null;
		expect(
			validatePoints( draft )[ 'rules.lesson_complete.points' ]
		).toBeDefined();
	} );

	it( 'requires a complete email only when it is on', () => {
		const draft = toDraft< 'points' >( points ) as PointsDraft;
		at( draft.rules, 0 ).email = {
			enabled: true,
			subject: ' ',
			body: '<p></p>',
		};
		expect( Object.keys( validatePoints( draft ) ) ).toEqual( [
			'rules.lesson_complete.email.subject',
			'rules.lesson_complete.email.body',
		] );
		at( draft.rules, 0 ).email = {
			enabled: true,
			subject: 'Hi',
			body: '<p>Yes</p>',
		};
		expect( validatePoints( draft ) ).toEqual( {} );
	} );

	it( 'bounds the leaderboard', () => {
		expect(
			validateLeaderboard( {
				enabled: true,
				mode: 'fastest_time',
				threshold: 0,
				limit: 1,
			} )
		).toEqual( {} );
		expect(
			Object.keys(
				validateLeaderboard( {
					enabled: true,
					mode: 'fastest_time',
					threshold: 101,
					limit: 0,
				} )
			)
		).toEqual( [ 'threshold', 'limit' ] );
	} );

	it( 'keeps level thresholds strictly increasing', () => {
		const levels = [ bronze, silver ];
		const draft = levelToDraft();
		expect( Object.keys( validateLevel( draft, levels, null ) ) ).toEqual( [
			'name',
			'points',
		] );
		expect(
			validateLevel(
				{ ...draft, name: 'Gold', points: 100 },
				levels,
				null
			).points
		).toContain( 'Silver' );
		expect(
			validateLevel(
				{ ...draft, name: 'Gold', points: 200 },
				levels,
				null
			)
		).toEqual( {} );
		// Editing a level may keep its own threshold.
		expect(
			validateLevel( levelToDraft( silver ), levels, 'silver' )
		).toEqual( {} );
		expect(
			validateLevel(
				{ ...levelToDraft( silver ), color: 'red' },
				levels,
				'silver'
			).color
		).toBeDefined();
		expect(
			validateLevel( { ...draft, name: 'X', points: 1 }, levels, null, {
				...options.limits,
				max_levels: 2,
			} ).name
		).toBeDefined();
	} );

	it( 'needs at least one valid condition for criteria badges', () => {
		const draft = badgeToDraft( starter );
		expect( validateBadge( draft ) ).toEqual( {} );
		expect(
			validateBadge( { ...draft, criteria: [] } ).criteria
		).toBeDefined();
		expect(
			validateBadge( badgeToDraft() )[ 'criteria.0.value' ]
		).toBeDefined();
		expect(
			validateBadge( { ...draft, name: '', color: 'nope' } )
		).toMatchObject( {
			name: expect.any( String ),
			color: expect.any( String ),
		} );
		expect(
			validateBadge( { ...draft, award_source: 'streak', criteria: [] } )
		).toEqual( {} );
	} );
} );

describe( 'display and errors', () => {
	it( 'describes criteria with server labels', () => {
		expect(
			describeCriterion( at( starter.criteria ), options.metrics )
		).toBe( 'Bonus points >= 10' );
		expect( describeCriterion( at( starter.criteria ), [] ) ).toBe(
			'points >= 10'
		);
	} );

	it( 'reduces failures', () => {
		const view = describeFailure(
			new ApiError( {
				status: 403,
				code: 'ohmylms_gamification_forbidden',
				message: 'No',
				details: { status: 403, params: { name: 'Bad', other: 5 } },
			} )
		);
		expect( view ).toEqual( {
			message: 'No',
			permission: true,
			code: 'ohmylms_gamification_forbidden',
			fieldErrors: { name: 'Bad' },
		} );
		expect(
			describeFailure(
				new ApiError( { status: 500, code: 'x', message: 'Boom' } )
			).permission
		).toBe( false );
	} );

	it( 'maps the tab parameter', () => {
		expect( tabFromParam( 'badges' ) ).toBe( 'badges' );
		expect( tabFromParam( 'rewards' ) ).toBe( 'rewards' );
		expect( tabFromParam( 'reward-settings' ) ).toBe( 'points' );
		expect( tabFromParam( null ) ).toBe( 'points' );
	} );
} );
