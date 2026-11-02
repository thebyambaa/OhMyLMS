import { act, screen } from '@testing-library/react';
import { mountSlots } from './dom';
import { createExtensionHost } from './host';

describe( 'mountSlots', () => {
	afterEach( () => {
		document.body.replaceChildren();
	} );

	it( 'mounts slots and checkout fields with their context', async () => {
		document.body.innerHTML = `
			<div data-ohmylms-slot="student.course.after" data-ohmylms-context='{"contentId":7}'></div>
			<div data-ohmylms-slot="checkout.fields.after" data-ohmylms-kind="checkout-field" data-ohmylms-context="{}"></div>`;
		const host = createExtensionHost();
		host.registerSlot( 'note', {
			label: 'Note',
			slot: 'student.course.after',
			render: ( { contentId }: { contentId: number } ) => (
				<p>Course { contentId }</p>
			),
		} );
		host.registerCheckoutField( 'help', {
			label: 'Help',
			slot: 'checkout.fields.after',
			render: () => <p>Checkout help</p>,
		} );

		act( () => mountSlots( host ) );

		expect( await screen.findByText( 'Course 7' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Checkout help' ) ).toBeInTheDocument();
	} );

	it( 'passes the kind and skips elements whose context is not JSON', () => {
		document.body.innerHTML = `
			<div data-ohmylms-slot="checkout.summary.after" data-ohmylms-kind="checkout-field" data-ohmylms-context='{"contentId":3}'></div>
			<div data-ohmylms-slot="student.course.before" data-ohmylms-context="not json"></div>
			<div data-ohmylms-slot="student.course.before"></div>`;
		const mount = vi.fn();

		mountSlots( { mount } );

		expect( mount.mock.calls ).toEqual( [
			[
				document.body.children[ 0 ],
				'checkout.summary.after',
				{ contentId: 3 },
				'checkout-field',
			],
			[
				document.body.children[ 2 ],
				'student.course.before',
				{},
				'slot',
			],
		] );
	} );
} );
