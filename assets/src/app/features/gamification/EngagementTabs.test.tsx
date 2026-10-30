import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import * as engagementApi from './engagementApi';
import {
	customStreakBadge,
	masterySettings,
	scoreSettings,
	streakBadge,
	streakSettings,
	xpSettings,
} from './engagementFixtures';
import { options, page } from './fixtures';
import GamificationPage from './GamificationPage';
import type { EngagementGroup, EngagementMap } from './types';

vi.mock( './api', () => ( {
	getSection: vi.fn(),
	saveSection: vi.fn(),
	getOptions: vi.fn(),
	listLevels: vi.fn(),
	createLevel: vi.fn(),
	updateLevel: vi.fn(),
	deleteLevel: vi.fn(),
	listBadges: vi.fn(),
	createBadge: vi.fn(),
	updateBadge: vi.fn(),
	deleteBadge: vi.fn(),
	listMedia: vi.fn(),
} ) );
vi.mock( './engagementApi', () => ( {
	getEngagement: vi.fn(),
	saveEngagement: vi.fn(),
	listMilestoneBadges: vi.fn(),
} ) );

const groups: EngagementMap = {
	streak: streakSettings,
	xp: xpSettings,
	score: scoreSettings,
	mastery: masterySettings,
};
const noticeOptions = { selector: '.components-notice__content' };
const getEngagement = vi.mocked( engagementApi.getEngagement );
const saveEngagement = vi.mocked( engagementApi.saveEngagement );

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( api.getOptions ).mockResolvedValue( options );
	vi.mocked( api.getSection ).mockResolvedValue( { enabled: true } as never );
	vi.mocked( api.listBadges ).mockResolvedValue( page( [] ) );
	vi.mocked( api.listLevels ).mockResolvedValue( page( [] ) );
	getEngagement.mockImplementation( ( async ( group: EngagementGroup ) =>
		JSON.parse( JSON.stringify( groups[ group ] ) ) ) as never );
	vi.mocked( engagementApi.listMilestoneBadges ).mockResolvedValue( [
		streakBadge,
		customStreakBadge,
	] );
} );

function renderPage( tab: string ) {
	return render(
		<MemoryRouter initialEntries={ [ `/gamification?tab=${ tab }` ] }>
			<GamificationPage />
		</MemoryRouter>
	);
}

describe( 'streaks tab', () => {
	it( 'opens from ?tab=streaks and loads only the streak group', async () => {
		renderPage( 'streaks' );
		const form = await screen.findByRole( 'form', {
			name: 'Learning streaks',
		} );
		expect(
			screen.getByRole( 'tab', { name: 'Streaks' } )
		).toHaveAttribute( 'aria-selected', 'true' );
		expect( getEngagement ).toHaveBeenCalledTimes( 1 );
		expect( getEngagement ).toHaveBeenCalledWith(
			'streak',
			expect.any( AbortSignal )
		);
		expect(
			within( form ).getByRole( 'checkbox', {
				name: 'Record learning streaks',
			} )
		).not.toBeChecked();
		expect(
			within( form ).getByRole( 'spinbutton', {
				name: 'Answers a practice session needs',
			} )
		).toHaveValue( 3 );
		const milestone = within( form ).getByRole( 'group', {
			name: 'Milestone 2',
		} );
		expect(
			within( milestone ).getByRole( 'combobox', { name: 'Badge' } )
		).toHaveValue( 'streak-30' );
		expect(
			await within( form ).findAllByRole( 'option', {
				name: 'Fortnight',
			} )
		).toHaveLength( 2 );
		expect( api.getSection ).not.toHaveBeenCalledWith(
			'points',
			expect.anything()
		);
	} );

	it( 'saves the edited draft and marks the tab while unsaved', async () => {
		const user = userEvent.setup();
		saveEngagement.mockImplementation( ( async (
			_group: EngagementGroup,
			draft: unknown
		) => ( {
			...streakSettings,
			...( draft as object ),
		} ) ) as never );
		renderPage( 'streaks' );
		const form = await screen.findByRole( 'form', {
			name: 'Learning streaks',
		} );
		const refill = within( form ).getByRole( 'spinbutton', {
			name: 'Learning days that earn a freeze back',
		} );
		await user.clear( refill );
		await user.type( refill, '9' );
		expect(
			screen.getByRole( 'tab', { name: /Streaks \(unsaved\)/ } )
		).toBeVisible();
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await screen.findByText( 'Settings saved.', noticeOptions )
		).toBeVisible();
		expect( saveEngagement ).toHaveBeenCalledTimes( 1 );
		expect( saveEngagement ).toHaveBeenCalledWith(
			'streak',
			expect.objectContaining( { refill_days: 9, practice_minimum: 3 } ),
			expect.any( AbortSignal )
		);
		expect( screen.getByRole( 'tab', { name: 'Streaks' } ) ).toBeVisible();
	} );

	it( 'blocks an invalid draft before sending it', async () => {
		const user = userEvent.setup();
		renderPage( 'streaks' );
		const form = await screen.findByRole( 'form', {
			name: 'Learning streaks',
		} );
		const initial = within( form ).getByRole( 'spinbutton', {
			name: 'Freezes a new learner starts with',
		} );
		await user.clear( initial );
		await user.type( initial, '5' );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Add milestone' } )
		);
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			within( form ).getByText(
				'A learner cannot start with more freezes than the maximum.'
			)
		).toBeVisible();
		expect(
			within(
				within( form ).getByRole( 'group', { name: 'Milestone 3' } )
			).getByText( 'Enter a whole number from 1 to 10000.' )
		).toBeVisible();
		expect( saveEngagement ).not.toHaveBeenCalled();
	} );

	it( 'adds and removes milestones', async () => {
		const user = userEvent.setup();
		renderPage( 'streaks' );
		const form = await screen.findByRole( 'form', {
			name: 'Learning streaks',
		} );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Remove milestone 1' } )
		);
		expect(
			within( form ).queryByRole( 'group', { name: 'Milestone 2' } )
		).toBeNull();
		expect(
			within(
				within( form ).getByRole( 'group', { name: 'Milestone 1' } )
			).getByRole( 'spinbutton', { name: 'Days in a row' } )
		).toHaveValue( 30 );
	} );

	it( 'shows a server field error on the milestones and keeps the draft', async () => {
		const user = userEvent.setup();
		saveEngagement.mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_streak_badge',
				message: 'Choose an existing milestone badge.',
				details: { status: 422, field: 'milestones' },
			} )
		);
		renderPage( 'streaks' );
		const form = await screen.findByRole( 'form', {
			name: 'Learning streaks',
		} );
		await user.click(
			within( form ).getByRole( 'checkbox', { name: 'A finished quiz' } )
		);
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await within( form ).findAllByText(
				'Choose an existing milestone badge.'
			)
		).toHaveLength( 2 );
		expect(
			within( form ).getByRole( 'checkbox', { name: 'A finished quiz' } )
		).not.toBeChecked();
	} );

	it( 'shows a permission error without a retry', async () => {
		getEngagement.mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_gamification_forbidden',
				message: 'Sorry, you are not allowed to manage gamification.',
			} )
		);
		renderPage( 'streaks' );
		expect(
			(
				await screen.findAllByText(
					'Sorry, you are not allowed to manage gamification.'
				)
			)[ 0 ]
		).toBeVisible();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).toBeNull();
	} );
} );

describe( 'XP and mastery tab', () => {
	it( 'loads XP, score and mastery as three forms', async () => {
		renderPage( 'xp' );
		const xp = await screen.findByRole( 'form', {
			name: 'XP and daily goal',
		} );
		const score = await screen.findByRole( 'form', {
			name: 'Mastery score',
		} );
		const mastery = await screen.findByRole( 'form', {
			name: 'Mastery model',
		} );
		expect(
			[ ...getEngagement.mock.calls.map( ( call ) => call[ 0 ] ) ].sort()
		).toEqual( [ 'mastery', 'score', 'xp' ] );
		expect(
			within( xp ).getByRole( 'textbox', {
				name: 'Daily goal choices (XP)',
			} )
		).toHaveValue( '10, 20, 30' );
		expect(
			within(
				within( score ).getByRole( 'group', {
					name: 'Mastering (90–100)',
				} )
			).getByRole( 'spinbutton', { name: 'Loss per mistake' } )
		).toHaveValue( 6.5 );
		expect(
			within( mastery ).getByRole( 'combobox', {
				name: 'How skill levels are decided',
			} )
		).toHaveValue( 'evidence' );
		expect(
			within( mastery ).getByRole( 'checkbox', { name: 'Quiz' } )
		).toBeChecked();
		expect(
			within( mastery ).getByRole( 'checkbox', {
				name: 'Write AI study notes',
			} )
		).toBeDisabled();
	} );

	it( 'saves XP goals as numbers and rejects a default goal outside them', async () => {
		const user = userEvent.setup();
		saveEngagement.mockResolvedValue( {
			...xpSettings,
			goals: [ 15, 30 ],
			default_goal: 15,
		} as never );
		renderPage( 'xp' );
		const xp = await screen.findByRole( 'form', {
			name: 'XP and daily goal',
		} );
		const goals = within( xp ).getByRole( 'textbox', {
			name: 'Daily goal choices (XP)',
		} );
		await user.clear( goals );
		await user.type( goals, '30, 15' );
		await user.click(
			within( xp ).getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			within( xp ).getByText(
				'The default goal must be one of the goal choices.'
			)
		).toBeVisible();
		expect( saveEngagement ).not.toHaveBeenCalled();

		const fallback = within( xp ).getByRole( 'spinbutton', {
			name: 'Default daily goal',
		} );
		await user.clear( fallback );
		await user.type( fallback, '15' );
		await user.click(
			within( xp ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( saveEngagement ).toHaveBeenCalledWith(
				'xp',
				expect.objectContaining( {
					goals: '30, 15',
					default_goal: 15,
				} ),
				expect.any( AbortSignal )
			)
		);
		await waitFor( () => expect( goals ).toHaveValue( '15, 30' ) );
	} );

	it( 'saves a score band with a decimal step', async () => {
		const user = userEvent.setup();
		saveEngagement.mockImplementation(
			( async ( _group: EngagementGroup, draft: unknown ) =>
				draft ) as never
		);
		renderPage( 'xp' );
		const score = await screen.findByRole( 'form', {
			name: 'Mastery score',
		} );
		const hint = within( score ).getByRole( 'spinbutton', {
			name: 'Share of the gain kept after a hint (0–1)',
		} );
		await user.clear( hint );
		await user.type( hint, '0.25' );
		await user.click(
			within( score ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( saveEngagement ).toHaveBeenCalledWith(
				'score',
				expect.objectContaining( { hint_gain: 0.25 } ),
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'is reachable with the arrow keys after Badges', async () => {
		const user = userEvent.setup();
		renderPage( 'badges' );
		await screen.findByRole( 'tab', { name: 'Badges' } );
		screen.getByRole( 'tab', { name: 'Badges' } ).focus();
		await user.keyboard( '{ArrowRight}' );
		expect( screen.getByRole( 'tab', { name: 'Streaks' } ) ).toHaveFocus();
		await user.keyboard( '{ArrowRight}' );
		expect(
			screen.getByRole( 'tab', { name: 'XP and mastery' } )
		).toHaveAttribute( 'aria-selected', 'true' );
		expect(
			await screen.findByRole( 'form', { name: 'Mastery model' } )
		).toBeVisible();
	} );
} );
