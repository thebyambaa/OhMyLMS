import { act, render, renderHook, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
	Link,
	MemoryRouter,
	Route,
	Routes,
	useLocation,
} from 'react-router-dom';
import type { ReactNode } from 'react';
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard';
import type { UnsavedChangesGuardOptions } from './useUnsavedChangesGuard';

function Harness( {
	dirty,
	options,
}: {
	dirty: boolean;
	options?: UnsavedChangesGuardOptions;
} ) {
	const guard = useUnsavedChangesGuard( dirty, options );
	const { search } = useLocation();
	return (
		<div>
			<Link to="/other">Other page</Link>
			<a href="#/hash-route">Hash link</a>
			<Link to="/here?section=b">Next section</Link>
			<a href="https://example.com/">External</a>
			<a href="/new-tab" target="_blank">
				New tab
			</a>
			<a href="#/other" download>
				Download
			</a>
			<span data-testid="pending">{ guard.pendingPath ?? 'none' }</span>
			<span data-testid="search">{ search || 'none' }</span>
			<button onClick={ guard.stay }>Stay</button>
			<button onClick={ guard.leave }>Leave</button>
			<button onClick={ () => guard.requestLeave( '/other' ) }>
				Back to list
			</button>
		</div>
	);
}

const wrap = (
	dirty: boolean,
	options?: UnsavedChangesGuardOptions
): ReactNode => (
	<MemoryRouter initialEntries={ [ '/here' ] }>
		<Routes>
			<Route
				path="/here"
				element={ <Harness dirty={ dirty } options={ options } /> }
			/>
			<Route path="/other" element={ <p>Arrived</p> } />
			<Route path="/hash-route" element={ <p>Hash arrived</p> } />
		</Routes>
	</MemoryRouter>
);

/**
 * Stop jsdom from following a link the guard lets through.
 *
 * @param name Accessible name of the link.
 */
function keepHere( name: string ) {
	const link = screen.getByRole( 'link', { name } );
	link.addEventListener( 'click', ( event ) => event.preventDefault() );
	return link;
}

describe( 'useUnsavedChangesGuard', () => {
	it( 'does nothing while the form is clean', async () => {
		const user = userEvent.setup();
		render( wrap( false ) );
		await user.click( screen.getByRole( 'link', { name: 'Other page' } ) );
		expect( await screen.findByText( 'Arrived' ) ).toBeInTheDocument();
	} );

	it( 'holds an in-app link while dirty, then continues or stays', async () => {
		const user = userEvent.setup();
		render( wrap( true ) );
		await user.click( screen.getByRole( 'link', { name: 'Other page' } ) );
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent( '/other' );
		await user.click( screen.getByRole( 'button', { name: 'Stay' } ) );
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent( 'none' );
		await user.click( screen.getByRole( 'link', { name: 'Hash link' } ) );
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent(
			'/hash-route'
		);
		await user.click( screen.getByRole( 'button', { name: 'Leave' } ) );
		expect( await screen.findByText( 'Hash arrived' ) ).toBeInTheDocument();
	} );

	it( 'leaves external, new-tab, download and modified clicks alone', async () => {
		const user = userEvent.setup();
		render( wrap( true ) );
		await user.click( keepHere( 'External' ) );
		await user.click( keepHere( 'New tab' ) );
		await user.click( keepHere( 'Download' ) );
		await user.keyboard( '{Control>}' );
		await user.click( keepHere( 'Other page' ) );
		await user.keyboard( '{/Control}' );
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent( 'none' );
	} );

	it( 'asks the browser to confirm closing the page only while dirty', () => {
		const view = render( wrap( true ) );
		const dirtyEvent = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( dirtyEvent );
		expect( dirtyEvent.defaultPrevented ).toBe( true );
		view.rerender( wrap( false ) );
		const cleanEvent = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( cleanEvent );
		expect( cleanEvent.defaultPrevented ).toBe( false );
	} );

	it( 'stops intercepting once unmounted', () => {
		const link = document.createElement( 'a' );
		link.setAttribute( 'href', '#/webhooks' );
		document.body.append( link );
		const wrapper = ( { children }: { children: ReactNode } ) => (
			<MemoryRouter initialEntries={ [ '/emails?email=x' ] }>
				{ children }
			</MemoryRouter>
		);
		const view = renderHook( () => useUnsavedChangesGuard( true ), {
			wrapper,
		} );
		const click = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		act( () => {
			link.dispatchEvent( click );
		} );
		expect( click.defaultPrevented ).toBe( true );
		expect( view.result.current.pendingPath ).toBe( '/webhooks' );

		view.unmount();
		const free = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		link.dispatchEvent( free );
		expect( free.defaultPrevented ).toBe( false );
		const unload = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( unload );
		expect( unload.defaultPrevented ).toBe( false );
		link.remove();
	} );

	it( 'navigates in code at once when clean and waits for a decision when dirty', async () => {
		const user = userEvent.setup();
		const view = render( wrap( true ) );
		const back = () =>
			screen.getByRole( 'button', { name: 'Back to list' } );
		await user.click( back() );
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent( '/other' );
		await user.click( screen.getByRole( 'button', { name: 'Stay' } ) );
		view.rerender( wrap( false ) );
		await user.click( back() );
		expect( await screen.findByText( 'Arrived' ) ).toBeInTheDocument();
	} );

	it( 'treats a link to another section of the same screen as leaving by default', async () => {
		const user = userEvent.setup();
		render( wrap( true ) );
		await user.click(
			screen.getByRole( 'link', { name: 'Next section' } )
		);
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent(
			'/here?section=b'
		);
	} );

	it( 'lets section links through with ignoreSamePath but still guards other routes', async () => {
		const user = userEvent.setup();
		render( wrap( true, { ignoreSamePath: true } ) );
		await user.click(
			screen.getByRole( 'link', { name: 'Next section' } )
		);
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent( 'none' );
		expect( screen.getByTestId( 'search' ) ).toHaveTextContent(
			'?section=b'
		);
		await user.click( screen.getByRole( 'link', { name: 'Other page' } ) );
		expect( screen.getByTestId( 'pending' ) ).toHaveTextContent( '/other' );
	} );
} );
