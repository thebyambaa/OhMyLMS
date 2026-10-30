import { ApiError } from '../../lib/api';
import {
	masterySettings,
	scoreSettings,
	streakSettings,
	xpSettings,
} from './engagementFixtures';
import {
	describeEngagementFailure,
	engagementPayload,
	parseEngagement,
	parseEngagementSave,
	parseGoals,
	toEngagementDraft,
	validateEngagement,
} from './engagementModel';
import { tabFromParam } from './model';
import type { StreakDraft } from './types';

describe( 'engagement parsing', () => {
	it( 'accepts each group as the server sends it', () => {
		expect( parseEngagement( 'streak', streakSettings ) ).toEqual(
			streakSettings
		);
		expect( parseEngagement( 'xp', xpSettings ) ).toEqual( xpSettings );
		expect( parseEngagement( 'score', scoreSettings ) ).toEqual(
			scoreSettings
		);
		expect( parseEngagement( 'mastery', masterySettings ) ).toEqual(
			masterySettings
		);
	} );

	it( 'reads an empty surface map that PHP encoded as a list', () => {
		expect(
			parseEngagement( 'mastery', { ...masterySettings, surfaces: [] } )
				.surfaces
		).toEqual( {} );
	} );

	it.each( [
		[ 'streak', { ...streakSettings, enable: 1 } ],
		[ 'streak', { ...streakSettings, refill_days: '7' } ],
		[ 'streak', { ...streakSettings, milestones: [ { days: 7 } ] } ],
		[ 'xp', { ...xpSettings, goals: '10,20' } ],
		[ 'xp', { ...xpSettings, lesson: 1.5 } ],
		[ 'score', { ...scoreSettings, bands: [ { gain: '9', loss: 1 } ] } ],
		[ 'mastery', { ...masterySettings, policies: null } ],
		[ 'mastery', { ...masterySettings, surfaces: { quiz: 'yes' } } ],
	] as const )( 'rejects a malformed %s group', ( group, raw ) => {
		expect( () => parseEngagement( group, raw ) ).toThrow(
			expect.objectContaining( { code: 'invalid_response' } )
		);
	} );

	it( 'reads the stored group from a save answer', () => {
		expect(
			parseEngagementSave( 'xp', {
				success: true,
				message: 'Settings saved successfully.',
				settings: xpSettings,
			} )
		).toEqual( xpSettings );
		expect( () =>
			parseEngagementSave( 'xp', { success: true } )
		).toThrow();
		expect( () =>
			parseEngagementSave( 'xp', {
				success: false,
				settings: xpSettings,
			} )
		).toThrow();
	} );
} );

describe( 'engagement drafts and payloads', () => {
	it( 'edits goals as text and sends them as numbers', () => {
		const draft = toEngagementDraft( 'xp', xpSettings );
		expect( draft.goals ).toBe( '10, 20, 30' );
		expect(
			engagementPayload( 'xp', { ...draft, goals: '5; 15 25' } ).goals
		).toEqual( [ 5, 15, 25 ] );
		expect( parseGoals( '10, x' ) ).toBeNull();
		expect( parseGoals( '' ) ).toEqual( [] );
	} );

	it( 'sends only the editable mastery fields', () => {
		const payload = engagementPayload(
			'mastery',
			toEngagementDraft( 'mastery', masterySettings )
		);
		expect( Object.keys( payload ).sort() ).toEqual( [
			'ai_insights',
			'policy',
			'show_score',
			'surfaces',
		] );
	} );

	it( 'copies nested lists so editing a draft never changes the saved group', () => {
		const draft = toEngagementDraft( 'streak', streakSettings );
		draft.milestones[ 0 ]!.points = 99;
		expect( streakSettings.milestones[ 0 ]!.points ).toBe( 0 );
	} );
} );

describe( 'engagement validation', () => {
	const streak = (): StreakDraft =>
		toEngagementDraft( 'streak', streakSettings );

	it( 'accepts the defaults', () => {
		expect( validateEngagement( 'streak', streak() ) ).toEqual( {} );
		expect(
			validateEngagement( 'xp', toEngagementDraft( 'xp', xpSettings ) )
		).toEqual( {} );
		expect(
			validateEngagement(
				'score',
				toEngagementDraft( 'score', scoreSettings )
			)
		).toEqual( {} );
		expect(
			validateEngagement(
				'mastery',
				toEngagementDraft( 'mastery', masterySettings )
			)
		).toEqual( {} );
	} );

	it( 'checks streak ranges, freezes and milestone days', () => {
		const errors = validateEngagement( 'streak', {
			...streak(),
			practice_minimum: 0,
			refill_days: null,
			initial_freezes: 3,
			maximum_freezes: 2,
			milestones: [
				{ days: 7, badge: '', points: 0 },
				{ days: 7, badge: '', points: -1 },
				{ days: null, badge: '', points: 0 },
			],
		} );
		expect( Object.keys( errors ).sort() ).toEqual( [
			'initial_freezes',
			'milestones.1.days',
			'milestones.1.points',
			'milestones.2.days',
			'practice_minimum',
			'refill_days',
		] );
		expect( errors.initial_freezes ).toMatch( /more freezes/ );
		expect( errors[ 'milestones.1.days' ] ).toMatch( /different number/ );
	} );

	it( 'limits milestones to 20', () => {
		const milestones = Array.from( { length: 21 }, ( _x, index ) => ( {
			days: index + 1,
			badge: '',
			points: 0,
		} ) );
		expect(
			validateEngagement( 'streak', { ...streak(), milestones } )
				.milestones
		).toBeDefined();
	} );

	it( 'checks XP goals and the default goal', () => {
		const draft = toEngagementDraft( 'xp', xpSettings );
		expect(
			validateEngagement( 'xp', { ...draft, goals: '' } ).goals
		).toBeDefined();
		expect(
			validateEngagement( 'xp', { ...draft, goals: '1,2,3,4,5,6,7' } )
				.goals
		).toBeDefined();
		expect(
			validateEngagement( 'xp', { ...draft, goals: '10, 2.5' } ).goals
		).toBeDefined();
		expect(
			validateEngagement( 'xp', { ...draft, goals: '10, 30' } )
				.default_goal
		).toMatch( /one of the goal choices/ );
		expect(
			validateEngagement( 'xp', { ...draft, daily_cap: 5 } ).daily_cap
		).toBeDefined();
	} );

	it( 'allows decimals in the score but not out of range', () => {
		const draft = toEngagementDraft( 'score', scoreSettings );
		expect(
			validateEngagement( 'score', { ...draft, hint_gain: 0.25 } )
		).toEqual( {} );
		const errors = validateEngagement( 'score', {
			...draft,
			hint_gain: 1.5,
			unlock: 49,
			decay_per_week: null,
			bands: [ { gain: 51, loss: 0 }, ...draft.bands.slice( 1 ) ],
		} );
		expect( Object.keys( errors ).sort() ).toEqual( [
			'bands.0.gain',
			'decay_per_week',
			'hint_gain',
			'unlock',
		] );
		expect(
			validateEngagement( 'score', {
				...draft,
				bands: draft.bands.slice( 0, 3 ),
			} ).bands
		).toBeDefined();
	} );

	it( 'maps a 422 field and 400 params to field errors', () => {
		expect(
			describeEngagementFailure(
				new ApiError( {
					status: 422,
					code: 'ohmylms_xp_default_goal',
					message:
						'The default goal must be one of the goal choices.',
					details: { status: 422, field: 'default_goal' },
				} )
			).fieldErrors
		).toEqual( {
			default_goal: 'The default goal must be one of the goal choices.',
		} );
		expect(
			describeEngagementFailure(
				new ApiError( {
					status: 400,
					code: 'rest_invalid_param',
					message: 'Invalid parameter(s): refill_days',
					details: {
						status: 400,
						params: { refill_days: 'Too big.' },
					},
				} )
			).fieldErrors
		).toEqual( { refill_days: 'Too big.' } );
	} );
} );

describe( 'tabs', () => {
	it( 'knows the streak and XP tabs', () => {
		expect( tabFromParam( 'streaks' ) ).toBe( 'streaks' );
		expect( tabFromParam( 'xp' ) ).toBe( 'xp' );
		expect( tabFromParam( 'streak-settings' ) ).toBe( 'points' );
	} );
} );
