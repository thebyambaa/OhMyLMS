import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import * as api from '../shell/api';
import { allScope, context, envelope, northScope } from '../shell/fixtures';
import { builtInWidgets } from '.';
import { parseSiteHealth } from './SiteHealthModel';
import SiteHealthWidget from './SiteHealthWidget';

vi.mock( '../shell/api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );

const getWidget = vi.mocked( api.getWidget );

const raw = {
	checks: [
		{
			id: 'payment_gateway',
			status: 'ok',
			label: 'Payment gateway connected',
			detail: 'Connected: PayPal (live).',
			action: null,
		},
		{
			id: 'email_sender',
			status: 'warning',
			label: 'Email sender configured',
			detail: 'No sender name and address are set.',
			action: { label: 'Set the sender', route: '/emails', href: null },
		},
		{
			id: 'cron',
			status: 'error',
			label: 'Cron running',
			detail: 'The oldest scheduled task is 42 minutes overdue.',
			action: {
				label: 'Check site health',
				route: null,
				href: 'http://800.local/wp-admin/site-health.php',
			},
		},
	],
	passed: 1,
	total: 3,
};

beforeEach( () => {
	vi.clearAllMocks();
	getWidget.mockImplementation( async ( widget, scope, period, parse ) =>
		envelope( widget, scope, parse( raw ), period )
	);
} );

describe( 'site health data', () => {
	it( 'validates checks and their fix links', () => {
		expect( parseSiteHealth( raw ).checks[ 2 ]?.action?.href ).toBe(
			'http://800.local/wp-admin/site-health.php'
		);
		const bad = ( action: unknown ) => () =>
			parseSiteHealth( {
				...raw,
				checks: [ { ...raw.checks[ 0 ], action } ],
			} );
		expect(
			bad( { label: 'x', route: null, href: 'javascript:alert(1)' } )
		).toThrow();
		expect( bad( { label: 'x', route: 'emails', href: null } ) ).toThrow();
		expect( bad( { label: 'x', route: null, href: null } ) ).toThrow();
		expect( () =>
			parseSiteHealth( {
				...raw,
				checks: [ { ...raw.checks[ 0 ], status: 'bad' } ],
			} )
		).toThrow();
	} );

	it( 'is registered for site administrators in all schools only', () => {
		const widget = builtInWidgets.find(
			( item ) => 'site-health' === item.id
		);
		expect( widget?.isAvailable?.( context( allScope ) ) ).toBe( true );
		expect( widget?.isAvailable?.( context( northScope ) ) ).toBe( false );
		expect(
			[ 'course-health', 'insights', 'activity' ].every( ( id ) =>
				builtInWidgets.some(
					( item ) => id === item.id && ! item.isAvailable
				)
			)
		).toBe( true );
	} );
} );

describe( 'site health widget', () => {
	it( 'lists every check with its status and a link to fix the failing ones', async () => {
		render(
			<MemoryRouter>
				<SiteHealthWidget context={ context( allScope ) } />
			</MemoryRouter>
		);
		const list = await screen.findByRole( 'list' );
		const items = within( list ).getAllByRole( 'listitem' );
		expect( items ).toHaveLength( 3 );
		expect( items[ 0 ] ).toHaveTextContent(
			'Payment gateway connected (Done)'
		);
		expect(
			within( items[ 0 ] as HTMLElement ).queryByRole( 'link' )
		).toBeNull();
		expect(
			within( items[ 1 ] as HTMLElement ).getByRole( 'link', {
				name: 'Set the sender',
			} )
		).toHaveAttribute( 'href', '/emails' );
		expect( items[ 2 ] ).toHaveTextContent( 'Cron running (Problem)' );
		expect(
			within( items[ 2 ] as HTMLElement ).getByRole( 'link', {
				name: 'Check site health',
			} )
		).toHaveAttribute(
			'href',
			'http://800.local/wp-admin/site-health.php'
		);
		expect(
			screen.getByRole( 'progressbar', { name: '1 of 3 done' } )
		).toHaveAttribute( 'value', '1' );
	} );
} );
