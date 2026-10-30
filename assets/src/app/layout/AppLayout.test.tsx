import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { createRegistry } from '../extensions/registry.mjs';
import { ExtensionsProvider } from '../extensions/context';
import { PlaceProvider } from '../routing/place';
import { AppLayout } from './AppLayout';
import { TABS_ID } from './ExtensionTabs';

it( 'provides one main landmark inside WordPress and restores the host on unmount', () => {
	const host = document.createElement( 'div' );
	host.id = 'wpbody';
	host.setAttribute( 'role', 'main' );
	const container = document.createElement( 'div' );
	host.appendChild( container );
	document.body.appendChild( host );
	try {
		const view = render(
			<MemoryRouter>
				<AppLayout />
			</MemoryRouter>,
			{ container }
		);
		expect( screen.getAllByRole( 'main' ) ).toHaveLength( 1 );
		expect( screen.getByRole( 'main' ) ).toHaveAttribute(
			'id',
			'ohmylms-app-main'
		);
		expect( host ).not.toHaveAttribute( 'role' );
		view.unmount();
		expect( host ).toHaveAttribute( 'role', 'main' );
	} finally {
		host.remove();
	}
} );

it( 'adds the extension pages of the current page to the server tab row', () => {
	const tabs = document.createElement( 'nav' );
	tabs.id = TABS_ID;
	tabs.innerHTML = '<a class="nav-tab" href="#">Courses</a>';
	document.body.append( tabs );
	const extensions = createRegistry();
	extensions.registerAdminPage( 'reports-addon', {
		label: 'Add-on reports',
		group: 'content',
		render: () => null,
	} );
	extensions.registerAdminPage( 'tools', {
		label: 'Tools',
		render: () => null,
	} );
	try {
		render(
			<ExtensionsProvider value={ extensions }>
				<PlaceProvider
					value={ {
						page: 'ohmylms-content',
						tab: 'ext-reports-addon',
						section: '',
					} }
				>
					<MemoryRouter>
						<AppLayout />
					</MemoryRouter>
				</PlaceProvider>
			</ExtensionsProvider>
		);
		const added = screen.getByRole( 'link', { name: 'Add-on reports' } );
		expect( tabs ).toContainElement( added );
		expect( added ).toHaveAttribute(
			'href',
			'admin.php?page=ohmylms-content&tab=ext-reports-addon'
		);
		expect( added ).toHaveClass( 'nav-tab', 'nav-tab-active' );
		expect( added ).toHaveAttribute( 'aria-current', 'page' );
		// Pages without a group are Settings tabs.
		expect(
			screen.queryByRole( 'link', { name: 'Tools' } )
		).not.toBeInTheDocument();
	} finally {
		tabs.remove();
	}
} );
