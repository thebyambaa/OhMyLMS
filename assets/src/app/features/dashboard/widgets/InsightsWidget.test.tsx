import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import * as api from '../shell/api';
import { allScope, context, envelope } from '../shell/fixtures';
import { parseInsights } from './InsightsModel';
import InsightsWidget from './InsightsWidget';

vi.mock( '../shell/api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );

const getWidget = vi.mocked( api.getWidget );

const raw = {
	insights: [
		{
			key: 'drop_off:41',
			rule: 'drop_off',
			severity: 'error',
			text: '“Lesson 6” (item 6 of Digital SAT Math Prep) loses 58% of learners: 70 stop there.',
			action: { label: 'Open report', route: '/reports/courses/41' },
		},
		{
			key: 'class_gap:9',
			rule: 'class_gap',
			severity: 'warning',
			text: 'Grade 8B is 18 pts behind other classes on Linear equations.',
			action: null,
		},
		{
			key: 'revenue',
			rule: 'revenue',
			severity: 'success',
			text: 'Revenue is up 8% vs the previous 30 days ($4,820.00).',
			action: { label: 'See earnings', route: '/earnings' },
		},
	],
};

function show() {
	return render(
		<MemoryRouter>
			<InsightsWidget context={ context( allScope ) } />
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.clearAllMocks();
	getWidget.mockImplementation( async ( widget, scope, period, parse ) =>
		envelope( widget, scope, parse( raw ), period )
	);
} );

describe( 'insights data', () => {
	it( 'validates insights and rejects malformed ones', () => {
		expect( parseInsights( raw ).insights[ 2 ]?.action?.route ).toBe(
			'/earnings'
		);
		const bad = ( change: Record< string, unknown > ) => () =>
			parseInsights( {
				insights: [ { ...raw.insights[ 0 ], ...change } ],
			} );
		expect( bad( { severity: 'red' } ) ).toThrow();
		expect( bad( { key: 'Bad key' } ) ).toThrow();
		expect( bad( { text: 5 } ) ).toThrow();
		expect(
			bad( { action: { label: 'x', route: '//evil.test' } } )
		).toThrow();
		expect( () => parseInsights( {} ) ).toThrow();
	} );
} );

describe( 'insights widget', () => {
	it( 'lists the sentences in server order with their severity', async () => {
		show();
		const list = await screen.findByRole( 'list' );
		expect( getWidget ).toHaveBeenCalledWith(
			'insights',
			'all',
			30,
			parseInsights,
			expect.any( AbortSignal )
		);
		const items = within( list ).getAllByRole( 'listitem' );
		expect( items ).toHaveLength( 3 );
		expect( items[ 0 ] ).toHaveClass( 'is-error' );
		expect( items[ 0 ] ).toHaveTextContent(
			'Needs action: “Lesson 6” (item 6 of Digital SAT Math Prep) loses 58% of learners'
		);
		expect( items[ 1 ] ).toHaveTextContent( 'Worth a look:' );
		expect( items[ 2 ] ).toHaveTextContent( 'Good news: Revenue is up 8%' );
		expect(
			screen.getByText( 'written from your data by fixed rules' )
		).toBeInTheDocument();
	} );

	it( 'links an insight only when it carries an action', async () => {
		show();
		const report = await screen.findByRole( 'link', {
			name: /Open report/,
		} );
		expect( report ).toHaveAttribute( 'href', '/reports/courses/41' );
		expect( screen.getAllByRole( 'link' ) ).toHaveLength( 2 );
		const gap = screen
			.getByText( /Grade 8B is 18 pts behind/ )
			.closest( 'li' ) as HTMLElement;
		expect( within( gap ).queryByRole( 'link' ) ).toBeNull();
	} );

	it( 'explains an empty list', async () => {
		getWidget.mockImplementation( async ( widget, scope, period, parse ) =>
			envelope( widget, scope, parse( { insights: [] } ), period )
		);
		show();
		expect(
			await screen.findByText( /Nothing stands out in this period/ )
		).toBeInTheDocument();
	} );
} );
