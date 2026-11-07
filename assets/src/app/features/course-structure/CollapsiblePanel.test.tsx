import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { CollapsiblePanel } from './CollapsiblePanel';

describe( 'collapsible panel', () => {
	it( 'starts folded, opens from its header and keeps the body mounted', async () => {
		const user = userEvent.setup();
		render(
			<CollapsiblePanel title="Settings" summary="2 targets">
				<input aria-label="Staged edit" defaultValue="" />
			</CollapsiblePanel>
		);
		const toggle = screen.getByRole( 'button', { name: /Settings/ } );
		expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
		expect( screen.getByText( '2 targets' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Staged edit' ) ).not.toBeVisible();
		await user.click( toggle );
		expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
		const field = screen.getByLabelText( 'Staged edit' );
		expect( field ).toBeVisible();
		await user.type( field, 'keep me' );
		await user.click( toggle );
		expect( field ).not.toBeVisible();
		await user.click( toggle );
		expect( screen.getByLabelText( 'Staged edit' ) ).toHaveValue(
			'keep me'
		);
	} );

	it( 'can start open', () => {
		render(
			<CollapsiblePanel title="Open one" defaultOpen>
				<p>Body</p>
			</CollapsiblePanel>
		);
		expect( screen.getByText( 'Body' ) ).toBeVisible();
	} );

	it( 'follows the open state it is given and reports toggles', async () => {
		const user = userEvent.setup();
		const onToggle = vi.fn();
		const { rerender } = render(
			<CollapsiblePanel
				title="Skills"
				open={ false }
				onToggle={ onToggle }
			>
				<p>Body</p>
			</CollapsiblePanel>
		);
		await user.click( screen.getByRole( 'button', { name: /Skills/ } ) );
		expect( onToggle ).toHaveBeenCalledWith( true );
		expect( screen.getByText( 'Body' ) ).not.toBeVisible();
		rerender(
			<CollapsiblePanel title="Skills" open onToggle={ onToggle }>
				<p>Body</p>
			</CollapsiblePanel>
		);
		expect( screen.getByText( 'Body' ) ).toBeVisible();
	} );

	it( 'mounts a lazy body only once it has been opened', async () => {
		const user = userEvent.setup();
		render(
			<CollapsiblePanel title="Later" lazy>
				<p>Loaded body</p>
			</CollapsiblePanel>
		);
		expect( screen.queryByText( 'Loaded body' ) ).not.toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: /Later/ } ) );
		expect( screen.getByText( 'Loaded body' ) ).toBeVisible();
		await user.click( screen.getByRole( 'button', { name: /Later/ } ) );
		expect( screen.getByText( 'Loaded body' ) ).toBeInTheDocument();
	} );
} );
