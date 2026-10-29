import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AppLayout } from './AppLayout';

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
				<AppLayout features={ [] } />
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
