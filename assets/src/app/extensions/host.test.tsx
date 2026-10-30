import { act, render, screen } from '@testing-library/react';
import {
	createExtensionHost,
	installExtensionHost,
	isExtensionHost,
} from './host';

function quietErrors() {
	vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
	const prevent = ( event: ErrorEvent ) => event.preventDefault();
	window.addEventListener( 'error', prevent );
	return () => window.removeEventListener( 'error', prevent );
}

describe( 'extension host', () => {
	afterEach( () => {
		delete window.ohmylms;
	} );

	it( 'publishes a frozen API v1 object and announces it', () => {
		const ready = vi.fn();
		window.addEventListener( 'ohmylms:extensions-ready', ready );

		const host = installExtensionHost( window );

		expect( window.ohmylms?.extensions ).toBe( host );
		expect( isExtensionHost( host ) ).toBe( true );
		expect( Object.isFrozen( host ) ).toBe( true );
		expect( host.version ).toBe( 1 );
		expect( ready ).toHaveBeenCalledTimes( 1 );
		expect( ( ready.mock.calls[ 0 ]?.[ 0 ] as CustomEvent ).detail ).toBe(
			host
		);
		for ( const method of [
			'registerAdminPage',
			'registerEditorPanel',
			'registerQuestionEditor',
			'registerLessonEditor',
			'registerSlot',
			'registerMembershipSettings',
			'registerCheckoutField',
			'registerIntegration',
			'list',
			'get',
			'subscribe',
			'getRevision',
			'renderSlot',
			'renderEditor',
			'mount',
		] as const ) {
			expect( typeof host[ method ] ).toBe( 'function' );
		}
		expect( typeof host.api.getExtensionSettings ).toBe( 'function' );
		window.removeEventListener( 'ohmylms:extensions-ready', ready );
	} );

	it( 'keeps an existing host and its registrations', () => {
		const first = installExtensionHost( window );
		first.registerSlot( 'kept', { label: 'Kept', render: () => null } );

		expect( installExtensionHost( window ) ).toBe( first );
		expect( first.get( 'slot', 'kept' ) ).toBeDefined();
	} );

	it( 'renders a slot with its context and updates for later registrations', () => {
		const host = createExtensionHost();
		host.registerSlot( 'note', {
			label: 'Note',
			slot: 'student.course.after',
			render: ( { contentId }: { contentId: number } ) => (
				<p>Course { contentId }</p>
			),
		} );
		host.registerSlot( 'elsewhere', {
			label: 'Elsewhere',
			slot: 'checkout.fields.after',
			render: () => <p>Wrong slot</p>,
		} );

		render( host.renderSlot( 'student.course.after', { contentId: 7 } ) );

		expect( screen.getByText( 'Course 7' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Wrong slot' ) ).not.toBeInTheDocument();

		act( () => {
			host.registerSlot( 'late', {
				label: 'Late',
				slot: 'student.course.after',
				priority: 1,
				render: () => <p>Late entry</p>,
			} );
		} );
		expect( screen.getByText( 'Late entry' ) ).toBeInTheDocument();
	} );

	it( 'skips entries whose when() rejects the context', () => {
		const host = createExtensionHost();
		host.registerSlot( 'home-only', {
			label: 'Home only',
			when: ( context ) => context.route === '/',
			render: () => <p>Home note</p>,
		} );

		const { rerender } = render(
			host.renderSlot( 'admin.screen.before', { route: '/courses' } )
		);
		expect( screen.queryByText( 'Home note' ) ).not.toBeInTheDocument();

		rerender( host.renderSlot( 'admin.screen.before', { route: '/' } ) );
		expect( screen.getByText( 'Home note' ) ).toBeInTheDocument();
	} );

	it( 'isolates a failing extension and reports it', () => {
		const restore = quietErrors();
		const host = createExtensionHost();
		const reported = vi.fn();
		window.addEventListener( 'ohmylms:extension-error', reported );
		host.registerSlot( 'broken', {
			label: 'Broken',
			render: () => {
				throw new Error( 'Extension failure' );
			},
		} );
		host.registerSlot( 'healthy', {
			label: 'Healthy',
			render: () => <p>Still here</p>,
		} );

		render( host.renderSlot( 'admin.screen.after' ) );

		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'This extension could not be displayed.'
		);
		expect( screen.getByText( 'Still here' ) ).toBeInTheDocument();
		expect(
			( reported.mock.calls[ 0 ]?.[ 0 ] as CustomEvent ).detail
		).toEqual( { id: 'broken', message: 'Extension failure' } );
		window.removeEventListener( 'ohmylms:extension-error', reported );
		restore();
	} );

	it( 'renders one editor by id, and nothing for unknown or disabled editors', () => {
		const host = createExtensionHost();
		host.registerQuestionEditor( 'number', {
			label: 'Number',
			render: ( { type }: { type: string } ) => <p>Editor { type }</p>,
		} );
		host.registerQuestionEditor( 'off', {
			label: 'Off',
			enabled: false,
			render: () => <p>Disabled editor</p>,
		} );

		render( host.renderEditor( 'question-editor', 'number' ) ?? <></> );

		expect( screen.getByText( 'Editor number' ) ).toBeInTheDocument();
		expect( host.renderEditor( 'question-editor', 'missing' ) ).toBeNull();
		expect( host.renderEditor( 'question-editor', 'off' ) ).toBeNull();
	} );

	it( 'mounts a slot into an element outside React and re-renders in place', async () => {
		const host = createExtensionHost();
		host.registerSlot( 'footer', {
			label: 'Footer',
			render: ( { label }: { label: string } ) => <p>{ label }</p>,
		} );
		const element = document.createElement( 'div' );
		document.body.append( element );

		await act( async () =>
			host.mount( element, 'student.course.after', { label: 'First' } )
		);
		expect( element ).toHaveTextContent( 'First' );

		await act( async () =>
			host.mount( element, 'student.course.after', { label: 'Second' } )
		);
		expect( element ).toHaveTextContent( 'Second' );
		expect( element.querySelectorAll( 'p' ) ).toHaveLength( 1 );
		element.remove();
	} );
} );
