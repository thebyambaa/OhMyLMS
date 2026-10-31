import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import {
	completedStudent,
	courseReport,
	currency,
	dashboard,
	earnings,
	emptyDashboard,
	refundedTransaction,
	student,
	transaction,
	algebra,
	geometry,
} from './fixtures';
import {
	courseReport as courseReportFeature,
	dashboard as dashboardFeature,
	earnings as earningsFeature,
} from './index';
import { formatMoney } from './model';
import * as shellApi from './shell/api';
import { adminScopes, envelope } from './shell/fixtures';

vi.mock( './shell/api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );

vi.mock( './api', () => ( {
	getDashboard: vi.fn(),
	getEarnings: vi.fn(),
	listTransactions: vi.fn(),
	listCourseReport: vi.fn(),
	getCourseReport: vi.fn(),
	listCourseStudents: vi.fn(),
} ) );

const mocks = {
	getDashboard: vi.mocked( api.getDashboard ),
	getEarnings: vi.mocked( api.getEarnings ),
	listTransactions: vi.mocked( api.listTransactions ),
	listCourseReport: vi.mocked( api.listCourseReport ),
	getCourseReport: vi.mocked( api.getCourseReport ),
	listCourseStudents: vi.mocked( api.listCourseStudents ),
};

const notice = { selector: '.components-notice__content' };
const features = [ dashboardFeature, earningsFeature, courseReportFeature ];
function show( route: string ) {
	return renderShell( features, {
		route,
		capabilities: { manage_options: true, ohmylms_view_dashboard: true },
	} );
}
/**
 * The overview widget's card.
 */
async function overview() {
	return ( await screen.findByRole( 'region', {
		name: 'Earnings and courses',
	} ) ) as HTMLElement;
}
/**
 * Text Testing Library sees: Intl uses non-breaking spaces, which it normalises.
 *
 * @param value Amount.
 */
const money = ( value: number ) =>
	formatMoney( value, currency ).replace( /\s/g, ' ' );
function failure( status: number, code: string, message: string ) {
	return new ApiError( { status, code, message } );
}
const collection = < T, >( items: T[], totalPages = 1 ) => ( {
	items,
	total: items.length,
	totalPages,
} );

beforeEach( () => {
	vi.clearAllMocks();
	mocks.getDashboard.mockResolvedValue( dashboard );
	mocks.getEarnings.mockResolvedValue( earnings );
	mocks.listTransactions.mockResolvedValue(
		collection( [ transaction, refundedTransaction ], 3 )
	);
	mocks.listCourseReport.mockResolvedValue(
		collection( [ algebra, geometry ] )
	);
	mocks.getCourseReport.mockResolvedValue( courseReport );
	mocks.listCourseStudents.mockResolvedValue(
		collection( [ student, completedStudent ] )
	);
	vi.mocked( shellApi.getScopes ).mockResolvedValue( adminScopes );
	vi.mocked( shellApi.getWidget ).mockImplementation(
		async ( widget, scope, period, parse ) =>
			envelope(
				widget,
				scope,
				parse(
					'pulse' === widget
						? { dates: [], currency: null, metrics: [] }
						: { items: [], snoozed: 0, grading_days: 3 }
				),
				period
			)
	);
} );

describe( 'routes', () => {
	it( 'are hidden and make no request without the dashboard capability', () => {
		renderShell( features, {
			route: '/dashboard',
			capabilities: { manage_options: true },
		} );
		expect( shellApi.getScopes ).not.toHaveBeenCalled();
		expect( mocks.getDashboard ).not.toHaveBeenCalled();
	} );

	it( 'place the dashboard, earnings and the course report on their admin pages', async () => {
		show( '/dashboard' );
		await overview();
		expect( dashboardFeature ).toMatchObject( {
			capability: 'ohmylms_view_dashboard',
		} );
		expect( dashboardFeature ).toMatchObject( { group: 'dashboard' } );
		expect( dashboardFeature.tab ).toBeUndefined();
		expect( earningsFeature ).toMatchObject( {
			group: 'commerce',
			tab: 'earnings',
		} );
		expect( courseReportFeature ).toMatchObject( {
			group: 'content',
			tab: 'courses',
			view: 'report',
			hidden: true,
		} );
	} );
} );

describe( 'overview widget', () => {
	it( 'shows loading, then cards with changes, a described chart and course lists', async () => {
		let release!: () => void;
		mocks.getDashboard.mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					release = () => resolve( dashboard );
				} )
		);
		show( '/dashboard' );
		expect(
			await within( await overview() ).findByText( 'Loading…' )
		).toBeInTheDocument();
		await waitFor( () => expect( release ).toBeDefined() );
		release();

		const summary = await screen.findByRole( 'list', { name: 'Summary' } );
		const cards = within( summary ).getAllByRole( 'listitem' );
		expect( cards ).toHaveLength( 5 );
		expect(
			within( cards[ 0 ] as HTMLElement ).getByText( 'Earnings' )
		).toBeInTheDocument();
		expect(
			within( cards[ 0 ] as HTMLElement ).getByText( money( 330 ) )
		).toBeInTheDocument();
		expect(
			within( cards[ 1 ] as HTMLElement ).getByText(
				'New, nothing in the previous period.'
			)
		).toBeInTheDocument();
		expect(
			within( cards[ 2 ] as HTMLElement ).getByText(
				'Down 26.7% from the previous period.'
			)
		).toBeInTheDocument();

		const chart = screen.getByRole( 'img', { name: /Earnings/ } );
		expect(
			( chart.querySelector( 'desc' )?.textContent ?? '' ).replace(
				/\s/g,
				' '
			)
		).toContain( money( 330 ) );

		const recent = screen.getByRole( 'table', { name: 'Recent courses' } );
		expect(
			within( recent ).getByRole( 'link', { name: 'Intro to Algebra' } )
		).toHaveAttribute(
			'href',
			expect.stringContaining( '/reports/courses/41' )
		);
		expect( within( recent ).getByText( 'draft' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'table', { name: 'Popular courses' } )
		).toBeInTheDocument();
	} );

	it( 'offers the chart data as a table that opens from the keyboard', async () => {
		const user = userEvent.setup();
		show( '/dashboard' );
		await screen.findByRole( 'img', { name: /Earnings/ } );
		const summary = screen.getByText( 'Show data table' );
		summary.focus();
		await user.keyboard( '{Enter}' );
		const table = await screen.findByRole( 'table', { name: 'Earnings' } );
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 4 );
		expect(
			within( rows[ 1 ] as HTMLElement ).getByText( money( 250 ) )
		).toBeInTheDocument();
		expect(
			within( rows[ 1 ] as HTMLElement ).getByText( money( 30 ) )
		).toBeInTheDocument();
	} );

	it( 'shows empty states instead of a chart and lists', async () => {
		mocks.getDashboard.mockResolvedValue( emptyDashboard );
		show( '/dashboard' );
		expect(
			await screen.findByText( 'No earnings in this period.' )
		).toBeInTheDocument();
		expect( screen.getByText( 'No courses yet.' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'No students enrolled in this period.' )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'img' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'table', { name: 'Recent courses' } )
		).not.toBeInTheDocument();
		const cards = within(
			screen.getByRole( 'list', { name: 'Summary' } )
		).getAllByRole( 'listitem' );
		expect(
			within( cards[ 0 ] as HTMLElement ).getByText( '0%' )
		).toBeInTheDocument();
	} );

	it( 'shows a server error and retries', async () => {
		const user = userEvent.setup();
		mocks.getDashboard.mockRejectedValueOnce(
			failure(
				500,
				'ohmylms_analytics_query_failed',
				'The report could not be loaded.'
			)
		);
		show( '/dashboard' );
		expect(
			await screen.findByText(
				/The report could not be loaded\./,
				notice
			)
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'list', { name: 'Summary' } )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /could not be loaded/, notice )
		).not.toBeInTheDocument();
	} );

	it( 'explains a permission error without offering a retry', async () => {
		mocks.getDashboard.mockRejectedValue(
			failure(
				403,
				'ohmylms_analytics_cannot_view',
				'Sorry, you are not allowed to view analytics.'
			)
		);
		show( '/dashboard' );
		expect(
			await screen.findByText(
				'You do not have permission to view this.',
				notice
			)
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'list', { name: 'Summary' } )
		).not.toBeInTheDocument();
	} );

	it( 'follows the dashboard period', async () => {
		const user = userEvent.setup();
		show( '/dashboard?period=7' );
		await screen.findByRole( 'list', { name: 'Summary' } );
		expect( mocks.getDashboard.mock.calls[ 0 ]?.[ 0 ] ).toEqual( {
			preset: 'last_7_days',
			start: '',
			end: '',
		} );
		expect(
			screen.getByRole( 'button', { name: '7 days' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
		await user.click( screen.getByRole( 'button', { name: '90 days' } ) );
		await waitFor( () =>
			expect( mocks.getDashboard.mock.calls.at( -1 )?.[ 0 ] ).toEqual( {
				preset: 'last_90_days',
				start: '',
				end: '',
			} )
		);
		expect(
			screen.getByRole( 'link', { name: 'View the earnings report' } )
		).toHaveAttribute(
			'href',
			expect.stringContaining( 'range=last_90_days' )
		);
	} );
} );

describe( 'date range filter', () => {
	it( 'applies a preset chosen with the select at once', async () => {
		const user = userEvent.setup();
		show( '/earnings' );
		await screen.findByRole( 'list', { name: 'Summary' } );
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Date range' } ),
			'last_90_days'
		);
		await waitFor( () =>
			expect( mocks.getEarnings.mock.calls.at( -1 )?.[ 0 ] ).toEqual( {
				preset: 'last_90_days',
				start: '',
				end: '',
			} )
		);
	} );

	it( 'reads the range from the URL', async () => {
		show( '/earnings?range=last_7_days' );
		await screen.findByRole( 'list', { name: 'Summary' } );
		expect( mocks.getEarnings.mock.calls[ 0 ]?.[ 0 ] ).toEqual( {
			preset: 'last_7_days',
			start: '',
			end: '',
		} );
		expect(
			screen.getByRole( 'combobox', { name: 'Date range' } )
		).toHaveValue( 'last_7_days' );
	} );

	it( 'applies a custom range with the keyboard and rejects an invalid one', async () => {
		const user = userEvent.setup();
		show( '/earnings' );
		await screen.findByRole( 'list', { name: 'Summary' } );
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Date range' } ),
			'custom'
		);
		expect( mocks.getEarnings ).toHaveBeenCalledTimes( 1 );

		const start = screen.getByLabelText( 'Start date' );
		const end = screen.getByLabelText( 'End date' );
		await user.click( start );
		await user.keyboard( '2001-03-31' );
		await user.tab();
		expect( end ).toHaveFocus();
		await user.keyboard( '2001-03-01{Enter}' );
		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
			'The start date must not be after the end date.'
		);
		expect( mocks.getEarnings ).toHaveBeenCalledTimes( 1 );

		await user.clear( start );
		await user.keyboard( '2001-03-01' );
		await user.clear( end );
		await user.keyboard( '2001-03-31' );
		await user.tab();
		expect(
			screen.getByRole( 'button', { name: 'Apply range' } )
		).toHaveFocus();
		await user.keyboard( '{Enter}' );
		await waitFor( () =>
			expect( mocks.getEarnings.mock.calls.at( -1 )?.[ 0 ] ).toEqual( {
				preset: 'custom',
				start: '2001-03-01',
				end: '2001-03-31',
			} )
		);
		expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
	} );
} );

describe( 'earnings report', () => {
	it( 'shows totals, breakdowns, orders and courses', async () => {
		show( '/earnings' );
		await screen.findByRole( 'list', { name: 'Summary' } );
		const countries = screen.getByRole( 'table', {
			name: 'Orders by country',
		} );
		expect( within( countries ).getByText( 'US' ) ).toBeInTheDocument();

		const orders = await screen.findByRole( 'table', {
			name: 'Transactions',
		} );
		expect( within( orders ).getByText( '#77' ) ).toBeInTheDocument();
		expect(
			within( orders ).getByText( 'Intro to Algebra, Geometry' )
		).toBeInTheDocument();
		expect(
			within( orders ).getByText( /\(refunded\)/ )
		).toBeInTheDocument();
		expect(
			screen.getAllByText( '2 orders found.' )[ 0 ]
		).toBeInTheDocument();

		const courses = await screen.findByRole( 'table', {
			name: 'Course report',
		} );
		expect(
			within( courses ).getByRole( 'link', { name: 'Geometry' } )
		).toHaveAttribute(
			'href',
			expect.stringContaining( '/reports/courses/42' )
		);
	} );

	it( 'sorts with accessible column buttons and pages the orders', async () => {
		const user = userEvent.setup();
		show( '/earnings' );
		const orders = await screen.findByRole( 'table', {
			name: 'Transactions',
		} );
		const dateHeader = within( orders )
			.getAllByRole( 'link', { name: /^Date Sort/ } )[ 0 ]!
			.closest( 'th' );
		expect( dateHeader ).toHaveAttribute( 'aria-sort', 'descending' );

		await user.click(
			within( orders ).getAllByRole( 'link', {
				name: /^Total Sort/,
			} )[ 0 ]!
		);
		await waitFor( () =>
			expect(
				mocks.listTransactions.mock.calls.at( -1 )?.[ 1 ]
			).toMatchObject( {
				orderby: 'total',
				order: 'desc',
			} )
		);
		await user.click(
			(
				await screen.findAllByRole( 'link', { name: /^Total Sort/ } )
			)[ 0 ]!
		);
		await waitFor( () =>
			expect(
				mocks.listTransactions.mock.calls.at( -1 )?.[ 1 ]
			).toMatchObject( {
				orderby: 'total',
				order: 'asc',
			} )
		);
		expect(
			(
				await screen.findAllByRole( 'link', { name: /^Total Sort/ } )
			)[ 0 ]!.closest( 'th' )
		).toHaveAttribute( 'aria-sort', 'ascending' );

		const next = await screen.findAllByRole( 'button', {
			name: 'Next page',
		} );
		await user.click( next[ 0 ] as HTMLElement );
		await waitFor( () =>
			expect(
				mocks.listTransactions.mock.calls.at( -1 )?.[ 1 ].page
			).toBe( 2 )
		);
	} );

	it( 'searches by order number and filters by payment method', async () => {
		const user = userEvent.setup();
		show( '/earnings' );
		await screen.findByRole( 'table', { name: 'Transactions' } );
		await user.type( screen.getByLabelText( 'Order number' ), '77{Enter}' );
		await waitFor( () =>
			expect(
				mocks.listTransactions.mock.calls.at( -1 )?.[ 1 ].search
			).toBe( '77' )
		);
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Payment method' } ),
			'paypal'
		);
		await waitFor( () =>
			expect(
				mocks.listTransactions.mock.calls.at( -1 )?.[ 1 ].payment_method
			).toBe( 'paypal' )
		);
	} );

	it( 'shows empty tables and keeps the rest of the page when one table fails', async () => {
		mocks.listTransactions.mockResolvedValue( collection( [], 0 ) );
		mocks.listCourseReport.mockRejectedValue(
			failure(
				500,
				'ohmylms_analytics_query_failed',
				'Courses are unavailable.'
			)
		);
		show( '/earnings' );
		expect(
			await screen.findByText( 'No orders in this period.' )
		).toBeInTheDocument();
		expect(
			await screen.findByText( /Courses are unavailable\./, notice )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'list', { name: 'Summary' } )
		).toBeInTheDocument();
	} );

	it( 'shows a permission error', async () => {
		mocks.getEarnings.mockRejectedValue(
			failure( 401, 'ohmylms_analytics_cannot_view', 'Sorry.' )
		);
		show( '/earnings' );
		expect(
			await screen.findByText(
				'You do not have permission to view analytics.',
				notice
			)
		).toBeInTheDocument();
	} );
} );

describe( 'course report', () => {
	it( 'shows the course, its figures and its students', async () => {
		show( '/reports/courses/41' );
		expect(
			await screen.findByRole( 'heading', { name: 'Intro to Algebra' } )
		).toBeInTheDocument();
		expect( mocks.getCourseReport.mock.calls[ 0 ]?.[ 0 ] ).toBe( 41 );
		expect(
			screen.getByText(
				'1 chapters, 2 lessons, 1 quizzes, 0 assignments.',
				{ exact: false }
			)
		).toBeInTheDocument();
		const students = await screen.findByRole( 'table', {
			name: 'Students of this course',
		} );
		expect(
			within( students ).getByText( 'Ada Lovelace' )
		).toBeInTheDocument();
		expect(
			within( students ).getByText( 'ada@example.com' )
		).toBeInTheDocument();
		expect(
			within( students ).getByRole( 'progressbar', {
				name: 'Progress of Ada Lovelace',
			} )
		).toHaveAttribute( 'value', '66' );
		expect(
			within( students ).getByText( 'Completed' )
		).toBeInTheDocument();
	} );

	it( 'filters students by progress', async () => {
		const user = userEvent.setup();
		show( '/reports/courses/41' );
		await screen.findByRole( 'table', { name: 'Students of this course' } );
		await user.click(
			await screen.findByRole( 'link', { name: 'Completed' } )
		);
		await waitFor( () =>
			expect(
				mocks.listCourseStudents.mock.calls.at( -1 )?.[ 1 ].progress
			).toBe( 'completed' )
		);
	} );

	it( 'explains a missing course without a retry button', async () => {
		mocks.getCourseReport.mockRejectedValue(
			failure(
				404,
				'ohmylms_analytics_course_not_found',
				'Course not found.'
			)
		);
		show( '/reports/courses/999' );
		expect(
			await screen.findByText( 'That course does not exist.', notice )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Try again' } )
		).not.toBeInTheDocument();
	} );

	it( 'rejects an invalid course id without any request', async () => {
		show( '/reports/courses/abc' );
		expect(
			await screen.findByText( 'That is not a valid course.', notice )
		).toBeInTheDocument();
		expect( mocks.getCourseReport ).not.toHaveBeenCalled();
		expect( mocks.listCourseStudents ).not.toHaveBeenCalled();
	} );

	it( 'shows an empty student list', async () => {
		mocks.listCourseStudents.mockResolvedValue( collection( [], 0 ) );
		show( '/reports/courses/41' );
		expect(
			await screen.findByText( 'No students found.' )
		).toBeInTheDocument();
	} );
} );
