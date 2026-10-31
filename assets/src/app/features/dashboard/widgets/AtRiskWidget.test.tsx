import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../../lib/api';
import * as shellApi from '../shell/api';
import { algebraClass, context, envelope } from '../shell/fixtures';
import type { WidgetContext } from '../shell/model';
import * as atRiskApi from './atRisk';
import { parseAtRisk } from './atRisk';
import AtRiskWidget from './AtRiskWidget';

vi.mock( '../shell/api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );

vi.mock( './atRisk', async ( importOriginal ) => ( {
	...( await importOriginal< typeof import( './atRisk' ) >() ),
	nudgeLearner: vi.fn(),
} ) );

const getWidget = vi.mocked( shellApi.getWidget );
const nudgeLearner = vi.mocked( atRiskApi.nudgeLearner );
const notice = '.components-notice__content';

function learner( id: number, overrides: Record< string, unknown > = {} ) {
	return {
		id,
		name: `Learner ${ id }`,
		initials: `L${ id }`,
		level: 'watch',
		reasons: [
			{
				rule: 'inactive',
				value: 9,
				severe: false,
				text: 'Inactive 9 days',
			},
		],
		summary: 'Inactive 9 days',
		report: null,
		nudged_at: null,
		nudged_label: '',
		can_nudge: true,
		...overrides,
	};
}

const raw = {
	learners: [
		learner( 11, {
			name: 'Ana Example',
			initials: 'AE',
			level: 'high',
			reasons: [
				{
					rule: 'inactive',
					value: 7,
					severe: false,
					text: 'Inactive 7 days',
				},
				{
					rule: 'mastery',
					value: 15,
					severe: false,
					text: 'Mastery down 15 pts in 2 wks',
				},
			],
			summary: 'Inactive 7 days · Mastery down 15 pts in 2 wks',
			report: '/students/11/report',
		} ),
		learner( 12, {
			name: 'Bat Demo',
			initials: 'BD',
			nudged_at: '2026-10-30T08:00:00Z',
			nudged_label: 'October 30, 2026',
			can_nudge: false,
		} ),
		learner( 13, { name: 'Chen Sample', initials: 'CS' } ),
	],
	counts: { high: 1, watch: 2 },
	total: 3,
	rules: { inactive: true, mastery: true, overdue: true },
	nudge: { cap: 20, remaining: 5, interval_hours: 72 },
};

function show( current: WidgetContext = context( algebraClass ) ) {
	return render(
		<MemoryRouter>
			<AtRiskWidget context={ current } />
		</MemoryRouter>
	);
}

function serve( data: unknown ) {
	getWidget.mockImplementation( async ( widget, scope, period, parse ) =>
		envelope( widget, scope, parse( data ), period )
	);
}

beforeEach( () => {
	vi.clearAllMocks();
	serve( raw );
	nudgeLearner.mockResolvedValue( {
		learner: 13,
		nudgedAt: '2026-10-31T09:00:00Z',
		nudgedLabel: 'October 31, 2026',
		remaining: 4,
		guardians: 0,
	} );
} );

describe( 'at-risk data', () => {
	it( 'validates learners and rejects malformed ones', () => {
		const parsed = parseAtRisk( raw );
		expect( parsed.learners[ 0 ]?.report ).toBe( '/students/11/report' );
		expect( parsed.learners[ 1 ]?.canNudge ).toBe( false );
		expect( parsed.nudge.intervalHours ).toBe( 72 );
		const bad = ( change: Record< string, unknown > ) => () =>
			parseAtRisk( {
				...raw,
				learners: [ { ...raw.learners[ 0 ], ...change } ],
			} );
		expect( bad( { level: 'red' } ) ).toThrow();
		expect( bad( { report: 'https://evil.test' } ) ).toThrow();
		expect(
			bad( { reasons: [ { rule: 'gpa', value: 1, severe: false } ] } )
		).toThrow();
		expect( bad( { can_nudge: 'yes' } ) ).toThrow();
		expect( () => parseAtRisk( { ...raw, counts: null } ) ).toThrow();
	} );
} );

describe( 'at-risk widget', () => {
	it( 'asks for the scope and period and lists learners with level and reasons', async () => {
		show( context( algebraClass, 7 ) );
		const list = await screen.findByRole( 'list' );
		expect( getWidget ).toHaveBeenCalledWith(
			'at-risk',
			'class:9',
			7,
			parseAtRisk,
			expect.any( AbortSignal )
		);
		const rows = within( list ).getAllByRole( 'listitem' );
		expect( rows ).toHaveLength( 3 );
		const first = rows[ 0 ] as HTMLElement;
		expect( first ).toHaveClass( 'is-high' );
		expect( first ).toHaveTextContent( 'AE' );
		expect( within( first ).getByText( 'High risk' ) ).toBeInTheDocument();
		expect(
			within( first ).getByText(
				'Inactive 7 days · Mastery down 15 pts in 2 wks'
			)
		).toBeInTheDocument();
		expect(
			within( rows[ 2 ] as HTMLElement ).getByText( 'Watch' )
		).toBeInTheDocument();
		expect(
			screen.getByText( '1 high · 2 watch · why they’re flagged' )
		).toBeInTheDocument();
	} );

	it( 'links to the student report only when the server offers it', async () => {
		show();
		const link = await screen.findByRole( 'link', { name: 'Ana Example' } );
		expect( link ).toHaveAttribute( 'href', '/students/11/report' );
		expect(
			screen.queryByRole( 'link', { name: 'Chen Sample' } )
		).not.toBeInTheDocument();
		expect( screen.getByText( 'Chen Sample' ) ).toBeInTheDocument();
	} );

	it( 'shows "Nudged <date>" for a learner nudged in the last 72 hours', async () => {
		show();
		const row = ( await screen.findByText( 'Bat Demo' ) ).closest(
			'li'
		) as HTMLElement;
		expect(
			within( row ).getByText( 'Nudged October 30, 2026' )
		).toBeInTheDocument();
		expect( within( row ).queryByRole( 'button' ) ).toBeNull();
	} );

	it( 'nudges a learner, then shows the date instead of the button', async () => {
		const user = userEvent.setup();
		show();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Send a nudge email to Chen Sample',
			} )
		);
		expect( nudgeLearner ).toHaveBeenCalledWith( 'class:9', 30, 13 );
		expect( await screen.findByRole( 'status' ) ).toHaveTextContent(
			'Nudge sent to Chen Sample.'
		);
		const row = screen
			.getByText( 'Chen Sample' )
			.closest( 'li' ) as HTMLElement;
		expect(
			within( row ).getByText( 'Nudged October 31, 2026' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', {
				name: 'Send a nudge email to Ana Example',
			} )
		).toBeEnabled();
	} );

	it( 'explains a rate limit and marks the learner as nudged', async () => {
		const user = userEvent.setup();
		nudgeLearner.mockRejectedValueOnce(
			new ApiError( {
				status: 429,
				code: 'ohmylms_at_risk_nudge_too_soon',
				message:
					'You already nudged this learner on October 29, 2026. You can nudge them again after 72 hours.',
				details: { status: 429, nudged_label: 'October 29, 2026' },
			} )
		);
		show();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Send a nudge email to Chen Sample',
			} )
		);
		expect(
			await screen.findByText( /You already nudged this learner/, {
				selector: notice,
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Nudged October 29, 2026' )
		).toBeInTheDocument();
	} );

	it( 'disables nudges once the daily cap is reached', async () => {
		const user = userEvent.setup();
		nudgeLearner.mockRejectedValueOnce(
			new ApiError( {
				status: 429,
				code: 'ohmylms_at_risk_nudge_daily_cap',
				message: 'You have sent 20 nudges in the last 24 hours.',
			} )
		);
		show();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Send a nudge email to Chen Sample',
			} )
		);
		expect(
			await screen.findByText(
				'You reached the daily nudge limit. Try again tomorrow.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', {
				name: 'Send a nudge email to Ana Example',
			} )
		).toHaveAttribute( 'aria-disabled', 'true' );
	} );

	it( 'shows a permission error from a nudge without crashing', async () => {
		const user = userEvent.setup();
		nudgeLearner.mockRejectedValueOnce(
			new ApiError( {
				status: 403,
				code: 'ohmylms_dashboard_forbidden_scope',
				message: 'No.',
			} )
		);
		show();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Send a nudge email to Ana Example',
			} )
		);
		expect(
			await screen.findByText( 'You cannot view this school or class.', {
				selector: notice,
			} )
		).toBeInTheDocument();
	} );

	it( 'shows the first six and expands to all', async () => {
		const user = userEvent.setup();
		serve( {
			...raw,
			learners: Array.from( { length: 8 }, ( _, i ) =>
				learner( 100 + i )
			),
			counts: { high: 0, watch: 8 },
			total: 8,
		} );
		show();
		const list = await screen.findByRole( 'list' );
		expect( within( list ).getAllByRole( 'listitem' ) ).toHaveLength( 6 );
		await user.click(
			screen.getByRole( 'button', { name: 'Show all 8 flagged' } )
		);
		expect( within( list ).getAllByRole( 'listitem' ) ).toHaveLength( 8 );
	} );

	it( 'says when nobody is at risk, and when every rule is off', async () => {
		serve( {
			...raw,
			learners: [],
			counts: { high: 0, watch: 0 },
			total: 0,
		} );
		const { unmount } = show();
		expect(
			await screen.findByText( 'No learners are at risk here right now.' )
		).toBeInTheDocument();
		unmount();
		serve( {
			...raw,
			learners: [],
			counts: { high: 0, watch: 0 },
			total: 0,
			rules: { inactive: false, mastery: false, overdue: false },
		} );
		show();
		expect(
			await screen.findByText(
				'Every at-risk rule is switched off in Settings.'
			)
		).toBeInTheDocument();
	} );

	it( 'shows a failed load with a retry', async () => {
		const user = userEvent.setup();
		getWidget.mockRejectedValueOnce(
			new ApiError( {
				status: 500,
				code: 'ohmylms_dashboard_query_failed',
				message: 'The dashboard could not be loaded.',
			} )
		);
		show();
		expect(
			await screen.findByText( /The dashboard could not be loaded\./, {
				selector: notice,
			} )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect( await screen.findByRole( 'list' ) ).toBeInTheDocument();
	} );
} );
