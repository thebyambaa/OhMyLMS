import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { searchPages } from './api';
import { pages } from './fixtures';
import { PagePicker } from './PagePicker';

vi.mock( './api', () => ( { searchPages: vi.fn() } ) );

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( searchPages ).mockResolvedValue( pages );
} );

describe( 'PagePicker', () => {
	it( 'loads a bounded first page of results and offers no selection by default', async () => {
		render(
			<PagePicker
				label="Checkout page"
				value={ 0 }
				onChange={ vi.fn() }
			/>
		);
		await screen.findByRole( 'option', { name: 'All courses' } );
		expect( searchPages ).toHaveBeenCalledWith(
			{ search: '' },
			expect.any( AbortSignal )
		);
		expect( screen.getByLabelText( 'Checkout page' ) ).toHaveDisplayValue(
			'— None —'
		);
	} );

	it( 'labels the saved page by id even when it is not in the first results', async () => {
		vi.mocked( searchPages ).mockImplementation( async ( params ) =>
			params?.include
				? [ { id: 99, title: 'Old terms', slug: 'old-terms' } ]
				: pages
		);
		render(
			<PagePicker label="Terms page" value={ 99 } onChange={ vi.fn() } />
		);
		await waitFor( () =>
			expect( screen.getByLabelText( 'Terms page' ) ).toHaveDisplayValue(
				'Old terms'
			)
		);
		expect( searchPages ).toHaveBeenCalledWith(
			{ include: [ 99 ] },
			expect.any( AbortSignal )
		);
	} );

	it( 'marks a saved page that is no longer published', async () => {
		vi.mocked( searchPages ).mockImplementation( async ( params ) =>
			params?.include ? [] : pages
		);
		render(
			<PagePicker label="Terms page" value={ 55 } onChange={ vi.fn() } />
		);
		expect(
			await screen.findByRole( 'option', {
				name: 'Page 55 (not published or missing)',
			} )
		).toBeInTheDocument();
	} );

	it( 'searches by typed text after a pause and reports the chosen id', async () => {
		const user = userEvent.setup();
		const onChange = vi.fn();
		render(
			<PagePicker
				label="Checkout page"
				value={ 0 }
				onChange={ onChange }
			/>
		);
		await screen.findByRole( 'option', { name: 'All courses' } );
		vi.mocked( searchPages ).mockResolvedValue( [ pages[ 1 ]! ] );
		await user.type(
			screen.getByLabelText( 'Search pages for Checkout page' ),
			'check'
		);
		await waitFor(
			() =>
				expect( searchPages ).toHaveBeenLastCalledWith(
					{ search: 'check' },
					expect.any( AbortSignal )
				),
			{ timeout: 2000 }
		);
		await user.selectOptions(
			screen.getByLabelText( 'Checkout page' ),
			await screen.findByRole( 'option', { name: 'Checkout' } )
		);
		expect( onChange ).toHaveBeenCalledWith( 12 );
	} );

	it( 'shows the error and the validation message', async () => {
		vi.mocked( searchPages ).mockRejectedValue(
			new ApiError( {
				status: 500,
				code: 'boom',
				message: 'Search failed.',
			} )
		);
		render(
			<PagePicker
				label="Checkout page"
				value={ 0 }
				error="Choose a different page."
				onChange={ vi.fn() }
			/>
		);
		expect(
			( await screen.findAllByText( 'Search failed.' ) ).length
		).toBeGreaterThan( 0 );
		expect(
			screen.getByLabelText( 'Checkout page' )
		).toHaveAccessibleDescription( 'Choose a different page.' );
	} );
} );
