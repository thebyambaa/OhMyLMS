import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { ApiError } from '../../../lib/api';
import { DashboardShell } from '../DashboardPage';
import { createWidgetRegistry } from '../widgets/registry';
import type { DashboardWidgetProps } from '../widgets/registry';
import * as api from './api';
import { adminScopes, schoolAdminScopes, teacherScopes } from './fixtures';
import { chooseScope, groupScopes, parsePeriod, parseScopes } from './model';
import { WidgetCard } from './WidgetCard';

vi.mock( './api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );

const getScopes = vi.mocked( api.getScopes );
const seen: string[] = [];

function Probe( { context }: DashboardWidgetProps ) {
	seen.push( `${ context.scope.id }|${ context.period }` );
	return (
		<WidgetCard id="probe" title="Probe">
			<p>{ `Showing ${ context.scope.label } for ${ context.period }` }</p>
		</WidgetCard>
	);
}

function Broken(): never {
	throw new Error( 'boom' );
}

function Search() {
	return <output aria-label="search">{ useLocation().search }</output>;
}

const registry = () =>
	createWidgetRegistry( [
		{ id: 'probe', title: 'Probe', order: 20, component: Probe },
		{
			id: 'money',
			title: 'Money',
			order: 10,
			size: 'narrow',
			isAvailable: ( context ) => context.scope.commerce,
			component: () => <p>Money for admins</p>,
		},
		{ id: 'broken', title: 'Broken', order: 30, component: Broken },
	] );

function show( route = '/dashboard' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<DashboardShell registry={ registry() } />
			<Search />
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.clearAllMocks();
	seen.length = 0;
	vi.spyOn( console, 'error' ).mockImplementation( () => undefined );
	getScopes.mockResolvedValue( adminScopes );
} );

describe( 'scope model', () => {
	it( 'validates the scopes response', () => {
		const list = parseScopes( {
			scopes: [
				{
					id: 'school:3',
					type: 'school',
					label: 'North',
					school_id: 3,
					school_name: 'North',
					commerce: false,
				},
			],
			default: 'school:3',
			site_admin: false,
		} );
		expect( list.scopes[ 0 ] ).toMatchObject( {
			id: 'school:3',
			schoolId: 3,
		} );
		expect( () =>
			parseScopes( {
				scopes: [ { id: 'district:1', type: 'district' } ],
			} )
		).toThrow( 'invalid report data' );
		expect( () => parseScopes( { scopes: 'all' } ) ).toThrow();
	} );

	it( 'picks the requested scope only when the user has it', () => {
		expect( chooseScope( adminScopes, 'class:9' )?.id ).toBe( 'class:9' );
		expect( chooseScope( schoolAdminScopes, 'all' )?.id ).toBe(
			'school:3'
		);
		expect( chooseScope( schoolAdminScopes, null )?.id ).toBe( 'school:3' );
		expect(
			chooseScope(
				{ scopes: [], defaultScope: null, siteAdmin: false },
				null
			)
		).toBeNull();
	} );

	it( 'accepts only 7, 30 and 90 days', () => {
		expect( parsePeriod( '7' ) ).toBe( 7 );
		expect( parsePeriod( '90' ) ).toBe( 90 );
		expect( parsePeriod( '45' ) ).toBe( 30 );
		expect( parsePeriod( null ) ).toBe( 30 );
	} );

	it( 'groups classes under their school and independent classes last', () => {
		const { top, groups } = groupScopes( adminScopes.scopes );
		expect( top ).toEqual( [ { value: 'all', label: 'All schools' } ] );
		expect( groups.map( ( group ) => group.label ) ).toEqual( [
			'North Academy',
			'Classes without a school',
		] );
		expect( groups[ 0 ]?.options ).toEqual( [
			{ value: 'school:3', label: 'North Academy (whole school)' },
			{ value: 'class:9', label: 'Algebra 8A' },
		] );
		expect( groupScopes( teacherScopes.scopes ).groups ).toEqual( [
			{
				label: 'North Academy',
				options: [ { value: 'class:9', label: 'Algebra 8A' } ],
			},
		] );
	} );
} );

describe( 'dashboard shell', () => {
	it( 'loads the scopes, then renders the widgets of the scope in order', async () => {
		show();
		expect( screen.getByText( 'Loading dashboard…' ) ).toBeInTheDocument();
		expect(
			await screen.findByText( 'Showing All schools for 30' )
		).toBeInTheDocument();
		const regions = screen
			.getAllByRole( 'region' )
			.map( ( region ) => region.getAttribute( 'aria-labelledby' ) );
		expect( regions ).toEqual( [ 'ohmylms-widget-probe-title' ] );
		expect( screen.getByText( 'Money for admins' ) ).toBeInTheDocument();
		const cells = document.querySelectorAll(
			'.ohmylms-dashboard-grid__cell'
		);
		expect( cells[ 0 ] ).toHaveClass( 'is-narrow' );
		expect( cells[ 1 ] ).toHaveClass( 'is-wide' );
		expect(
			screen.getByText(
				'All schools · last 30 days, compared with the 30 days before.'
			)
		).toBeInTheDocument();
	} );

	it( 'isolates a widget that fails to render', async () => {
		show();
		await screen.findByText( 'Showing All schools for 30' );
		expect(
			screen.getByRole( 'button', { name: /try again/i } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Money for admins' ) ).toBeInTheDocument();
	} );

	it( 'switches scope with the grouped picker and keeps it in the URL', async () => {
		const user = userEvent.setup();
		show();
		const picker = await screen.findByRole( 'combobox', { name: 'Show' } );
		expect(
			within( picker ).getByRole( 'group', { name: 'North Academy' } )
		).toBeInTheDocument();
		await user.selectOptions( picker, 'class:9' );
		expect(
			await screen.findByText( 'Showing Algebra 8A for 30' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Money for admins' )
		).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'search' ) ).toHaveTextContent(
			'scope=class%3A9'
		);
	} );

	it( 'switches the period with pressed buttons', async () => {
		const user = userEvent.setup();
		show();
		await screen.findByText( 'Showing All schools for 30' );
		const thirty = screen.getByRole( 'button', { name: '30 days' } );
		expect( thirty ).toHaveAttribute( 'aria-pressed', 'true' );
		await user.click( screen.getByRole( 'button', { name: '7 days' } ) );
		expect(
			await screen.findByText( 'Showing All schools for 7' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: '7 days' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
		expect( screen.getByLabelText( 'search' ) ).toHaveTextContent(
			'period=7'
		);
		expect( seen ).toContain( 'all|7' );
	} );

	it( 'reads scope and period from the URL and ignores a scope the user does not have', async () => {
		getScopes.mockResolvedValue( schoolAdminScopes );
		show( '/dashboard?scope=all&period=90' );
		expect(
			await screen.findByText( 'Showing North Academy for 90' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Money for admins' )
		).not.toBeInTheDocument();
	} );

	it( 'hides the picker for a single scope', async () => {
		getScopes.mockResolvedValue( teacherScopes );
		show();
		await screen.findByText( 'Showing Algebra 8A for 30' );
		expect(
			screen.queryByRole( 'combobox', { name: 'Show' } )
		).not.toBeInTheDocument();
	} );

	it( 'explains a failed scope request and a user without scopes', async () => {
		getScopes.mockRejectedValueOnce(
			new ApiError( {
				status: 403,
				code: 'ohmylms_dashboard_cannot_view',
				message: 'Sorry.',
			} )
		);
		const { unmount } = show();
		expect(
			await screen.findByText(
				'You do not have permission to view this.',
				{
					selector: '.components-notice__content',
				}
			)
		).toBeInTheDocument();
		unmount();
		getScopes.mockResolvedValueOnce( {
			scopes: [],
			defaultScope: null,
			siteAdmin: false,
		} );
		show();
		expect(
			await screen.findByText(
				'There is no school or class for you to follow yet.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
		await waitFor( () => expect( seen ).toHaveLength( 0 ) );
	} );
} );
