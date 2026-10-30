import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import {
	bronze,
	image,
	leaderboard,
	options,
	page,
	points,
	rewards,
	silver,
	starter,
	toggleOff,
	toggleOn,
	at,
} from './fixtures';
import GamificationPage from './GamificationPage';
import { gamification } from './index';
import type { SectionId } from './types';

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

const sections = {
	points,
	levels: toggleOn,
	badges: toggleOff,
	leaderboard,
	rewards,
};

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( api.getSection ).mockImplementation( ( async (
		section: SectionId
	) => JSON.parse( JSON.stringify( sections[ section ] ) ) ) as never );
	vi.mocked( api.getOptions ).mockResolvedValue( options );
	vi.mocked( api.listLevels ).mockResolvedValue( page( [ bronze, silver ] ) );
	vi.mocked( api.listBadges ).mockResolvedValue( page( [ starter ] ) );
	vi.mocked( api.listMedia ).mockResolvedValue( page( [ image ] ) );
} );

function renderPage( tab?: string ) {
	return render(
		<MemoryRouter
			initialEntries={ [
				tab ? `/gamification?tab=${ tab }` : '/gamification',
			] }
		>
			<GamificationPage />
		</MemoryRouter>
	);
}

describe( 'gamification feature', () => {
	it( 'is one registry entry for administrators', () => {
		expect( gamification ).toMatchObject( {
			id: 'gamification',
			path: '/gamification',
			capability: 'manage_options',
		} );
	} );
} );

describe( 'GamificationPage', () => {
	it( 'shows the points tab first and warns when the add-on is off', async () => {
		vi.mocked( api.getOptions ).mockResolvedValue( {
			...options,
			integration_enabled: false,
		} );
		renderPage();
		expect(
			await screen.findByRole( 'form', { name: 'Bonus points' } )
		).toBeVisible();
		expect( screen.getByRole( 'tab', { name: 'Points' } ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);
		expect(
			( await screen.findAllByText( /switched off in Add-ons/ ) )[ 0 ]
		).toBeVisible();
		expect( api.listLevels ).not.toHaveBeenCalled();
		expect(
			screen.getByRole( 'link', { name: 'Open certificates' } )
		).toHaveAttribute( 'href', '#/certificates' );
	} );

	it( 'moves between tabs with the arrow keys and loads a tab when first opened', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'form', { name: 'Bonus points' } );
		screen.getByRole( 'tab', { name: 'Points' } ).focus();
		await user.keyboard( '{ArrowRight}' );
		const levels = screen.getByRole( 'tab', { name: 'Levels' } );
		expect( levels ).toHaveFocus();
		expect( levels ).toHaveAttribute( 'aria-selected', 'true' );
		expect(
			await screen.findByRole( 'table', { name: 'Levels' } )
		).toBeVisible();
		await user.keyboard( '{End}' );
		expect( screen.getByRole( 'tab', { name: 'Rewards' } ) ).toHaveFocus();
		await user.keyboard( '{ArrowRight}' );
		expect( screen.getByRole( 'tab', { name: 'Points' } ) ).toHaveFocus();
	} );

	it( 'saves edited point rules and confirms', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveSection ).mockResolvedValue( {
			...points,
			rules: [ { ...points.rules[ 0 ], points: 12 }, points.rules[ 1 ] ],
		} as never );
		renderPage();
		const field = await screen.findByRole( 'spinbutton', {
			name: /Points for .Lesson completion./,
		} );
		await user.clear( field );
		await user.type( field, '12' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			( await screen.findAllByText( 'Settings saved.' ) )[ 0 ]
		).toBeVisible();
		expect( api.saveSection ).toHaveBeenCalledTimes( 1 );
		const draft = at( vi.mocked( api.saveSection ).mock.calls )[ 1 ];
		expect(
			at( ( draft as { rules: Array< { points: number } > } ).rules )
				.points
		).toBe( 12 );
		expect(
			screen.getByRole( 'button', { name: 'Save changes' } )
		).toHaveAttribute( 'aria-disabled', 'true' );
	} );

	it( 'shows a validation error and does not call the server', async () => {
		const user = userEvent.setup();
		renderPage();
		const field = await screen.findByRole( 'spinbutton', {
			name: /Points for .Lesson completion./,
		} );
		await user.clear( field );
		await user.type( field, '-3' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await screen.findByText( /Enter a whole number from 0 to 100000/ )
		).toBeVisible();
		expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( api.saveSection ).not.toHaveBeenCalled();
	} );

	it( 'requires a subject and message when a rule email is switched on', async () => {
		const user = userEvent.setup();
		renderPage();
		await user.click(
			await screen.findByRole( 'checkbox', {
				name: /Email the learner for .Lesson completion./,
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await screen.findByText( 'Enter the email subject.' )
		).toBeVisible();
		expect( screen.getByText( 'Enter the email message.' ) ).toBeVisible();
		expect( api.saveSection ).not.toHaveBeenCalled();
	} );

	it( 'keeps the edit and shows the server message when saving fails', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveSection ).mockRejectedValue(
			new ApiError( {
				status: 500,
				code: 'boom',
				message: 'The database is down.',
			} )
		);
		renderPage();
		await user.click(
			await screen.findByRole( 'checkbox', {
				name: 'Award bonus points',
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			( await screen.findAllByText( 'The database is down.' ) )[ 0 ]
		).toBeVisible();
		expect(
			screen.getByRole( 'checkbox', { name: 'Award bonus points' } )
		).not.toBeChecked();
		expect(
			screen.getByRole( 'button', { name: 'Save changes' } )
		).toBeEnabled();
	} );

	it( 'explains a permission error and offers no retry', async () => {
		vi.mocked( api.getSection ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'forbidden',
				message: 'Not allowed.',
			} )
		);
		renderPage();
		expect(
			( await screen.findAllByText( 'Not allowed.' ) )[ 0 ]
		).toBeVisible();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).toBeNull();
	} );

	it( 'offers a retry after a server error on load', async () => {
		const user = userEvent.setup();
		vi.mocked( api.getSection ).mockRejectedValueOnce(
			new ApiError( {
				status: 500,
				code: 'x',
				message: 'Temporary failure.',
			} )
		);
		renderPage();
		await user.click(
			await screen.findByRole( 'button', { name: 'Try again' } )
		);
		expect(
			await screen.findByRole( 'form', { name: 'Bonus points' } )
		).toBeVisible();
	} );

	it( 'asks before leaving with unsaved changes', async () => {
		const user = userEvent.setup();
		renderPage();
		await user.click(
			await screen.findByRole( 'checkbox', {
				name: 'Award bonus points',
			} )
		);
		expect(
			screen.getByRole( 'tab', { name: /Points \(unsaved\)/ } )
		).toBeVisible();
		await user.click(
			screen.getByRole( 'link', { name: 'Open certificates' } )
		);
		expect(
			(
				await screen.findAllByText( /Leave this page and discard them/ )
			)[ 0 ]
		).toBeVisible();
		await user.click(
			screen.getByRole( 'button', { name: 'Stay on this page' } )
		);
		expect(
			screen.queryByRole( 'button', { name: 'Stay on this page' } )
		).toBeNull();
	} );

	describe( 'levels', () => {
		it( 'lists levels, adds one and rejects a threshold that is already used', async () => {
			const user = userEvent.setup();
			vi.mocked( api.createLevel ).mockResolvedValue( silver );
			renderPage( 'levels' );
			const table = await screen.findByRole( 'table', {
				name: 'Levels',
			} );
			expect( within( table ).getByText( 'Bronze' ) ).toBeVisible();
			await user.click(
				screen.getByRole( 'button', { name: 'Add level' } )
			);
			const dialog = await screen.findByRole( 'dialog', {
				name: 'Add level',
			} );
			await user.type(
				within( dialog ).getByRole( 'textbox', { name: 'Name' } ),
				'Gold'
			);
			const needed = within( dialog ).getByRole( 'spinbutton', {
				name: 'Points needed',
			} );
			await user.type( needed, '100' );
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Save level' } )
			);
			expect(
				await within( dialog ).findByText( /"Silver" already starts/ )
			).toBeVisible();
			expect( api.createLevel ).not.toHaveBeenCalled();
			await user.clear( needed );
			await user.type( needed, '300' );
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Save level' } )
			);
			await waitFor( () =>
				expect( api.createLevel ).toHaveBeenCalledTimes( 1 )
			);
			expect(
				at( vi.mocked( api.createLevel ).mock.calls )[ 0 ]
			).toMatchObject( {
				name: 'Gold',
				points: 300,
			} );
			await waitFor( () =>
				expect( screen.queryByRole( 'dialog' ) ).toBeNull()
			);
			expect( api.listLevels ).toHaveBeenCalledTimes( 2 );
		} );

		it( 'shows the server error inside the editor and keeps it open', async () => {
			const user = userEvent.setup();
			vi.mocked( api.updateLevel ).mockRejectedValue(
				new ApiError( {
					status: 422,
					code: 'ohmylms_level_points_taken',
					message: 'Threshold taken.',
				} )
			);
			renderPage( 'levels' );
			await user.click(
				await screen.findByRole( 'button', { name: 'Edit Bronze' } )
			);
			const dialog = await screen.findByRole( 'dialog', {
				name: 'Edit level',
			} );
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Save level' } )
			);
			expect(
				await within( dialog ).findByText( 'Threshold taken.' )
			).toBeVisible();
			expect( api.updateLevel ).toHaveBeenCalledWith(
				'bronze',
				expect.anything(),
				expect.anything()
			);
			expect(
				within( dialog ).getByRole( 'button', { name: 'Save level' } )
			).toBeEnabled();
		} );

		it( 'confirms before deleting', async () => {
			const user = userEvent.setup();
			vi.mocked( api.deleteLevel ).mockResolvedValue( undefined );
			renderPage( 'levels' );
			await user.click(
				await screen.findByRole( 'button', { name: 'Delete Silver' } )
			);
			const dialog = await screen.findByRole( 'dialog', {
				name: 'Delete level',
			} );
			expect(
				within( dialog ).getByText( /1 learner has reached it/ )
			).toBeVisible();
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Cancel' } )
			);
			expect( api.deleteLevel ).not.toHaveBeenCalled();
			await user.click(
				screen.getByRole( 'button', { name: 'Delete Silver' } )
			);
			await user.click(
				within(
					await screen.findByRole( 'dialog', {
						name: 'Delete level',
					} )
				).getByRole( 'button', {
					name: 'Delete',
				} )
			);
			await waitFor( () =>
				expect( api.deleteLevel ).toHaveBeenCalledWith(
					'silver',
					expect.anything()
				)
			);
		} );

		it( 'shows an empty state', async () => {
			vi.mocked( api.listLevels ).mockResolvedValue( page( [] ) );
			renderPage( 'levels' );
			expect( await screen.findByText( /No levels yet/ ) ).toBeVisible();
		} );

		it( 'reports a failure to load the list', async () => {
			vi.mocked( api.listLevels ).mockRejectedValue(
				new ApiError( {
					status: 500,
					code: 'x',
					message: 'Could not list levels.',
				} )
			);
			renderPage( 'levels' );
			expect(
				await screen.findByText( 'Could not list levels.' )
			).toBeVisible();
			expect(
				screen.getByRole( 'button', { name: 'Try again' } )
			).toBeVisible();
		} );
	} );

	describe( 'badges', () => {
		it( 'lists badges with their conditions', async () => {
			renderPage( 'badges' );
			const table = await screen.findByRole( 'table', {
				name: 'Badges',
			} );
			expect( within( table ).getByText( 'Starter' ) ).toBeVisible();
			expect(
				within( table ).getByText( 'Bonus points >= 10' )
			).toBeVisible();
		} );

		it( 'creates a badge with an image from the media library', async () => {
			const user = userEvent.setup();
			vi.mocked( api.createBadge ).mockResolvedValue( starter );
			renderPage( 'badges' );
			await user.click(
				await screen.findByRole( 'button', { name: 'Add badge' } )
			);
			const dialog = await screen.findByRole( 'dialog', {
				name: 'Add badge',
			} );
			await user.type(
				within( dialog ).getByRole( 'textbox', { name: 'Name' } ),
				'Finisher'
			);
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Choose image' } )
			);
			const picker = await screen.findByRole( 'dialog', {
				name: 'Choose a badge image',
			} );
			await user.click(
				await within( picker ).findByRole( 'button', {
					name: /Use image Trophy/,
				} )
			);
			expect(
				await within( dialog ).findByAltText( 'Selected badge image' )
			).toHaveAttribute( 'src', image.thumbnail_url );
			await user.type(
				within( dialog ).getByRole( 'spinbutton', {
					name: 'Condition 1: value',
				} ),
				'25'
			);
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Save badge' } )
			);
			await waitFor( () =>
				expect( api.createBadge ).toHaveBeenCalledTimes( 1 )
			);
			expect(
				at( vi.mocked( api.createBadge ).mock.calls )[ 0 ]
			).toMatchObject( {
				name: 'Finisher',
				image_id: 77,
				criteria: [ { metric: 'points', operator: '>=', value: 25 } ],
			} );
		} );

		it( 'needs a valid condition value before saving', async () => {
			const user = userEvent.setup();
			renderPage( 'badges' );
			await user.click(
				await screen.findByRole( 'button', { name: 'Add badge' } )
			);
			const dialog = await screen.findByRole( 'dialog', {
				name: 'Add badge',
			} );
			await user.type(
				within( dialog ).getByRole( 'textbox', { name: 'Name' } ),
				'X'
			);
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Save badge' } )
			);
			expect(
				await within( dialog ).findByText(
					/Enter a whole number from 0 to 1000000/
				)
			).toBeVisible();
			expect( api.createBadge ).not.toHaveBeenCalled();
		} );

		it( 'adds and removes conditions and lets the keyboard reach every control', async () => {
			const user = userEvent.setup();
			renderPage( 'badges' );
			await user.click(
				await screen.findByRole( 'button', { name: 'Add badge' } )
			);
			const dialog = await screen.findByRole( 'dialog', {
				name: 'Add badge',
			} );
			await user.click(
				within( dialog ).getByRole( 'button', {
					name: 'Add condition',
				} )
			);
			expect(
				within( dialog ).getByRole( 'spinbutton', {
					name: 'Condition 2: value',
				} )
			).toBeVisible();
			await user.click(
				within( dialog ).getByRole( 'button', {
					name: 'Remove condition 2',
				} )
			);
			expect(
				within( dialog ).queryByRole( 'spinbutton', {
					name: 'Condition 2: value',
				} )
			).toBeNull();
			expect(
				within( dialog ).getByRole( 'button', {
					name: 'Remove condition 1',
				} )
			).toHaveAttribute( 'aria-disabled', 'true' );
			within( dialog ).getByRole( 'textbox', { name: 'Name' } ).focus();
			await user.keyboard( '{Escape}' );
			await waitFor( () =>
				expect( screen.queryByRole( 'dialog' ) ).toBeNull()
			);
		} );

		it( 'confirms before deleting a badge', async () => {
			const user = userEvent.setup();
			vi.mocked( api.deleteBadge ).mockResolvedValue( undefined );
			renderPage( 'badges' );
			await user.click(
				await screen.findByRole( 'button', { name: 'Delete Starter' } )
			);
			const dialog = await screen.findByRole( 'dialog', {
				name: 'Delete badge',
			} );
			expect(
				within( dialog ).getByText( /3 learners have earned it/ )
			).toBeVisible();
			await user.click(
				within( dialog ).getByRole( 'button', { name: 'Delete' } )
			);
			await waitFor( () =>
				expect( api.deleteBadge ).toHaveBeenCalledWith(
					'starter',
					expect.anything()
				)
			);
		} );
	} );

	describe( 'leaderboard and rewards', () => {
		it( 'saves the leaderboard settings and validates the numbers', async () => {
			const user = userEvent.setup();
			vi.mocked( api.saveSection ).mockResolvedValue( {
				...leaderboard,
				limit: 5,
			} as never );
			renderPage( 'leaderboard' );
			const limit = await screen.findByRole( 'spinbutton', {
				name: 'Learners shown',
			} );
			await user.clear( limit );
			await user.type( limit, '0' );
			const forms = screen.getAllByRole( 'form' );
			await user.click(
				within( at( forms ) ).getByRole( 'button', {
					name: 'Save changes',
				} )
			);
			expect(
				await screen.findByText( /Enter a whole number from 1 to 100/ )
			).toBeVisible();
			expect( api.saveSection ).not.toHaveBeenCalled();
			await user.clear( limit );
			await user.type( limit, '5' );
			await user.selectOptions(
				screen.getByRole( 'combobox', { name: 'Rank learners by' } ),
				'fastest_time'
			);
			await user.click(
				within( at( forms ) ).getByRole( 'button', {
					name: 'Save changes',
				} )
			);
			await waitFor( () =>
				expect( api.saveSection ).toHaveBeenCalledTimes( 1 )
			);
			expect( at( vi.mocked( api.saveSection ).mock.calls )[ 0 ] ).toBe(
				'leaderboard'
			);
			expect(
				at( vi.mocked( api.saveSection ).mock.calls )[ 1 ]
			).toMatchObject( {
				mode: 'fastest_time',
				limit: 5,
			} );
		} );

		it( 'no longer carries the rewards', async () => {
			renderPage( 'leaderboard' );
			await screen.findByRole( 'form', { name: 'Leaderboard' } );
			expect(
				screen.queryByRole( 'form', { name: 'Point rewards' } )
			).not.toBeInTheDocument();
			expect( api.getSection ).not.toHaveBeenCalledWith(
				'rewards',
				expect.anything()
			);
		} );
	} );

	describe( 'rewards', () => {
		it( 'opens from ?tab=rewards and saves with the loaded revision', async () => {
			const user = userEvent.setup();
			vi.mocked( api.saveSection ).mockResolvedValue( {
				enabled: true,
				purchase_with_points: true,
				revision: 'fedcba9876543210',
			} as never );
			renderPage( 'rewards' );
			expect(
				screen.getByRole( 'tab', { name: 'Rewards' } )
			).toHaveAttribute( 'aria-selected', 'true' );
			await user.click(
				await screen.findByRole( 'checkbox', {
					name: 'Enable point rewards',
				} )
			);
			await user.click(
				screen.getByRole( 'checkbox', {
					name: 'Allow course purchases with bonus points',
				} )
			);
			expect(
				screen.getByRole( 'tab', { name: 'Rewards (unsaved)' } )
			).toBeVisible();
			const form = screen.getByRole( 'form', { name: 'Point rewards' } );
			await user.click(
				within( form ).getByRole( 'button', { name: 'Save changes' } )
			);
			await waitFor( () =>
				expect( api.saveSection ).toHaveBeenCalledWith(
					'rewards',
					{
						enabled: true,
						purchase_with_points: true,
						revision: '0123456789abcdef',
					},
					expect.anything()
				)
			);
			expect(
				( await screen.findAllByText( 'Settings saved.' ) )[ 0 ]
			).toBeVisible();
		} );

		it( 'offers a reload when someone else saved first', async () => {
			const user = userEvent.setup();
			vi.mocked( api.saveSection ).mockRejectedValue(
				new ApiError( {
					status: 409,
					code: 'ohmylms_gamification_conflict',
					message:
						'These settings were changed by someone else. Reload them before saving.',
				} )
			);
			renderPage( 'rewards' );
			await user.click(
				await screen.findByRole( 'checkbox', {
					name: 'Enable point rewards',
				} )
			);
			await user.click(
				screen.getByRole( 'button', { name: 'Save changes' } )
			);
			const reload = await screen.findByRole( 'button', {
				name: 'Reload settings',
			} );
			const calls = vi
				.mocked( api.getSection )
				.mock.calls.filter(
					( call ) => 'rewards' === call[ 0 ]
				).length;
			await user.click( reload );
			await waitFor( () =>
				expect(
					vi
						.mocked( api.getSection )
						.mock.calls.filter(
							( call ) => 'rewards' === call[ 0 ]
						).length
				).toBe( calls + 1 )
			);
			expect(
				await screen.findByRole( 'checkbox', {
					name: 'Enable point rewards',
				} )
			).not.toBeChecked();
		} );

		it( 'warns when learners cannot earn points', async () => {
			const user = userEvent.setup();
			vi.mocked( api.getSection ).mockImplementation( ( async (
				section: SectionId
			) =>
				'points' === section
					? { ...points, enabled: false }
					: JSON.parse(
							JSON.stringify( sections[ section ] )
						) ) as never );
			renderPage( 'rewards' );
			await user.click(
				await screen.findByRole( 'checkbox', {
					name: 'Enable point rewards',
				} )
			);
			expect(
				(
					await screen.findAllByText(
						/switched off on the Points tab/
					)
				)[ 0 ]
			).toBeVisible();
		} );
	} );
} );
