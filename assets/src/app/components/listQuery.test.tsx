import { act, render, renderHook, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { QueryRouter } from '../routing/QueryRouter';
import type { FeatureDefinition } from '../features/types';
import { useListQuery } from './listQuery';

const DEFAULTS = { status: 'all', s: '', orderby: 'date', order: 'desc' };

function List() {
	const query = useListQuery( DEFAULTS );
	const location = useLocation();
	return (
		<>
			<p data-testid="state">
				{ query.values.status } { query.values.s || '-' } { query.page }
			</p>
			<p data-testid="search">{ location.search }</p>
			<a href={ query.href( { status: 'draft' } ) }>Draft</a>
			<a href={ query.href( { status: 'publish' } ) }>Published</a>
			<a href={ query.href( { page: 3 } ) }>Page 3</a>
			<button onClick={ () => query.set( { s: 'alg' } ) }>Search</button>
			<button onClick={ () => query.set( { page: 2 } ) }>Next</button>
		</>
	);
}

describe( 'useListQuery', () => {
	it( 'reads the state from the route query and builds addresses with changes', async () => {
		const user = userEvent.setup();
		render(
			<MemoryRouter
				initialEntries={ [
					'/courses?status=publish&page=2&action=new',
				] }
			>
				<Routes>
					<Route path="/courses" element={ <List /> } />
				</Routes>
			</MemoryRouter>
		);
		expect( screen.getByTestId( 'state' ) ).toHaveTextContent(
			'publish - 2'
		);
		// Changing a filter goes back to the first page and keeps other parameters.
		expect( screen.getByRole( 'link', { name: 'Draft' } ) ).toHaveAttribute(
			'href',
			'/courses?status=draft&action=new'
		);
		expect(
			screen.getByRole( 'link', { name: 'Page 3' } )
		).toHaveAttribute(
			'href',
			'/courses?status=publish&page=3&action=new'
		);
		// The current view's link opens its first page, as on WordPress lists.
		expect(
			screen.getByRole( 'link', { name: 'Published' } )
		).toHaveAttribute( 'href', '/courses?status=publish&action=new' );
		await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
		expect( screen.getByTestId( 'state' ) ).toHaveTextContent(
			'publish alg 1'
		);
		await user.click( screen.getByRole( 'button', { name: 'Next' } ) );
		expect( screen.getByTestId( 'search' ) ).toHaveTextContent(
			'?status=publish&action=new&s=alg&page=2'
		);
	} );

	it( 'keeps the state in the admin address (`paged`) on the admin pages', async () => {
		const user = userEvent.setup();
		window.history.replaceState(
			null,
			'',
			'/wp-admin/admin.php?page=ohmylms-content&tab=courses&status=draft'
		);
		const features: FeatureDefinition[] = [
			{
				id: 'courses',
				title: 'Courses',
				path: '/courses',
				group: 'content',
				tab: 'courses',
				component: () => null,
			},
		];
		render(
			<QueryRouter
				features={ features }
				place={ {
					page: 'ohmylms-content',
					tab: 'courses',
					section: '',
				} }
				load={ vi.fn() }
			>
				<Routes>
					<Route path="/courses" element={ <List /> } />
				</Routes>
			</QueryRouter>
		);
		expect( screen.getByTestId( 'state' ) ).toHaveTextContent(
			'draft - 1'
		);
		expect(
			screen.getByRole( 'link', { name: 'Page 3' } )
		).toHaveAttribute(
			'href',
			'admin.php?page=ohmylms-content&tab=courses&status=draft&paged=3'
		);
		await user.click( screen.getByRole( 'link', { name: 'Page 3' } ) );
		expect( window.location.search ).toBe(
			'?page=ohmylms-content&tab=courses&status=draft&paged=3'
		);
		expect( screen.getByTestId( 'state' ) ).toHaveTextContent(
			'draft - 3'
		);
		window.history.replaceState( null, '', '/' );
	} );

	it( 'keeps the state in memory outside a router', () => {
		const view = renderHook( () => useListQuery( DEFAULTS ) );
		expect( view.result.current.values.status ).toBe( 'all' );
		const key = view.result.current.key;
		act( () => view.result.current.set( { status: 'trash' } ) );
		expect( view.result.current.values.status ).toBe( 'trash' );
		expect( view.result.current.key ).not.toBe( key );
		act( () => view.result.current.set( { page: 4 } ) );
		expect( view.result.current.page ).toBe( 4 );
		expect( view.result.current.href( { status: 'all' } ) ).toBe( '?' );
	} );
} );
