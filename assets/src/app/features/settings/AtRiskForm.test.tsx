import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, get, patch } from '../../lib/api';
import { parseAtRiskSettings, validateAtRisk } from './atRisk';
import type { AtRiskValues } from './atRisk';
import { AtRiskForm } from './AtRiskForm';

const notice = { selector: '.components-notice__content' };

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
} ) );

const defaults: AtRiskValues = {
	inactive_enabled: true,
	inactive_days: 7,
	inactive_severe_days: 14,
	mastery_enabled: true,
	mastery_drop: 10,
	mastery_window_days: 14,
	mastery_severe_drop: 20,
	overdue_enabled: true,
	overdue_items: 1,
	overdue_severe_items: 3,
	nudge_guardians: false,
	nudge_daily_cap: 20,
};

function response(
	values: Partial< AtRiskValues > = {},
	changed: string[] = []
) {
	return { group: 'at-risk', values: { ...defaults, ...values }, changed };
}

function show( onDirty = vi.fn() ) {
	render( <AtRiskForm active onDirty={ onDirty } /> );
	return onDirty;
}

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( get ).mockResolvedValue( response() );
} );

describe( 'at-risk settings model', () => {
	it( 'parses the group and rejects other shapes', () => {
		expect( parseAtRiskSettings( response() ).values ).toEqual( defaults );
		expect( () =>
			parseAtRiskSettings( { ...response(), group: 'tax' } )
		).toThrow();
		expect( () =>
			parseAtRiskSettings( response( { inactive_days: 7.5 } ) )
		).toThrow();
		expect( () =>
			parseAtRiskSettings( {
				...response(),
				values: { ...defaults, nudge_guardians: 'no' },
			} )
		).toThrow();
	} );

	it( 'validates ranges and high-risk thresholds like the server', () => {
		expect( validateAtRisk( defaults ) ).toEqual( {} );
		const errors = validateAtRisk( {
			...defaults,
			inactive_days: 0,
			mastery_window_days: 91,
			nudge_daily_cap: Number.NaN,
			overdue_items: 4,
		} );
		expect( Object.keys( errors ).sort() ).toEqual( [
			'inactive_days',
			'mastery_window_days',
			'nudge_daily_cap',
			'overdue_severe_items',
		] );
		expect( errors.inactive_days ).toBe( 'Enter a number from 1 to 365.' );
	} );
} );

describe( 'at-risk settings tab', () => {
	it( 'loads the rules and shows each threshold', async () => {
		show();
		expect(
			await screen.findByRole( 'spinbutton', {
				name: 'Days without activity',
			} )
		).toHaveValue( 7 );
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/at-risk',
			expect.objectContaining( { signal: expect.any( AbortSignal ) } )
		);
		expect(
			screen.getByRole( 'spinbutton', { name: 'Points lost on a skill' } )
		).toHaveValue( 10 );
		expect(
			screen.getByRole( 'spinbutton', { name: 'Within (days)' } )
		).toHaveValue( 14 );
		expect(
			screen.getByRole( 'spinbutton', { name: 'Overdue items' } )
		).toHaveValue( 1 );
		expect(
			screen.getByRole( 'checkbox', {
				name: 'Also send a copy to the learner’s guardians',
			} )
		).not.toBeChecked();
	} );

	it( 'saves only the changed fields and reports unsaved edits', async () => {
		const user = userEvent.setup();
		vi.mocked( patch ).mockResolvedValue(
			response( { inactive_days: 10 }, [ 'inactive_days' ] )
		);
		const onDirty = show();
		const days = await screen.findByRole( 'spinbutton', {
			name: 'Days without activity',
		} );
		await user.clear( days );
		await user.type( days, '10' );
		await waitFor( () =>
			expect( onDirty ).toHaveBeenLastCalledWith( true )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/at-risk',
			{ inactive_days: 10 },
			expect.anything()
		);
		expect(
			await screen.findByText( 'Settings saved.', notice )
		).toBeInTheDocument();
		await waitFor( () =>
			expect( onDirty ).toHaveBeenLastCalledWith( false )
		);
	} );

	it( 'blocks a high-risk threshold below the rule before sending', async () => {
		const user = userEvent.setup();
		show();
		const severe = await screen.findByRole( 'spinbutton', {
			name: 'High risk after (days)',
		} );
		await user.clear( severe );
		await user.type( severe, '3' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await screen.findByText(
				'The high-risk threshold cannot be below the rule’s threshold.'
			)
		).toBeInTheDocument();
		expect( patch ).not.toHaveBeenCalled();
	} );

	it( 'shows the server’s 422 message on its field', async () => {
		const user = userEvent.setup();
		vi.mocked( patch ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_settings_invalid_value',
				message: 'Some settings are not valid.',
				details: {
					status: 422,
					params: {
						nudge_daily_cap: 'Enter a number from 1 to 200.',
					},
				},
			} )
		);
		show();
		const cap = await screen.findByRole( 'spinbutton', {
			name: 'Nudges per sender per day',
		} );
		await user.clear( cap );
		await user.type( cap, '50' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect(
			await screen.findByText( 'Enter a number from 1 to 200.' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Some settings are not valid.', notice )
		).toBeInTheDocument();
	} );

	it( 'hides a rule’s thresholds while the rule is off and saves the toggle', async () => {
		const user = userEvent.setup();
		vi.mocked( patch ).mockResolvedValue(
			response( { mastery_enabled: false }, [ 'mastery_enabled' ] )
		);
		show();
		await user.click(
			await screen.findByRole( 'checkbox', {
				name: 'Flag learners whose skill mastery is dropping',
			} )
		);
		expect(
			screen.queryByRole( 'spinbutton', {
				name: 'Points lost on a skill',
			} )
		).not.toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/settings/at-risk',
			{ mastery_enabled: false },
			expect.anything()
		);
	} );
} );
