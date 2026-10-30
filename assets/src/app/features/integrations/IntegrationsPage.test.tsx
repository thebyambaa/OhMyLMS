import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import {
	gamification,
	integrations as items,
	wpfusion,
	zoom,
	zoomSettings,
} from './fixtures';
import { integrations } from './index';
import IntegrationsPage from './IntegrationsPage';

vi.mock( './api', () => ( {
	listIntegrations: vi.fn(),
	setIntegrationEnabled: vi.fn(),
	getIntegrationSettings: vi.fn(),
	saveIntegrationSettings: vi.fn(),
} ) );

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( api.listIntegrations ).mockResolvedValue( items );
	vi.mocked( api.getIntegrationSettings ).mockResolvedValue( zoomSettings );
} );

const forbidden = () =>
	new ApiError( {
		status: 403,
		code: 'ohmylms_integration_cannot_edit',
		message: 'Sorry, you are not allowed to change integrations.',
	} );

// Notices are also announced in a hidden live region, so match the visible copy only.
const notice = ( text: string | RegExp ) =>
	screen.findByText( text, { selector: '.components-notice__content' } );

function Search() {
	return <output data-testid="search">{ useLocation().search }</output>;
}

const renderPage = ( route = '/integrations' ) =>
	render(
		<MemoryRouter initialEntries={ [ route ] }>
			<IntegrationsPage />
			<Search />
		</MemoryRouter>
	);

describe( 'IntegrationsPage OAuth result', () => {
	it( 'shows the Google Meet connection result and clears it from the address', async () => {
		const user = userEvent.setup();
		renderPage( '/integrations?auth=success&tab=x' );
		expect(
			await notice( 'Google Meet is connected.' )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Close' } ) );
		expect(
			screen.queryByText( 'Google Meet is connected.', {
				selector: '.components-notice__content',
			} )
		).not.toBeInTheDocument();
		expect( screen.getByTestId( 'search' ) ).toHaveTextContent( '?tab=x' );
	} );
	it( 'explains a failed connection', async () => {
		renderPage( '/integrations?auth=error&message=missing_credentials' );
		expect(
			await notice(
				'Google Meet could not be connected. Save the Google Meet client ID and client secret, then connect again.'
			)
		).toBeInTheDocument();
	} );
	it( 'shows nothing without an OAuth result', async () => {
		renderPage();
		await screen.findByRole( 'table' );
		expect(
			screen.queryByText( /Google Meet/, {
				selector: '.components-notice__content',
			} )
		).not.toBeInTheDocument();
	} );
} );

describe( 'IntegrationsPage list', () => {
	it( 'shows loading, then a named table with switches and server reasons', async () => {
		renderPage();
		expect( screen.getAllByRole( 'status' )[ 0 ] ).toHaveTextContent(
			'Loading integrations'
		);
		expect(
			await screen.findByRole( 'table', { name: 'Integrations' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'checkbox', { name: 'Enable Gamification' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'Enable Zoom' } )
		).not.toBeChecked();
		const locked = screen.getByRole( 'checkbox', {
			name: 'Enable WP Fusion',
		} );
		expect( locked ).toBeDisabled();
		expect( locked ).toHaveAccessibleDescription(
			wpfusion.unavailable_reason ?? ''
		);
		expect(
			screen.getByRole( 'button', { name: 'Settings for Zoom' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: 'Settings for Gamification',
			} )
		).not.toBeInTheDocument();
	} );
	it( 'filters by search text and category and offers a way back', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search integrations' } ),
			'zoo'
		);
		expect(
			screen.getByText( '1 integration shown.' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Gamification' ) ).not.toBeInTheDocument();
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Category' } ),
			'crm'
		);
		expect(
			screen.getByText( 'No integrations match your filters.' )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Clear filters' } )
		);
		expect(
			screen.getByText( '3 integrations shown.' )
		).toBeInTheDocument();
	} );
	it( 'shows a retry for server errors but not for permission errors', async () => {
		const user = userEvent.setup();
		vi.mocked( api.listIntegrations ).mockRejectedValueOnce(
			new Error( 'Request failed' )
		);
		renderPage();
		await notice( /Request failed/ );
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		await screen.findByRole( 'table' );
	} );
	it( 'does not offer a retry when access is denied', async () => {
		vi.mocked( api.listIntegrations ).mockRejectedValue( forbidden() );
		renderPage();
		await notice( /not allowed/ );
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );
	it( 'hides the route and makes no request without manage_options', () => {
		renderShell( [ integrations ], { route: '/integrations' } );
		expect(
			screen.queryByRole( 'link', { name: 'Integrations' } )
		).not.toBeInTheDocument();
		expect( api.listIntegrations ).not.toHaveBeenCalled();
	} );
} );

describe( 'IntegrationsPage switches', () => {
	it( 'enables an integration from the keyboard, announces it and prevents double submits', async () => {
		const user = userEvent.setup();
		let finish!: ( value: typeof zoom ) => void;
		vi.mocked( api.setIntegrationEnabled ).mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					finish = resolve;
				} )
		);
		renderPage();
		const toggle = await screen.findByRole( 'checkbox', {
			name: 'Enable Zoom',
		} );
		await user.tab();
		toggle.focus();
		await user.keyboard( ' ' );
		await waitFor( () =>
			expect( api.setIntegrationEnabled ).toHaveBeenCalledTimes( 1 )
		);
		expect( api.setIntegrationEnabled ).toHaveBeenCalledWith(
			'zoom',
			true,
			expect.any( AbortSignal )
		);
		expect( toggle ).toBeDisabled();
		expect(
			screen.getByRole( 'checkbox', { name: 'Enable Gamification' } )
		).toBeDisabled();
		finish( { ...zoom, enabled: true } );
		expect( await notice( 'Zoom enabled.' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'checkbox', { name: 'Enable Zoom' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'Enable Zoom' } )
		).toBeEnabled();
	} );
	it( 'tells the user to reload when the menu changes', async () => {
		const user = userEvent.setup();
		vi.mocked( api.setIntegrationEnabled ).mockResolvedValue( {
			...gamification,
			enabled: false,
			requires_reload: true,
		} );
		renderPage();
		await user.click(
			await screen.findByRole( 'checkbox', {
				name: 'Enable Gamification',
			} )
		);
		expect( await notice( 'Gamification disabled.' ) ).toBeInTheDocument();
		expect(
			await notice( 'Reload the page to update the admin menu.' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Reload page' } )
		).toBeInTheDocument();
	} );
	it( 'keeps the previous state and shows the server message when a switch fails', async () => {
		const user = userEvent.setup();
		vi.mocked( api.setIntegrationEnabled ).mockRejectedValue( forbidden() );
		renderPage();
		await user.click(
			await screen.findByRole( 'checkbox', { name: 'Enable Zoom' } )
		);
		expect(
			await notice( 'Sorry, you are not allowed to change integrations.' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'checkbox', { name: 'Enable Zoom' } )
		).not.toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'Enable Zoom' } )
		).toBeEnabled();
	} );
	it( 'cancels a pending switch request on unmount', async () => {
		const user = userEvent.setup();
		let signal: AbortSignal | undefined;
		vi.mocked( api.setIntegrationEnabled ).mockImplementation(
			( _id, _enabled, next ) => {
				signal = next;
				return new Promise( () => undefined );
			}
		);
		const view = renderPage();
		await user.click(
			await screen.findByRole( 'checkbox', { name: 'Enable Zoom' } )
		);
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );
} );

describe( 'IntegrationsPage settings dialog', () => {
	async function openSettings( user: ReturnType< typeof userEvent.setup > ) {
		renderPage();
		await user.click(
			await screen.findByRole( 'button', { name: 'Settings for Zoom' } )
		);
		return within(
			await screen.findByRole( 'dialog', { name: 'Zoom settings' } )
		);
	}
	it( 'never shows stored secrets, only that one exists', async () => {
		const dialog = await openSettings( userEvent.setup() );
		const secret = await dialog.findByLabelText( 'Client secret' );
		expect( secret ).toHaveAttribute( 'type', 'password' );
		expect( secret ).toHaveValue( '' );
		expect( secret ).toHaveAccessibleDescription(
			/A value is stored \(\*\*\*\*7890\)/
		);
		expect( dialog.getByLabelText( 'Account ID' ) ).toHaveValue( 'acct-1' );
		expect(
			dialog.getByLabelText( 'Event notification endpoint URL' )
		).toHaveAttribute( 'readonly' );
	} );
	it( 'reports validation errors next to the field and focuses the first one', async () => {
		const user = userEvent.setup();
		const dialog = await openSettings( user );
		const account = await dialog.findByLabelText( 'Account ID' );
		await user.clear( account );
		await user.type(
			dialog.getByLabelText( 'Client secret' ),
			'has space'
		);
		await user.click(
			dialog.getByRole( 'button', { name: 'Save settings' } )
		);
		expect( account ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( account ).toHaveAccessibleDescription(
			'This field is required.'
		);
		expect( dialog.getByLabelText( 'Client secret' ) ).toHaveAttribute(
			'aria-invalid',
			'true'
		);
		await waitFor( () => expect( account ).toHaveFocus() );
		expect( api.saveIntegrationSettings ).not.toHaveBeenCalled();
	} );
	it( 'sends only the changed fields and closes with a confirmation', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveIntegrationSettings ).mockResolvedValue(
			zoomSettings
		);
		const dialog = await openSettings( user );
		const client = await dialog.findByLabelText( 'Client ID' );
		await user.clear( client );
		await user.type( client, 'cid-9' );
		await user.type( dialog.getByLabelText( 'Client secret' ), 'abc123' );
		await user.click(
			dialog.getByLabelText( 'Remove the stored webhook secret token' )
		);
		await user.click(
			dialog.getByRole( 'button', { name: 'Save settings' } )
		);
		await waitFor( () =>
			expect( api.saveIntegrationSettings ).toHaveBeenCalledWith(
				'zoom',
				{
					client_id: 'cid-9',
					client_secret: 'abc123',
					webhook_secret_token: null,
				},
				expect.any( AbortSignal )
			)
		);
		expect( await notice( 'Zoom settings saved.' ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
	} );
	it( 'keeps what was typed and shows server field errors after a failed save', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveIntegrationSettings ).mockRejectedValueOnce(
			new ApiError( {
				status: 422,
				code: 'ohmylms_integration_invalid_settings',
				message: 'Some settings are not valid.',
				details: {
					params: { client_id: 'Use at most 128 characters.' },
				},
			} )
		);
		const dialog = await openSettings( user );
		const client = await dialog.findByLabelText( 'Client ID' );
		await user.type( client, 'x' );
		await user.click(
			dialog.getByRole( 'button', { name: 'Save settings' } )
		);
		expect(
			await dialog.findByText( 'Some settings are not valid.' )
		).toBeInTheDocument();
		expect( client ).toHaveValue( 'cid-1x' );
		expect( client ).toHaveAccessibleDescription(
			'Use at most 128 characters.'
		);
		expect(
			dialog.getByRole( 'button', { name: 'Save settings' } )
		).toBeEnabled();
	} );
	it( 'ignores a second submit while the first is pending', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveIntegrationSettings ).mockImplementation(
			() => new Promise( () => undefined )
		);
		const dialog = await openSettings( user );
		await user.type( await dialog.findByLabelText( 'Client ID' ), 'y' );
		await user.click(
			dialog.getByRole( 'button', { name: 'Save settings' } )
		);
		await waitFor( () =>
			expect(
				dialog.getByRole( 'button', { name: 'Save settings' } )
			).toBeDisabled()
		);
		await user.keyboard( '{Enter}' );
		expect( api.saveIntegrationSettings ).toHaveBeenCalledTimes( 1 );
	} );
	it( 'closes without a request when nothing changed, and with Escape', async () => {
		const user = userEvent.setup();
		const dialog = await openSettings( user );
		await dialog.findByLabelText( 'Account ID' );
		await user.click(
			dialog.getByRole( 'button', { name: 'Save settings' } )
		);
		expect( api.saveIntegrationSettings ).not.toHaveBeenCalled();
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Settings for Zoom' } )
		);
		await screen.findByRole( 'dialog', { name: 'Zoom settings' } );
		await user.keyboard( '{Escape}' );
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
		expect(
			screen.getByRole( 'button', { name: 'Settings for Zoom' } )
		).toHaveFocus();
	} );
	it( 'shows permission errors from loading settings without a retry', async () => {
		const user = userEvent.setup();
		vi.mocked( api.getIntegrationSettings ).mockRejectedValue(
			forbidden()
		);
		renderPage();
		await user.click(
			await screen.findByRole( 'button', { name: 'Settings for Zoom' } )
		);
		const dialog = within( await screen.findByRole( 'dialog' ) );
		expect( await dialog.findByText( /not allowed/ ) ).toBeInTheDocument();
		expect(
			dialog.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
	} );
} );
