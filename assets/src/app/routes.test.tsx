import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { lazy } from '@wordpress/element';
import { Link } from 'react-router-dom';
import { createRegistry } from './extensions/registry.mjs';
import type { FeatureDefinition } from './features/types';
import { renderShell } from './test/render';

function page( heading: string ) {
	return lazy( () =>
		Promise.resolve( {
			default: () => (
				<>
					<h2>{ heading }</h2>
					<Link to="/courses">Open courses</Link>
				</>
			),
		} )
	);
}

function createFeatures(
	overrides: Partial< FeatureDefinition > = {}
): FeatureDefinition[] {
	return [
		{
			id: 'home',
			title: 'Home',
			path: '/',
			group: 'dashboard',
			component: page( 'Home page' ),
		},
		{
			id: 'courses',
			title: 'Courses',
			path: '/courses',
			group: 'content',
			tab: 'courses',
			component: page( 'Courses page' ),
			...overrides,
		},
	];
}

describe( 'app shell', () => {
	it( 'renders the route in one main landmark, without a header or navigation of its own', async () => {
		renderShell( createFeatures(), { route: '/courses' } );

		expect( screen.getByRole( 'main' ) ).toBeInTheDocument();
		expect(
			await screen.findByRole( 'heading', { name: 'Courses page' } )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'banner' ) ).not.toBeInTheDocument();
		expect( screen.queryByRole( 'navigation' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'heading', { level: 1 } )
		).not.toBeInTheDocument();
	} );

	it( 'shows a loading status while a lazy route loads, then the route', async () => {
		renderShell( createFeatures() );

		expect( screen.getByRole( 'status' ) ).toHaveTextContent( 'Loading' );
		expect(
			await screen.findByRole( 'heading', { name: 'Home page' } )
		).toBeInTheDocument();
	} );

	it( 'navigates between lazy routes and moves focus to main', async () => {
		const user = userEvent.setup();
		renderShell( createFeatures() );
		await screen.findByRole( 'heading', { name: 'Home page' } );

		await user.click(
			screen.getByRole( 'link', { name: 'Open courses' } )
		);

		expect(
			await screen.findByRole( 'heading', { name: 'Courses page' } )
		).toBeInTheDocument();
		expect( screen.getByRole( 'main' ) ).toHaveFocus();
	} );

	it( 'renders the 404 route with a way back', async () => {
		renderShell( createFeatures(), { route: '/nope' } );

		expect(
			await screen.findByRole( 'heading', { name: 'Page not found' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Go to the start page' } )
		).toHaveAttribute( 'href', '/' );
	} );

	it( 'hides features the user lacks the capability for, and 404s their route', async () => {
		renderShell( createFeatures( { capability: 'manage_options' } ), {
			route: '/courses',
			capabilities: { manage_options: false },
		} );

		expect(
			await screen.findByRole( 'heading', { name: 'Page not found' } )
		).toBeInTheDocument();
	} );
} );

describe( 'extension surfaces', () => {
	function withExtensionPages(
		overrides: Partial< FeatureDefinition > = {}
	): FeatureDefinition[] {
		return [
			...createFeatures( overrides ),
			{
				id: 'extension-pages',
				title: 'Extensions',
				path: '/extensions/:id',
				group: 'settings',
				tab: 'ext-:id',
				hidden: true,
				component: lazy(
					() => import( './features/extensions/ExtensionPage' )
				),
			},
		];
	}

	it( 'routes registered admin pages', async () => {
		const extensions = createRegistry();
		extensions.registerAdminPage( 'example-page', {
			label: 'Example page',
			render: () => <h2>Example extension page</h2>,
		} );
		renderShell( withExtensionPages(), {
			route: '/extensions/example-page',
			extensions,
		} );

		expect(
			await screen.findByRole( 'heading', {
				name: 'Example extension page',
			} )
		).toBeInTheDocument();
	} );

	it( '404s an unknown or disabled extension page', async () => {
		const extensions = createRegistry();
		extensions.registerAdminPage( 'off', {
			label: 'Off',
			enabled: false,
			render: () => <h2>Disabled page</h2>,
		} );
		renderShell( withExtensionPages(), {
			route: '/extensions/off',
			extensions,
		} );

		expect(
			await screen.findByRole( 'heading', { name: 'Page not found' } )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Disabled page' ) ).not.toBeInTheDocument();
	} );

	it( 'renders screen slots and editor panels with the extension route, params and hash', async () => {
		const extensions = createRegistry();
		extensions.registerSlot( 'before', {
			label: 'Before',
			slot: 'admin.screen.before',
			when: ( context ) => context.route === '/course-edit/:id',
			render: ( { id, hash }: { id: string; hash: string } ) => (
				<p>
					Before course { id } at { hash }
				</p>
			),
		} );
		extensions.registerSlot( 'after', {
			label: 'After',
			slot: 'admin.screen.after',
			render: () => <p>After every screen</p>,
		} );
		extensions.registerEditorPanel( 'panel', {
			label: 'Panel',
			slot: '/course-edit/:id',
			render: ( { route }: { route: string } ) => (
				<p>Panel for { route }</p>
			),
		} );
		extensions.registerEditorPanel( 'other-panel', {
			label: 'Other panel',
			slot: '/quiz-edit/:id',
			render: () => <p>Quiz panel</p>,
		} );
		renderShell(
			createFeatures( {
				path: '/courses/:id',
				extensionRoute: '/course-edit/:id',
			} ),
			{ route: '/courses/12?tab=outline', extensions }
		);

		expect(
			await screen.findByRole( 'heading', { name: 'Courses page' } )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Before course 12 at #/courses/12?tab=outline' )
		).toBeInTheDocument();
		expect( screen.getByText( 'After every screen' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Panel for /course-edit/:id' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Quiz panel' ) ).not.toBeInTheDocument();
	} );

	it( 'keeps the screen when an extension slot fails', async () => {
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		window.addEventListener( 'error', ( event ) => event.preventDefault() );
		const extensions = createRegistry();
		extensions.registerSlot( 'broken', {
			label: 'Broken',
			slot: 'admin.screen.after',
			render: () => {
				throw new Error( 'Slot failure' );
			},
		} );
		renderShell( createFeatures(), { route: '/courses', extensions } );

		expect(
			await screen.findByRole( 'heading', { name: 'Courses page' } )
		).toBeInTheDocument();
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'This extension could not be displayed.'
		);
	} );
} );

describe( 'route error boundary', () => {
	it( 'shows an alert with a retry button that renders the route again', async () => {
		const user = userEvent.setup();
		let shouldFail = true;
		function Flaky() {
			if ( shouldFail ) {
				throw new Error( 'Boom' );
			}
			return <h2>Recovered</h2>;
		}
		// React logs caught render errors; keep the test output readable.
		vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
		// jsdom also reports the thrown error as an uncaught window error.
		window.addEventListener( 'error', ( event ) => event.preventDefault() );

		renderShell( [
			{
				id: 'flaky',
				title: 'Flaky',
				path: '/',
				group: 'dashboard',
				component: lazy( () => Promise.resolve( { default: Flaky } ) ),
			},
		] );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
			'Boom'
		);

		shouldFail = false;
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );

		await waitFor( () =>
			expect(
				screen.getByRole( 'heading', { name: 'Recovered' } )
			).toBeInTheDocument()
		);
		expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
	} );
} );
