import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import { findRegistryProblems } from '../registry';
import { renderShell } from '../../test/render';
import * as api from './api';
import CertificatesPage from './CertificatesPage';
import { certificate } from './fixtures';
import { certificateEditor, certificates } from './index';

vi.mock( './api', () => ( {
	listCertificates: vi.fn(),
	createCertificate: vi.fn(),
	duplicateCertificate: vi.fn(),
	deleteCertificate: vi.fn(),
} ) );
const listMock = vi.mocked( api.listCertificates );

function renderPage() {
	return render(
		<MemoryRouter initialEntries={ [ '/certificates' ] }>
			<Routes>
				<Route path="/certificates" element={ <CertificatesPage /> } />
				<Route
					path="/certificates/:id"
					element={ <p>Editor route reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const forbidden = () =>
	new ApiError( {
		status: 403,
		code: 'ohmylms_certificate_cannot_view',
		message: 'Sorry, you are not allowed to view certificate templates.',
	} );

beforeEach( () => {
	vi.resetAllMocks();
	listMock.mockResolvedValue( {
		items: [
			{ ...certificate, background_url: 'https://example.test/bg.png' },
		],
		total: 1,
		totalPages: 1,
	} );
} );

describe( 'CertificatesPage', () => {
	it( 'registers a capability-gated list route and a separate editor route', async () => {
		expect( certificates ).toMatchObject( {
			path: '/certificates',
			capability: 'manage_options',
		} );
		expect( certificateEditor ).toMatchObject( {
			path: '/certificates/:id',
			capability: 'manage_options',
		} );
		expect(
			findRegistryProblems( [ certificates, certificateEditor ] )
		).toEqual( [] );
		renderShell( [ certificates, certificateEditor ], {
			route: '/certificates',
		} );
		expect(
			screen.queryByRole( 'link', { name: 'Certificates' } )
		).not.toBeInTheDocument();
		expect( listMock ).not.toHaveBeenCalled();
	} );
	it( 'opens the lazy route for administrators', async () => {
		renderShell( [ certificates, certificateEditor ], {
			route: '/certificates',
			capabilities: { manage_options: true },
		} );
		expect(
			await screen.findByRole( 'table', {
				name: 'Certificate templates',
			} )
		).toBeInTheDocument();
		expect( certificates ).toMatchObject( {
			group: 'engagement',
			tab: 'gamification',
			section: 'certificates',
		} );
	} );
	it( 'shows loading, then a named table with thumbnails, links and counts', async () => {
		renderPage();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading certificate templates'
		);
		const table = await screen.findByRole( 'table', {
			name: 'Certificate templates',
		} );
		expect(
			within( table ).getByRole( 'link', { name: 'Completion' } )
		).toHaveAttribute( 'href', '/certificates/7' );
		expect( within( table ).getByText( 'Draft' ) ).toBeInTheDocument();
		expect(
			within( table ).getByText( 'Landscape, A4' )
		).toBeInTheDocument();
		const image = table.querySelector( 'img' );
		expect( image ).toHaveAttribute( 'src', 'https://example.test/bg.png' );
		expect( image ).toHaveAttribute( 'alt', '' );
		expect(
			screen.getAllByText( '1 template found.' )[ 0 ]
		).toBeInTheDocument();
	} );
	it( 'shows an empty state', async () => {
		listMock.mockResolvedValue( { items: [], total: 0, totalPages: 0 } );
		renderPage();
		expect(
			await screen.findByText( 'No certificate templates found.' )
		).toBeInTheDocument();
	} );
	it( 'reports a permission error from the server without showing a table', async () => {
		listMock.mockRejectedValue( forbidden() );
		renderPage();
		expect(
			await screen.findByText(
				'Sorry, you are not allowed to view certificate templates.'
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );
	it( 'retries a failed request and searches and filters through the form', async () => {
		const user = userEvent.setup();
		listMock.mockRejectedValueOnce( new Error( 'Request failed' ) );
		renderPage();
		await screen.findByText( 'Request failed' );
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		await screen.findByText( 'Completion' );
		await user.type(
			screen.getByRole( 'searchbox', {
				name: 'Search certificate templates',
			} ),
			'gold{Enter}'
		);
		await waitFor( () =>
			expect( listMock ).toHaveBeenLastCalledWith(
				1,
				'gold',
				'all',
				expect.any( AbortSignal )
			)
		);
		await user.click(
			await screen.findByRole( 'link', { name: 'Published' } )
		);
		await waitFor( () =>
			expect( listMock ).toHaveBeenLastCalledWith(
				1,
				'gold',
				'publish',
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'validates the name before creating, then opens the editor of the new template', async () => {
		const user = userEvent.setup();
		vi.mocked( api.createCertificate ).mockResolvedValue( {
			...certificate,
			id: 42,
		} );
		renderPage();
		await screen.findByText( 'Completion' );
		await user.click(
			screen.getByRole( 'button', { name: 'Add template' } )
		);
		const dialog = within(
			screen.getByRole( 'dialog', { name: 'Add certificate template' } )
		);
		await user.click(
			dialog.getByRole( 'button', { name: 'Create template' } )
		);
		expect( await dialog.findByRole( 'alert' ) ).toHaveTextContent(
			'Enter a name of 1 to 200 characters.'
		);
		expect( api.createCertificate ).not.toHaveBeenCalled();
		expect(
			dialog.getByRole( 'textbox', { name: 'Template name' } )
		).toBeInvalid();
		await user.type(
			dialog.getByRole( 'textbox', { name: 'Template name' } ),
			'Gold{Enter}'
		);
		await waitFor( () =>
			expect( api.createCertificate ).toHaveBeenCalledWith(
				'Gold',
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText( 'Editor route reached' )
		).toBeInTheDocument();
	} );
	it( 'keeps the dialog and the typed name when creation fails on the server', async () => {
		const user = userEvent.setup();
		vi.mocked( api.createCertificate ).mockRejectedValue(
			new ApiError( {
				status: 500,
				code: 'ohmylms_certificate_save_failed',
				message: 'The certificate template could not be saved.',
			} )
		);
		renderPage();
		await screen.findByText( 'Completion' );
		await user.click(
			screen.getByRole( 'button', { name: 'Add template' } )
		);
		const dialog = within( screen.getByRole( 'dialog' ) );
		await user.type(
			dialog.getByRole( 'textbox', { name: 'Template name' } ),
			'Gold'
		);
		await user.click(
			dialog.getByRole( 'button', { name: 'Create template' } )
		);
		expect(
			await dialog.findByText(
				'The certificate template could not be saved.'
			)
		).toBeInTheDocument();
		expect(
			dialog.getByRole( 'textbox', { name: 'Template name' } )
		).toHaveValue( 'Gold' );
		expect(
			screen.queryByText( 'Editor route reached' )
		).not.toBeInTheDocument();
	} );
	it( 'duplicates a template and reloads the list', async () => {
		const user = userEvent.setup();
		vi.mocked( api.duplicateCertificate ).mockResolvedValue( {
			...certificate,
			id: 9,
		} );
		renderPage();
		await screen.findByText( 'Completion' );
		await user.click(
			screen.getByRole( 'button', { name: 'Duplicate Completion' } )
		);
		await waitFor( () =>
			expect( api.duplicateCertificate ).toHaveBeenCalledWith(
				7,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText( 'Duplicated Completion as a draft.', {
				selector: '.components-notice__content',
			} )
		).toBeInTheDocument();
		await waitFor( () => expect( listMock ).toHaveBeenCalledTimes( 2 ) );
	} );
	it( 'reports a duplication failure', async () => {
		const user = userEvent.setup();
		vi.mocked( api.duplicateCertificate ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_certificate_cannot_create',
				message:
					'Sorry, you are not allowed to create certificate templates.',
			} )
		);
		renderPage();
		await screen.findByText( 'Completion' );
		await user.click(
			screen.getByRole( 'button', { name: 'Duplicate Completion' } )
		);
		expect(
			await screen.findByText(
				'Sorry, you are not allowed to create certificate templates.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
	} );
	it( 'requires confirmation to delete, supports Escape to cancel, and shows server errors in the dialog', async () => {
		const user = userEvent.setup();
		vi.mocked( api.deleteCertificate ).mockRejectedValueOnce(
			new ApiError( {
				status: 500,
				code: 'ohmylms_certificate_delete_failed',
				message: 'The certificate template could not be deleted.',
			} )
		);
		vi.mocked( api.deleteCertificate ).mockResolvedValueOnce( undefined );
		renderPage();
		await screen.findByText( 'Completion' );
		const trigger = screen.getByRole( 'button', {
			name: 'Delete Completion',
		} );
		await user.click( trigger );
		expect( api.deleteCertificate ).not.toHaveBeenCalled();
		expect(
			screen.getByRole( 'dialog', {
				name: 'Delete certificate template',
			} )
		).toHaveTextContent( 'Permanently delete Completion?' );
		await user.keyboard( '{Escape}' );
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
		expect( api.deleteCertificate ).not.toHaveBeenCalled();
		await user.click( trigger );
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Delete template',
			} )
		);
		expect(
			await within( screen.getByRole( 'dialog' ) ).findByText(
				'The certificate template could not be deleted.'
			)
		).toBeInTheDocument();
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Delete template',
			} )
		);
		await waitFor( () =>
			expect( api.deleteCertificate ).toHaveBeenCalledTimes( 2 )
		);
		expect( api.deleteCertificate ).toHaveBeenLastCalledWith(
			7,
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByText( 'Template deleted.', {
				selector: '.components-notice__content',
			} )
		).toBeInTheDocument();
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
	} );
	it( 'steps back a page when the last item of a later page is deleted', async () => {
		const user = userEvent.setup();
		listMock.mockResolvedValue( {
			items: [ certificate ],
			total: 21,
			totalPages: 2,
		} );
		vi.mocked( api.deleteCertificate ).mockResolvedValue( undefined );
		renderPage();
		await screen.findByText( 'Completion' );
		await user.click(
			screen.getAllByRole( 'button', { name: 'Next page' } )[ 0 ]!
		);
		await waitFor( () =>
			expect( listMock ).toHaveBeenLastCalledWith(
				2,
				'',
				'all',
				expect.any( AbortSignal )
			)
		);
		await screen.findByText( 'Completion' );
		await user.click(
			screen.getByRole( 'button', { name: 'Delete Completion' } )
		);
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Delete template',
			} )
		);
		await waitFor( () =>
			expect( listMock ).toHaveBeenLastCalledWith(
				1,
				'',
				'all',
				expect.any( AbortSignal )
			)
		);
	} );
} );
