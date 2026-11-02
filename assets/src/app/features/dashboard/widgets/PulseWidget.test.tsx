import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { formatMoney } from '../model';
import * as api from '../shell/api';
import { allScope, context, envelope, pulse } from '../shell/fixtures';
import { parsePulse, sparklinePoints } from './pulse';
import PulseWidget from './PulseWidget';

vi.mock( '../shell/api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );

const getWidget = vi.mocked( api.getWidget );

function metric( label: string ) {
	return screen.getByText( label ).closest( 'li' ) as HTMLElement;
}

beforeEach( () => {
	vi.clearAllMocks();
	getWidget.mockImplementation( async ( widget, scope ) =>
		envelope( widget, scope, pulse, 7 )
	);
} );

describe( 'pulse data', () => {
	const raw = {
		dates: [ '2026-10-30', '2026-10-31' ],
		currency: null,
		metrics: [
			{
				id: 'completions',
				format: 'number',
				value: 2,
				previous: 1,
				series: [ 1, 1 ],
			},
			{
				id: 'mood',
				format: 'number',
				value: 1,
				previous: 1,
				series: [ 1 ],
			},
		],
	};

	it( 'validates metrics and skips unknown ones', () => {
		const data = parsePulse( raw );
		expect( data.metrics.map( ( item ) => item.id ) ).toEqual( [
			'completions',
		] );
		expect( data.currency ).toBeNull();
		expect(
			parsePulse( {
				...raw,
				metrics: [
					{
						id: 'renewals_at_risk',
						format: 'number',
						value: 0,
						previous: 0,
						series: [ 0, 0 ],
						on_hold: 2,
					},
				],
			} ).metrics[ 0 ]?.onHold
		).toBe( 2 );
	} );

	it( 'rejects a series that does not match the days', () => {
		expect( () =>
			parsePulse( {
				...raw,
				metrics: [ { ...raw.metrics[ 0 ], series: [ 1 ] } ],
			} )
		).toThrow();
		expect( () => parsePulse( { dates: [] } ) ).toThrow();
	} );

	it( 'draws sparklines inside the box, flat when nothing changes', () => {
		expect( sparklinePoints( [ 0, 10, 5 ], 120, 28 ) ).toBe(
			'0,25 60,3 120,14'
		);
		expect( sparklinePoints( [ 4, 4, 4 ], 120, 28 ) ).toBe(
			'0,14 60,14 120,14'
		);
		expect( sparklinePoints( [ 9 ], 120, 28 ) ).toBe( '0,14 120,14' );
		expect( sparklinePoints( [], 120, 28 ) ).toBe( '' );
	} );
} );

describe( 'pulse widget', () => {
	it( 'shows each metric with its change and a sparkline', async () => {
		render(
			<MemoryRouter>
				<PulseWidget context={ context( allScope, 7 ) } />
			</MemoryRouter>
		);
		await screen.findByRole( 'list' );
		expect( getWidget.mock.calls[ 0 ]?.slice( 0, 3 ) ).toEqual( [
			'pulse',
			'all',
			7,
		] );
		expect(
			screen.getByText( 'last 7 days vs the period before' )
		).toBeInTheDocument();

		const revenue = metric( 'Net revenue' );
		expect(
			within( revenue ).getByText(
				formatMoney( 4820, pulse.currency! ).replace( /\s/g, ' ' ),
				{ normalizer: ( value ) => value.replace( /\s/g, ' ' ) }
			)
		).toBeInTheDocument();
		expect( within( revenue ).getByText( '+8%' ) ).toBeInTheDocument();
		expect(
			within( revenue ).getByText( 'Up 8% from the previous period.' )
		).toHaveClass( 'screen-reader-text' );
		const line = revenue.querySelector( 'svg polyline' );
		expect( line?.getAttribute( 'points' )?.split( ' ' ) ).toHaveLength(
			7
		);
		expect( revenue.querySelector( 'svg' ) ).toHaveAttribute(
			'aria-hidden',
			'true'
		);
		expect( revenue.querySelector( 'svg' ) ).toHaveClass( 'is-up' );

		const active = metric( 'Weekly active learners' );
		expect( within( active ).getByText( '248' ) ).toBeInTheDocument();
		expect(
			within( active ).getByText(
				'Daily values over 7 days range from 22 to 45.'
			)
		).toBeInTheDocument();

		const completions = metric( 'Course completions' );
		expect( within( completions ).getByText( '0%' ) ).toBeInTheDocument();
		expect( completions.querySelector( 'svg' ) ).toHaveClass(
			'is-neutral'
		);
	} );

	it( 'treats a rise in renewals at risk as bad news and shows subscriptions on hold', async () => {
		render(
			<MemoryRouter>
				<PulseWidget context={ context( allScope, 7 ) } />
			</MemoryRouter>
		);
		await screen.findByRole( 'list' );
		const renewals = metric( 'Renewals at risk' );
		expect( within( renewals ).getByText( '+100%' ) ).toBeInTheDocument();
		expect( renewals.querySelector( 'svg' ) ).toHaveClass( 'is-down' );
		expect(
			within( renewals ).getByText(
				'Failed renewal payments · 3 subscriptions on hold'
			)
		).toBeInTheDocument();
	} );

	it( 'shows only the metrics the server sends for a school', async () => {
		getWidget.mockImplementation( async ( widget, scope ) =>
			envelope(
				widget,
				scope,
				{
					...pulse,
					currency: null,
					metrics: pulse.metrics.filter(
						( item ) =>
							'revenue' !== item.id &&
							'renewals_at_risk' !== item.id
					),
				},
				7
			)
		);
		render(
			<MemoryRouter>
				<PulseWidget context={ context() } />
			</MemoryRouter>
		);
		const list = await screen.findByRole( 'list' );
		expect( within( list ).getAllByRole( 'listitem' ) ).toHaveLength( 2 );
		expect( screen.queryByText( 'Net revenue' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Renewals at risk' )
		).not.toBeInTheDocument();
	} );
} );
