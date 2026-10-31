import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { PlaceProvider } from '../routing/place';
import {
	PageTitleAction,
	PageTitleActions,
	TITLE_ACTIONS_ID,
} from './PageTitleActions';
import { useServerNavBadges } from './serverNavBadges';
import { TABS_ID } from './ExtensionTabs';
import { SECTIONS_ID } from '../routing/QueryRouter';

const place = { page: 'ohmylms-content', tab: 'courses', section: '' };

afterEach( () => document.body.replaceChildren() );

describe( 'PageTitleActions', () => {
	it( 'renders the actions next to the page heading the server printed', async () => {
		const user = userEvent.setup();
		const heading = document.createElement( 'div' );
		heading.innerHTML = `<h1 class="wp-heading-inline">Content Hub</h1><span id="${ TITLE_ACTIONS_ID }"></span>`;
		document.body.append( heading );
		const onAdd = vi.fn();
		render(
			<PlaceProvider value={ place }>
				<MemoryRouter>
					<PageTitleActions>
						<PageTitleAction onClick={ onAdd }>
							Add course
						</PageTitleAction>
						<PageTitleAction to="/courses?action=import">
							Import
						</PageTitleAction>
					</PageTitleActions>
				</MemoryRouter>
			</PlaceProvider>
		);
		const slot = document.getElementById( TITLE_ACTIONS_ID );
		const add = screen.getByRole( 'button', { name: 'Add course' } );
		expect( slot ).toContainElement( add );
		expect( add ).toHaveClass( 'page-title-action' );
		const link = screen.getByRole( 'link', { name: 'Import' } );
		expect( slot ).toContainElement( link );
		expect( link ).toHaveAttribute( 'href', '/courses?action=import' );
		await user.click( add );
		expect( onAdd ).toHaveBeenCalled();
	} );

	it( 'renders in place outside the admin pages', () => {
		render(
			<MemoryRouter>
				<PageTitleActions>
					<PageTitleAction onClick={ () => {} } disabled>
						Add quiz
					</PageTitleAction>
				</PageTitleActions>
			</MemoryRouter>
		);
		expect(
			screen.getByRole( 'button', { name: 'Add quiz' } )
		).toBeDisabled();
	} );
} );

function Marked( {
	kind,
	badges,
}: {
	kind: 'tab' | 'section';
	badges: Record< string, string | undefined >;
} ) {
	useServerNavBadges( kind, badges );
	return null;
}

describe( 'useServerNavBadges', () => {
	it( 'marks server tab and section links and removes the marks', () => {
		const nav = document.createElement( 'div' );
		nav.innerHTML =
			`<nav id="${ TABS_ID }"><a data-tab="schools" href="#">Schools</a><a data-tab="classes" href="#">Classes</a></nav>` +
			`<ul id="${ SECTIONS_ID }"><li><a data-section="tax" href="#">Tax</a></li></ul>`;
		document.body.append( nav );
		const view = render(
			<>
				<Marked kind="tab" badges={ { classes: '(unsaved)' } } />
				<Marked kind="section" badges={ { tax: '(unsaved)' } } />
			</>
		);
		const link = ( id: string ) =>
			nav.querySelector(
				`[data-tab="${ id }"], [data-section="${ id }"]`
			);
		expect( link( 'classes' ) ).toHaveTextContent( 'Classes (unsaved)', {
			normalizeWhitespace: false,
		} );
		expect( link( 'classes' ) ).toHaveAttribute(
			'data-ohmylms-badge',
			'(unsaved)'
		);
		expect( link( 'tax' ) ).toHaveTextContent( 'Tax (unsaved)' );
		expect( link( 'schools' ) ).toHaveTextContent( /^Schools$/ );
		expect( link( 'schools' ) ).not.toHaveAttribute( 'data-ohmylms-badge' );
		view.rerender(
			<>
				<Marked kind="tab" badges={ {} } />
				<Marked kind="section" badges={ { tax: '(unsaved)' } } />
			</>
		);
		expect( link( 'classes' ) ).toHaveTextContent( /^Classes$/ );
		expect( link( 'classes' ) ).not.toHaveAttribute( 'data-ohmylms-badge' );
		view.unmount();
		expect( link( 'tax' ) ).toHaveTextContent( /^Tax$/ );
	} );
} );
