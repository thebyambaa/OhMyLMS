import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import * as api from './api';
import { options, webhook } from './fixtures';
import { webhooks } from './index';
import { renderShell } from '../../test/render';
import WebhooksPage from './WebhooksPage';

vi.mock( './api', () => ( {
	listWebhooks: vi.fn(),
	getOptions: vi.fn(),
	saveWebhook: vi.fn(),
	deleteWebhook: vi.fn(),
	rotateSecret: vi.fn(),
	sendTest: vi.fn(),
	listDeliveries: vi.fn(),
} ) );
const listMock = vi.mocked( api.listWebhooks );

beforeEach( () => {
	vi.clearAllMocks();
	listMock.mockResolvedValue( {
		items: [ webhook ],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.getOptions ).mockResolvedValue( options );
} );

describe( 'WebhooksPage', () => {
	it( 'keeps existing records accessible when extensions remove every trigger', async () => {
		vi.mocked( api.getOptions ).mockResolvedValueOnce( {
			...options,
			triggers: [],
		} );
		render( <WebhooksPage /> );
		await screen.findByText( webhook.name );
		expect(
			screen.getByRole( 'button', { name: 'Add webhook' } )
		).toBeDisabled();
		expect(
			screen.getByRole( 'button', {
				name: `Delete webhook: ${ webhook.name }`,
			} )
		).toBeEnabled();
	} );
	it( 'hides the route and makes no requests without the required capability', async () => {
		renderShell( [ webhooks ], { route: '/webhooks' } );
		expect(
			screen.queryByRole( 'link', { name: 'Webhooks' } )
		).not.toBeInTheDocument();
		expect( listMock ).not.toHaveBeenCalled();
	} );
	it( 'shows loading, a named table and server event labels', async () => {
		render( <WebhooksPage /> );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading webhooks'
		);
		expect(
			await screen.findByRole( 'table', { name: 'Configured webhooks' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'region', { name: 'Configured webhooks' } )
		).toHaveAttribute( 'tabindex', '0' );
		expect( screen.getByText( 'Course Enrollment' ) ).toBeInTheDocument();
		expect(
			screen.queryByDisplayValue( webhook.secret_hint )
		).not.toBeInTheDocument();
	} );
	it( 'shows delivery diagnostics as text and pages through history', async () => {
		const user = userEvent.setup();
		vi.mocked( api.listDeliveries ).mockResolvedValue( {
			items: [
				{
					id: 5,
					delivery_id: 'delivery-5',
					status: 'failed',
					response_code: 500,
					error_message: '<script>not HTML</script>',
					created_at: '2026-10-30T00:00:00',
				},
			],
			total: 21,
			totalPages: 2,
		} );
		render( <WebhooksPage /> );
		await screen.findByText( webhook.name );
		await user.click(
			screen.getByRole( 'button', {
				name: `Deliveries for ${ webhook.name }`,
			} )
		);
		await screen.findByRole( 'table', {
			name: 'Recent delivery attempts',
		} );
		const dialog = screen.getByRole( 'dialog' );
		expect(
			within( dialog ).getByText( '<script>not HTML</script>' )
		).toBeInTheDocument();
		expect( dialog.querySelector( 'script' ) ).toBeNull();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Next page' } )
		);
		await waitFor( () =>
			expect( api.listDeliveries ).toHaveBeenLastCalledWith(
				webhook.id,
				2,
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'keeps entered edits after a failed save and reveals a created secret only once', async () => {
		const user = userEvent.setup();
		vi.mocked( api.saveWebhook ).mockRejectedValueOnce(
			new Error( 'Validation failed' )
		);
		vi.mocked( api.saveWebhook ).mockResolvedValueOnce( {
			webhook,
			secret: 'whsec_once',
		} );
		render( <WebhooksPage /> );
		await screen.findByText( webhook.name );
		await user.click(
			screen.getByRole( 'button', { name: 'Add webhook' } )
		);
		const dialog = within(
			screen.getByRole( 'dialog', { name: 'Add webhook' } )
		);
		await user.type(
			dialog.getByLabelText( 'Name', { exact: true } ),
			'New endpoint'
		);
		await user.type(
			dialog.getByLabelText( 'Endpoint URL' ),
			'https://example.com/new'
		);
		await user.click(
			dialog.getByRole( 'button', { name: 'Save webhook' } )
		);
		await waitFor( () =>
			expect( api.saveWebhook ).toHaveBeenCalledTimes( 1 )
		);
		expect( dialog.getByLabelText( 'Name', { exact: true } ) ).toHaveValue(
			'New endpoint'
		);
		await user.click(
			dialog.getByRole( 'button', { name: 'Save webhook' } )
		);
		await screen.findByRole( 'dialog', { name: 'Signing secret' } );
		expect( screen.getByLabelText( 'New signing secret' ) ).toHaveValue(
			'whsec_once'
		);
		await user.click( screen.getByRole( 'button', { name: 'Done' } ) );
		expect(
			screen.queryByDisplayValue( 'whsec_once' )
		).not.toBeInTheDocument();
	} );
	it( 'requires explicit confirmation for deletion and test delivery', async () => {
		const user = userEvent.setup();
		vi.mocked( api.sendTest ).mockResolvedValue( false );
		render( <WebhooksPage /> );
		await screen.findByText( webhook.name );
		await user.click(
			screen.getByRole( 'button', {
				name: `Delete webhook: ${ webhook.name }`,
			} )
		);
		expect( api.deleteWebhook ).not.toHaveBeenCalled();
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Cancel',
			} )
		);
		expect( api.deleteWebhook ).not.toHaveBeenCalled();
		await user.click(
			screen.getByRole( 'button', {
				name: `Send test delivery: ${ webhook.name }`,
			} )
		);
		expect( api.sendTest ).not.toHaveBeenCalled();
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Send test delivery',
			} )
		);
		await waitFor( () =>
			expect( api.sendTest ).toHaveBeenCalledWith(
				webhook.id,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText(
				'Test delivery failed. Open deliveries for details.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
	} );
	it( 'can retry a failed collection request and search by submitting the form', async () => {
		const user = userEvent.setup();
		listMock.mockRejectedValueOnce( new Error( 'Request failed' ) );
		render( <WebhooksPage /> );
		await screen.findByText( 'Request failed' );
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		await screen.findByText( webhook.name );
		await user.type(
			screen.getByRole( 'textbox', { name: 'Search webhooks' } ),
			'example'
		);
		await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
		await waitFor( () =>
			expect( listMock ).toHaveBeenLastCalledWith(
				1,
				'example',
				expect.any( AbortSignal )
			)
		);
	} );
} );
