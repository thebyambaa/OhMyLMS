import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { NoticesRegion } from '../../layout/NoticesRegion';
import { ApiError, get } from '../../lib/api';
import HomePage from './HomePage';
import { homeStore } from './store';

vi.mock( '../../lib/api', async ( importOriginal ) => ( {
	...( await importOriginal< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
} ) );

const getMock = vi.mocked( get );

function renderHome() {
	const registry = createRegistry();
	registry.register( homeStore );
	registry.register( noticesStore );
	return render(
		<RegistryProvider value={ registry }>
			<NoticesRegion />
			<HomePage />
		</RegistryProvider>
	);
}

describe( 'HomePage', () => {
	it( 'shows loading, then the list', async () => {
		getMock.mockResolvedValue( {
			post: { name: 'Posts', slug: 'post', description: '' },
		} );
		renderHome();

		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Loading' );
		expect( await screen.findByText( 'Posts' ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument();
	} );

	it( 'shows the empty state', async () => {
		getMock.mockResolvedValue( {} );
		renderHome();

		expect(
			await screen.findByText( 'No content types found.' )
		).toBeInTheDocument();
	} );

	it( 'shows the error and retries', async () => {
		const user = userEvent.setup();
		getMock.mockRejectedValueOnce(
			new ApiError( {
				status: 500,
				code: 'boom',
				message: 'Server down',
			} )
		);
		getMock.mockResolvedValueOnce( {
			page: { name: 'Pages', slug: 'page', description: '' },
		} );
		const { container } = renderHome();
		const view = within( container );

		expect( await view.findByText( /Server down/ ) ).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );

		expect( await screen.findByText( 'Pages' ) ).toBeInTheDocument();
		expect( view.queryByText( /Server down/ ) ).not.toBeInTheDocument();
	} );

	it( 'raises a notice through the shared notices store', async () => {
		const user = userEvent.setup();
		getMock.mockResolvedValue( {} );
		const { container } = renderHome();

		await user.click(
			screen.getByRole( 'button', { name: 'Show a notice' } )
		);

		expect(
			await within( container ).findByText(
				'This is a notice from the shared store.'
			)
		).toBeInTheDocument();
	} );
} );
