import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import { bundle, child, detail, orphan, root } from './fixtures';
import { taxonomies } from './index';

vi.mock( './api', () => ( { listTerms: vi.fn(), getTermDetail: vi.fn() } ) );

const list = vi.mocked( api.listTerms );
const noticeOptions = { selector: '.components-notice__content' };

function show( capabilities: Record< string, boolean > ) {
	return renderShell( [ taxonomies ], {
		route: '/taxonomies',
		capabilities,
	} );
}

beforeEach( () => {
	vi.clearAllMocks();
	list.mockImplementation( async ( kind ) => ( {
		items: 'categories' === kind ? [ root, child, orphan ] : [ bundle ],
		total: 'categories' === kind ? 3 : 1,
		totalPages: 1,
	} ) );
} );

describe( 'taxonomies route', () => {
	it( 'is hidden and makes no requests without edit_posts', () => {
		renderShell( [ taxonomies ], { route: '/taxonomies' } );
		expect(
			screen.queryByRole( 'link', { name: 'Categories and tags' } )
		).not.toBeInTheDocument();
		expect( list ).not.toHaveBeenCalled();
	} );

	it( 'shows the categories tab first with parents, counts and the owning screen', async () => {
		show( { edit_posts: true, manage_options: true } );
		const table = await screen.findByRole( 'table', {
			name: 'Categories',
		} );
		expect(
			within( table ).getByRole( 'cell', { name: 'Mathematics' } )
		).toBeInTheDocument();
		expect( within( table ).getByText( 'Top level' ) ).toBeInTheDocument();
		expect( within( table ).getAllByText( 'Mathematics' ) ).toHaveLength(
			2
		);
		expect( within( table ).getByText( '#99' ) ).toBeInTheDocument();
		expect(
			screen.getByText( /now curriculum items/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Manage the curriculum' } )
		).toHaveAttribute(
			'href',
			'admin.php?page=ohmylms#/extensions/curriculum'
		);
		expect( list ).toHaveBeenCalledWith(
			'categories',
			{ page: 1, search: '' },
			expect.any( AbortSignal )
		);
	} );

	it( 'hides management links and course details from non-administrators', async () => {
		show( { edit_posts: true } );
		await screen.findByRole( 'table', { name: 'Categories' } );
		expect(
			screen.queryByRole( 'link', { name: 'Manage the curriculum' } )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: /Show courses/ } )
		).not.toBeInTheDocument();
	} );

	it( 'switches tabs with the keyboard and loads tags without a parent column', async () => {
		const user = userEvent.setup();
		show( { edit_posts: true, manage_options: true } );
		await screen.findByRole( 'table', { name: 'Categories' } );
		screen.getByRole( 'tab', { name: 'Categories' } ).focus();
		await user.keyboard( '{ArrowRight}' );
		expect( screen.getByRole( 'tab', { name: 'Tags' } ) ).toHaveFocus();
		const table = await screen.findByRole( 'table', { name: 'Tags' } );
		expect(
			within( table ).getByText( 'Starter pack' )
		).toBeInTheDocument();
		expect(
			within( table ).queryByRole( 'columnheader', { name: 'Parent' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Manage course bundles' } )
		).toBeInTheDocument();
	} );

	it( 'shows an empty state', async () => {
		list.mockResolvedValue( { items: [], total: 0, totalPages: 0 } );
		show( { edit_posts: true } );
		expect(
			await screen.findByText( 'Nothing found.' )
		).toBeInTheDocument();
	} );

	it( 'shows a server error and retries', async () => {
		const user = userEvent.setup();
		list.mockRejectedValueOnce(
			new ApiError( {
				code: 'x',
				status: 500,
				message: 'Database offline.',
			} )
		);
		show( { edit_posts: true } );
		expect(
			await screen.findByText( /Database offline\./, noticeOptions )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'cell', { name: 'Mathematics' } )
		).toBeInTheDocument();
	} );

	it( 'shows a permission error', async () => {
		list.mockRejectedValue(
			new ApiError( {
				code: 'ohmylms_category_cannot_view',
				status: 403,
				message: 'Sorry, you are not allowed to view this list.',
			} )
		);
		show( { edit_posts: true } );
		expect(
			await screen.findByText(
				/not allowed to view this list/,
				noticeOptions
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );

	it( 'searches by submitting the form with the keyboard', async () => {
		const user = userEvent.setup();
		show( { edit_posts: true } );
		await screen.findAllByRole( 'cell', { name: /^Mathematics/ } );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search Categories' } ),
			'alg{Enter}'
		);
		await waitFor( () =>
			expect( list ).toHaveBeenLastCalledWith(
				'categories',
				{ page: 1, search: 'alg' },
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'pages through results', async () => {
		const user = userEvent.setup();
		list.mockResolvedValue( { items: [ root ], total: 45, totalPages: 3 } );
		show( { edit_posts: true } );
		await screen.findAllByRole( 'cell', { name: /^Mathematics/ } );
		await user.click(
			screen.getAllByRole( 'button', { name: 'Next page' } )[ 0 ]!
		);
		await waitFor( () =>
			expect( list ).toHaveBeenLastCalledWith(
				'categories',
				{ page: 2, search: '' },
				expect.any( AbortSignal )
			)
		);
	} );

	it( 'opens the linked courses of an item in a dialog that Escape closes', async () => {
		const user = userEvent.setup();
		vi.mocked( api.getTermDetail ).mockResolvedValue( detail );
		show( { edit_posts: true, manage_options: true } );
		await screen.findByRole( 'table', { name: 'Categories' } );
		await user.click(
			screen.getByRole( 'button', { name: 'Show courses in Algebra' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Courses in Algebra',
		} );
		expect(
			await within( dialog ).findByText( 'Intro to Algebra' )
		).toBeInTheDocument();
		expect( api.getTermDetail ).toHaveBeenCalledWith(
			'categories',
			4,
			expect.any( AbortSignal )
		);
		await user.keyboard( '{Escape}' );
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
	} );

	it( 'shows the server error in the dialog when course details are refused', async () => {
		const user = userEvent.setup();
		vi.mocked( api.getTermDetail ).mockRejectedValue(
			new ApiError( {
				code: 'rest_forbidden_context',
				status: 403,
				message: 'Sorry, you are not allowed to edit this resource.',
			} )
		);
		show( { edit_posts: true, manage_options: true } );
		await screen.findByRole( 'table', { name: 'Categories' } );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Show courses in Mathematics',
			} )
		);
		const dialog = await screen.findByRole( 'dialog' );
		expect(
			await within( dialog ).findByText(
				/not allowed to edit this resource/,
				noticeOptions
			)
		).toBeInTheDocument();
	} );
} );
