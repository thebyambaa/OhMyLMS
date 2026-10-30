import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
	Link,
	Route,
	Routes,
	useParams,
	useSearchParams,
} from 'react-router-dom';
import type { FeatureDefinition } from '../features/types';
import type { AdminPlace } from './address';
import { routeFromHref } from './links';
import { useAdminPlace } from './place';
import { hashTarget, QueryRouter, SECTIONS_ID } from './QueryRouter';

const none = () => null;

const features: FeatureDefinition[] = [
	{
		id: 'courses',
		title: 'Courses',
		path: '/courses',
		group: 'content',
		tab: 'courses',
		component: none,
	},
	{
		id: 'courses-editor',
		title: 'Course',
		path: '/courses/:id/:tab?',
		group: 'content',
		tab: 'courses',
		params: { tab: 'view' },
		component: none,
	},
	{
		id: 'orders',
		title: 'Orders',
		path: '/orders',
		group: 'commerce',
		tab: 'orders',
		component: none,
	},
	{
		id: 'settings',
		title: 'Settings',
		path: '/settings',
		group: 'settings',
		tab: 'settings',
		params: { tab: 'section' },
		component: none,
	},
];

function CourseList() {
	const place = useAdminPlace();
	return (
		<>
			<h2>Course list on { place?.page }</h2>
			<Link to="/courses/12/settings">Edit course 12</Link>
			<Link to="/orders">Orders</Link>
		</>
	);
}

function CourseEditor() {
	const { id, tab = 'details' } = useParams();
	return (
		<>
			<h2>
				Course { id } { tab }
			</h2>
			<Link to="/courses">Back to courses</Link>
		</>
	);
}

function SettingsScreen() {
	const [ params ] = useSearchParams();
	return <h2>Settings { params.get( 'tab' ) ?? 'general' }</h2>;
}

function setAddress( search: string, hash = '' ) {
	window.history.replaceState(
		null,
		'',
		`/wp-admin/admin.php?${ search }${ hash }`
	);
}

function renderRouter( place: AdminPlace, load = vi.fn() ) {
	render(
		<QueryRouter features={ features } place={ place } load={ load }>
			<Routes>
				<Route path="/courses" element={ <CourseList /> } />
				<Route path="/courses/:id/:tab?" element={ <CourseEditor /> } />
				<Route path="/settings" element={ <SettingsScreen /> } />
				<Route path="*" element={ <h2>Not found</h2> } />
			</Routes>
		</QueryRouter>
	);
	return load;
}

const content: AdminPlace = {
	page: 'ohmylms-content',
	tab: 'courses',
	section: '',
};

afterEach( () => {
	document.body.replaceChildren();
	setAddress( '' );
} );

describe( 'query router', () => {
	it( 'opens the screen of the address and links to addresses', () => {
		setAddress( 'page=ohmylms-content&tab=courses&id=12&view=settings' );
		renderRouter( content );

		expect(
			screen.getByRole( 'heading', { name: 'Course 12 settings' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Back to courses' } )
		).toHaveAttribute(
			'href',
			'admin.php?page=ohmylms-content&tab=courses'
		);
	} );

	it( 'moves inside the tab with pushState and back with popstate', async () => {
		const user = userEvent.setup();
		setAddress( 'page=ohmylms-content&tab=courses' );
		const load = renderRouter( content );

		await user.click(
			screen.getByRole( 'link', { name: 'Edit course 12' } )
		);

		expect(
			screen.getByRole( 'heading', { name: 'Course 12 settings' } )
		).toBeInTheDocument();
		expect( window.location.search ).toBe(
			'?page=ohmylms-content&tab=courses&id=12&view=settings'
		);
		expect( load ).not.toHaveBeenCalled();

		act( () => {
			setAddress( 'page=ohmylms-content&tab=courses' );
			window.dispatchEvent( new PopStateEvent( 'popstate' ) );
		} );
		expect(
			screen.getByRole( 'heading', {
				name: 'Course list on ohmylms-content',
			} )
		).toBeInTheDocument();
	} );

	it( 'loads another tab or page as a document', async () => {
		const user = userEvent.setup();
		setAddress( 'page=ohmylms-content&tab=courses' );
		const load = renderRouter( content );

		await user.click( screen.getByRole( 'link', { name: 'Orders' } ) );

		expect( load ).toHaveBeenCalledWith(
			'admin.php?page=ohmylms-commerce&tab=orders',
			false
		);
	} );

	it( 'follows the server when it fell back to the default tab', () => {
		setAddress( 'page=ohmylms-content&tab=nope&id=4' );
		renderRouter( content );

		expect(
			screen.getByRole( 'heading', {
				name: 'Course list on ohmylms-content',
			} )
		).toBeInTheDocument();
	} );

	it( 'takes over section links printed by the server and marks the current one', async () => {
		const user = userEvent.setup();
		const sections = document.createElement( 'ul' );
		sections.id = SECTIONS_ID;
		sections.dataset.defaultSection = 'general';
		sections.innerHTML =
			'<li><a data-section="general" class="current" href="admin.php?page=ohmylms-settings&amp;tab=settings&amp;section=general">General</a></li>' +
			'<li><a data-section="tax" href="admin.php?page=ohmylms-settings&amp;tab=settings&amp;section=tax">Tax</a></li>';
		document.body.append( sections );
		setAddress( 'page=ohmylms-settings&tab=settings' );
		const load = renderRouter( {
			page: 'ohmylms-settings',
			tab: 'settings',
			section: 'general',
		} );
		expect(
			screen.getByRole( 'heading', { name: 'Settings general' } )
		).toBeInTheDocument();

		await user.click( screen.getByRole( 'link', { name: 'Tax' } ) );

		expect(
			screen.getByRole( 'heading', { name: 'Settings tax' } )
		).toBeInTheDocument();
		expect( window.location.search ).toBe(
			'?page=ohmylms-settings&tab=settings&section=tax'
		);
		expect( screen.getByRole( 'link', { name: 'Tax' } ) ).toHaveClass(
			'current'
		);
		expect(
			screen.getByRole( 'link', { name: 'General' } )
		).not.toHaveClass( 'current' );
		expect( load ).not.toHaveBeenCalled();
	} );

	it( 'opens an old #/route hash on the same tab and drops the hash', () => {
		setAddress( 'page=ohmylms-content&tab=courses', '#/courses/7' );
		renderRouter( content );

		expect(
			screen.getByRole( 'heading', { name: 'Course 7 details' } )
		).toBeInTheDocument();
		expect( window.location.search ).toBe(
			'?page=ohmylms-content&tab=courses&id=7'
		);
		expect( window.location.hash ).toBe( '' );
	} );

	it( 'resolves same-tab links for unsaved-changes guards while mounted', () => {
		setAddress( 'page=ohmylms-content&tab=courses' );
		renderRouter( content );

		expect(
			routeFromHref( 'admin.php?page=ohmylms-content&tab=courses&id=3' )
		).toBe( '/courses/3' );
		expect(
			routeFromHref( 'admin.php?page=ohmylms-commerce&tab=orders' )
		).toBeNull();
		expect( routeFromHref( '#/courses/4' ) ).toBe( '/courses/4' );
		expect(
			routeFromHref(
				'admin.php?page=ohmylms-content&tab=courses#/course-edit/4'
			)
		).toBeNull();
		expect( routeFromHref( 'https://example.test/' ) ).toBeNull();
	} );
} );

describe( 'hashTarget', () => {
	it( 'names the address of a hash route on another page', () => {
		setAddress( 'page=ohmylms', '#/orders' );
		expect(
			hashTarget( window, features, {
				page: 'ohmylms',
				tab: '',
				section: '',
			} )
		).toBe( 'admin.php?page=ohmylms-commerce&tab=orders' );
		setAddress( 'page=ohmylms-content&tab=courses', '#/courses/3' );
		expect( hashTarget( window, features, content ) ).toBeNull();
		setAddress( 'page=ohmylms-content&tab=courses' );
		expect( hashTarget( window, features, content ) ).toBeNull();
	} );
} );
