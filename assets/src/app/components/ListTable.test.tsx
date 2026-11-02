import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from '@wordpress/element';
import {
	ListTable,
	ListTableSelect,
	type ListTableProps,
	type SortOrder,
} from '../../ui/ListTable';

interface Row {
	id: number;
	title: string;
	status: string;
}

const ROWS: Row[] = [
	{ id: 1, title: 'Algebra', status: 'Published' },
	{ id: 2, title: 'Biology', status: 'Draft' },
];

function table( overrides: Partial< ListTableProps< Row > > = {} ) {
	const props: ListTableProps< Row > = {
		caption: 'Courses',
		rows: ROWS,
		rowKey: ( row ) => row.id,
		rowLabel: ( row ) => row.title,
		columns: [
			{
				id: 'title',
				label: 'Title',
				primary: true,
				sortable: true,
				render: ( row ) => (
					<strong>
						<a className="row-title" href={ `#${ row.id }` }>
							{ row.title }
						</a>
					</strong>
				),
			},
			{ id: 'status', label: 'Status', render: ( row ) => row.status },
		],
		...overrides,
	};
	return render( <ListTable { ...props } /> );
}

describe( 'ListTable', () => {
	it( 'renders WordPress list-table markup', () => {
		const { container } = table( {
			pagination: {
				page: 1,
				totalPages: 3,
				totalItems: 42,
				onChange: () => {},
			},
		} );
		const element = screen.getByRole( 'table', { name: 'Courses' } );
		expect( element ).toHaveClass(
			'wp-list-table',
			'widefat',
			'fixed',
			'striped',
			'table-view-list'
		);
		expect( element.querySelector( 'thead tr' ) ).not.toBeNull();
		expect( element.querySelector( 'tfoot tr' ) ).not.toBeNull();
		const primary = element.querySelector( 'tbody td.column-primary' );
		expect( primary ).toHaveClass(
			'title',
			'column-title',
			'has-row-actions'
		);
		expect( primary ).toHaveAttribute( 'data-colname', 'Title' );
		expect(
			primary?.querySelector( 'button.toggle-row' )
		).toHaveTextContent( 'Show more details' );
		expect( container.querySelectorAll( '.tablenav' ) ).toHaveLength( 2 );
		expect(
			container.querySelector( '.tablenav.top .tablenav-pages' )
		).not.toBeNull();
		expect( screen.getAllByText( '42 items' ) ).toHaveLength( 2 );
	} );

	it( 'shows an empty row spanning every column', () => {
		table( {
			rows: [],
			emptyMessage: 'No courses found.',
			bulkActions: { actions: [], onApply: () => {} },
		} );
		const cell = screen.getByText( 'No courses found.' );
		expect( cell ).toHaveClass( 'colspanchange' );
		expect( cell ).toHaveAttribute( 'colspan', '3' );
		expect( cell.closest( 'tr' ) ).toHaveClass( 'no-items' );
	} );

	it( 'reveals row actions on keyboard focus and keeps their labels', async () => {
		const user = userEvent.setup();
		const onEdit = vi.fn();
		table( {
			rowActions: ( row ) => [
				{
					key: 'edit',
					label: 'Edit',
					ariaLabel: `Edit ${ row.title }`,
					onClick: onEdit,
				},
				{
					key: 'trash',
					label: 'Trash',
					destructive: true,
					onClick: () => {},
				},
			],
		} );
		const edit = screen.getByRole( 'button', { name: 'Edit Algebra' } );
		const actions = edit.closest( '.row-actions' );
		expect( actions ).not.toHaveClass( 'visible' );
		expect( actions ).toHaveTextContent( 'Edit | Trash' );
		await user.tab();
		expect( screen.getByRole( 'link', { name: 'Algebra' } ) ).toHaveFocus();
		expect( actions ).toHaveClass( 'visible' );
		await user.tab();
		expect( edit ).toHaveFocus();
		await user.keyboard( '{Enter}' );
		expect( onEdit ).toHaveBeenCalledTimes( 1 );
		expect(
			within( actions as HTMLElement ).getByRole( 'button', {
				name: 'Trash',
			} )
		).toHaveClass( 'button-link-delete' );
	} );

	it( 'toggles the responsive row details', async () => {
		const user = userEvent.setup();
		table();
		const [ toggle ] = screen.getAllByRole( 'button', {
			name: 'Show more details',
		} );
		expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
		await user.click( toggle! );
		expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
		expect( toggle!.closest( 'tr' ) ).toHaveClass( 'is-expanded' );
	} );

	it( 'marks sortable and sorted headers like WordPress', async () => {
		const user = userEvent.setup();
		function Sorted() {
			const [ sort, setSort ] = useState< {
				orderby: string;
				order: SortOrder;
			} >( { orderby: 'title', order: 'asc' } );
			return (
				<ListTable< Row >
					caption="Courses"
					rows={ ROWS }
					rowKey={ ( row ) => row.id }
					columns={ [
						{
							id: 'title',
							label: 'Title',
							sortable: true,
							render: ( row ) => row.title,
						},
						{
							id: 'status',
							label: 'Status',
							sortable: 'desc',
							render: ( row ) => row.status,
						},
					] }
					sort={ {
						...sort,
						onChange: ( orderby, order ) =>
							setSort( { orderby, order } ),
					} }
				/>
			);
		}
		render( <Sorted /> );
		const [ title, status ] = screen.getAllByRole( 'columnheader' );
		expect( title ).toHaveClass( 'sorted', 'asc' );
		expect( title ).toHaveAttribute( 'aria-sort', 'ascending' );
		expect( status ).toHaveClass( 'sortable', 'asc' );
		expect( status ).not.toHaveAttribute( 'aria-sort' );
		expect( screen.getByRole( 'table' ) ).toHaveAccessibleDescription(
			'Table ordered by Title. Ascending.'
		);
		await user.click(
			within( title! ).getByRole( 'link', {
				name: 'Title Sort descending.',
			} )
		);
		expect( title ).toHaveClass( 'sorted', 'desc' );
		expect( title ).toHaveAttribute( 'aria-sort', 'descending' );
		await user.click(
			within( status! ).getByRole( 'link', {
				name: 'Status Sort descending.',
			} )
		);
		expect( status ).toHaveClass( 'sorted', 'desc' );
		expect( title ).toHaveClass( 'sortable' );
	} );

	it( 'applies bulk actions to the selected rows', async () => {
		const user = userEvent.setup();
		const onApply = vi.fn();
		table( {
			bulkActions: {
				actions: [ { value: 'trash', label: 'Move to Trash' } ],
				onApply,
			},
		} );
		await user.click( screen.getByLabelText( 'Select Biology' ) );
		const [ select ] = screen.getAllByLabelText( 'Select bulk action' );
		await user.selectOptions( select!, 'trash' );
		await user.click(
			screen.getAllByRole( 'button', { name: 'Apply' } )[ 0 ]!
		);
		expect( onApply ).toHaveBeenCalledWith(
			'trash',
			[ '2' ],
			[ ROWS[ 1 ] ]
		);
		expect( screen.getByLabelText( 'Select Biology' ) ).not.toBeChecked();
		const [ all ] = screen.getAllByLabelText( 'Select All' );
		await user.click( all! );
		expect( screen.getByLabelText( 'Select Algebra' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Select Biology' ) ).toBeChecked();
	} );

	it( 'submits search and filters together and pages through results', async () => {
		const user = userEvent.setup();
		const onFilter = vi.fn();
		const onPage = vi.fn();
		function Filtered() {
			const [ search, setSearch ] = useState( '' );
			const [ status, setStatus ] = useState( 'all' );
			return (
				<ListTable< Row >
					caption="Courses"
					rows={ ROWS }
					columns={ [
						{
							id: 'title',
							label: 'Title',
							render: ( r ) => r.title,
						},
					] }
					search={ {
						label: 'Search courses',
						submitLabel: 'Search Courses',
						value: search,
						onChange: setSearch,
					} }
					filters={
						<ListTableSelect
							label="Status"
							value={ status }
							options={ [
								{ value: 'all', label: 'All' },
								{ value: 'draft', label: 'Draft' },
							] }
							onChange={ setStatus }
						/>
					}
					onFilter={ () => onFilter( search, status ) }
					pagination={ {
						page: 2,
						totalPages: 4,
						totalItems: 80,
						onChange: onPage,
					} }
				/>
			);
		}
		const { container } = render( <Filtered /> );
		expect(
			container.querySelector( '.tablenav.top .alignleft.actions select' )
		).not.toBeNull();
		await user.type(
			screen.getByLabelText( 'Search courses' ),
			'alg{Enter}'
		);
		expect( onFilter ).toHaveBeenLastCalledWith( 'alg', 'all' );
		await user.selectOptions( screen.getByLabelText( 'Status' ), 'draft' );
		await user.click( screen.getByRole( 'button', { name: 'Filter' } ) );
		expect( onFilter ).toHaveBeenLastCalledWith( 'alg', 'draft' );
		await user.click(
			screen.getAllByRole( 'button', { name: 'Next page' } )[ 0 ]!
		);
		expect( onPage ).toHaveBeenLastCalledWith( 3 );
		await user.click(
			screen.getAllByRole( 'button', { name: 'First page' } )[ 1 ]!
		);
		expect( onPage ).toHaveBeenLastCalledWith( 1 );
		const current = screen.getByLabelText( 'Current Page' );
		await user.clear( current );
		await user.type( current, '9{Enter}' );
		expect( onPage ).toHaveBeenLastCalledWith( 4 );
		expect( onFilter ).toHaveBeenCalledTimes( 2 );
		expect(
			container.querySelector( '.tablenav.bottom .paging-input' )
		).toHaveTextContent( '2 of 4' );
	} );

	it( 'disables the boundary page links on the first and last page', () => {
		const { container } = table( {
			pagination: { page: 1, totalPages: 1, onChange: () => {} },
		} );
		expect( container.querySelector( '.tablenav-pages' ) ).toHaveClass(
			'one-page'
		);
		expect(
			screen.queryByRole( 'button', { name: 'Next page' } )
		).toBeNull();
		expect(
			container.querySelectorAll( '.tablenav-pages-navspan.disabled' )
		).toHaveLength( 8 );
	} );

	it( 'renders views with counts and the current view', async () => {
		const user = userEvent.setup();
		const onSelect = vi.fn();
		const { container } = table( {
			views: [
				{ key: 'all', label: 'All', count: 3, current: true },
				{ key: 'trash', label: 'Trash', count: 1, onSelect },
			],
		} );
		const list = container.querySelector( 'ul.subsubsub' );
		expect( list ).toHaveTextContent( 'All (3) | Trash (1)' );
		expect(
			screen.getByRole( 'link', { name: 'All (3)' } )
		).toHaveAttribute( 'aria-current', 'page' );
		await user.click( screen.getByRole( 'link', { name: 'Trash (1)' } ) );
		expect( onSelect ).toHaveBeenCalled();
	} );

	it( 'gives views, sorting and pages real addresses and leaves modified clicks to the browser', async () => {
		const onSelect = vi.fn();
		const onPage = vi.fn();
		const onSort = vi.fn();
		table( {
			views: [
				{ key: 'all', label: 'All', current: true, href: '?' },
				{
					key: 'draft',
					label: 'Draft',
					href: '?status=draft',
					onSelect,
				},
			],
			sort: {
				orderby: 'status',
				order: 'asc',
				onChange: onSort,
				href: ( orderby, order ) =>
					`?orderby=${ orderby }&order=${ order }`,
			},
			pagination: {
				page: 2,
				totalPages: 3,
				onChange: onPage,
				href: ( page ) => `?page=${ page }`,
			},
		} );
		const draft = screen.getByRole( 'link', { name: 'Draft' } );
		expect( draft ).toHaveAttribute( 'href', '?status=draft' );
		const [ next ] = screen.getAllByRole( 'link', { name: 'Next page' } );
		expect( next ).toHaveAttribute( 'href', '?page=3' );
		expect( next ).toHaveClass( 'next-page', 'button' );
		expect(
			screen.getAllByRole( 'link', { name: 'First page' } )[ 0 ]
		).toHaveAttribute( 'href', '?page=1' );
		const [ title ] = screen.getAllByRole( 'link', {
			name: 'Title Sort ascending.',
		} );
		expect( title ).toHaveAttribute( 'href', '?orderby=title&order=asc' );

		// A modified click opens the address (the browser's default is not prevented).
		expect( fireEvent.click( next!, { ctrlKey: true } ) ).toBe( true );
		expect( fireEvent.click( draft, { metaKey: true } ) ).toBe( true );
		expect( fireEvent.click( title!, { shiftKey: true } ) ).toBe( true );
		expect( onPage ).not.toHaveBeenCalled();
		expect( onSelect ).not.toHaveBeenCalled();
		expect( onSort ).not.toHaveBeenCalled();

		// A plain click is handled in place.
		expect( fireEvent.click( next! ) ).toBe( false );
		expect( onPage ).toHaveBeenCalledWith( 3 );
		expect( fireEvent.click( draft ) ).toBe( false );
		expect( onSelect ).toHaveBeenCalled();
		expect( fireEvent.click( title! ) ).toBe( false );
		expect( onSort ).toHaveBeenCalledWith( 'title', 'asc' );
	} );

	it( 'prints a screen-reader heading for the list', () => {
		table( { heading: 'Courses list' } );
		expect(
			screen.getByRole( 'heading', { level: 2, name: 'Courses list' } )
		).toHaveClass( 'screen-reader-text' );
	} );
} );
