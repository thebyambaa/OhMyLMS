import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { OutlineNavigator } from './OutlineNavigator';
import { outline } from './fixtures';

function matchMedia( matches: boolean ) {
	window.matchMedia = vi.fn( () => ( { matches } ) ) as never;
}

afterEach( () => {
	// jsdom has no matchMedia; remove the stub so other tests see that.
	delete ( window as { matchMedia?: unknown } ).matchMedia;
} );

function renderNavigator() {
	return render(
		<OutlineNavigator
			draft={ outline }
			onNavigate={ vi.fn() }
			onConnect={ vi.fn() }
		/>
	);
}

describe( 'outline navigator', () => {
	it( 'starts open as a side rail on wide pages', () => {
		matchMedia( true );
		const { container } = renderNavigator();
		expect( container.querySelector( 'details' ) ).toHaveAttribute(
			'open'
		);
	} );

	it( 'starts folded away on narrower pages and shows how many units it holds', () => {
		matchMedia( false );
		const { container } = renderNavigator();
		expect( container.querySelector( 'details' ) ).not.toHaveAttribute(
			'open'
		);
		expect( screen.getByText( '2 units' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'heading', { name: 'Course outline' } )
		).toBeInTheDocument();
	} );

	it( 'opens and closes from its heading', async () => {
		matchMedia( false );
		const user = userEvent.setup();
		const { container } = renderNavigator();
		await user.click( screen.getByText( 'Course outline' ) );
		expect( container.querySelector( 'details' ) ).toHaveAttribute(
			'open'
		);
		await user.click( screen.getByText( 'Course outline' ) );
		expect( container.querySelector( 'details' ) ).not.toHaveAttribute(
			'open'
		);
	} );

	it( 'starts folded when the page cannot report its width', () => {
		const { container } = renderNavigator();
		expect( container.querySelector( 'details' ) ).not.toHaveAttribute(
			'open'
		);
	} );
} );
