import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../../lib/api';
import * as api from '../shell/api';
import {
	algebraClass,
	attention,
	context,
	emptyAttention,
	envelope,
	northScope,
} from '../shell/fixtures';
import type { WidgetContext } from '../shell/model';
import { parseNeedsAttention } from './needsAttention';
import NeedsAttentionWidget from './NeedsAttentionWidget';

vi.mock( '../shell/api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );

const notice = '.components-notice__content';
const getWidget = vi.mocked( api.getWidget );
const snoozeItem = vi.mocked( api.snoozeItem );
const wakeItem = vi.mocked( api.wakeItem );

function show( current: WidgetContext = context() ) {
	return render(
		<MemoryRouter>
			<NeedsAttentionWidget context={ current } />
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.clearAllMocks();
	getWidget.mockImplementation( async ( widget, scope ) =>
		envelope( widget, scope, attention )
	);
	snoozeItem.mockResolvedValue( {} );
	wakeItem.mockResolvedValue( {} );
} );

describe( 'needs attention data', () => {
	it( 'validates items and rejects malformed ones', () => {
		const raw = {
			items: [
				{
					key: 'stalling_course:4',
					urgency: 60,
					tone: 'warning',
					title: 'T',
					detail: 'D',
					count: 3,
					action: { label: 'Open', route: '/reports/courses/4' },
				},
			],
			snoozed: 0,
			grading_days: 3,
		};
		expect( parseNeedsAttention( raw ).items[ 0 ]?.action?.route ).toBe(
			'/reports/courses/4'
		);
		expect( parseNeedsAttention( raw ).gradingDays ).toBe( 3 );
		const bad = ( item: Record< string, unknown > ) => () =>
			parseNeedsAttention( {
				...raw,
				items: [ { ...raw.items[ 0 ], ...item } ],
			} );
		expect( bad( { tone: 'red' } ) ).toThrow();
		expect( bad( { key: 'Bad key' } ) ).toThrow();
		expect(
			bad( { action: { label: 'x', route: 'https://evil.test' } } )
		).toThrow();
		expect(
			parseNeedsAttention( {
				...raw,
				items: [ { ...raw.items[ 0 ], action: null } ],
			} ).items[ 0 ]?.action
		).toBeNull();
	} );
} );

describe( 'needs attention widget', () => {
	it( 'asks for the scope and period, then lists the ranked queue', async () => {
		show( context( algebraClass, 7 ) );
		const list = await screen.findByRole( 'list' );
		expect( getWidget ).toHaveBeenCalledWith(
			'needs-attention',
			'class:9',
			7,
			parseNeedsAttention,
			expect.any( AbortSignal )
		);
		const rows = within( list ).getAllByRole( 'listitem' );
		expect( rows ).toHaveLength( 3 );
		expect( rows[ 0 ] ).toHaveTextContent(
			'12 submissions waiting more than 3 days to be graded'
		);
		expect( rows[ 0 ] ).toHaveClass( 'is-warning' );
		expect(
			within( rows[ 0 ] as HTMLElement ).getByText( '(Soon)' )
		).toHaveClass( 'screen-reader-text' );
		expect(
			screen.getByText( '3 open · sorted by urgency · 1 snoozed' )
		).toBeInTheDocument();
	} );

	it( 'links actions through the router and leaves out links the user cannot open', async () => {
		show();
		const grade = await screen.findByRole( 'link', { name: 'Grade now' } );
		expect( grade ).toHaveAttribute(
			'href',
			'/assignments/55/submissions'
		);
		expect( grade ).toHaveClass( 'button', 'button-primary' );
		const edit = screen.getByRole( 'link', { name: 'Edit question' } );
		expect( edit ).toHaveClass( 'button' );
		expect( edit ).not.toHaveClass( 'button-primary' );
		const stalling = screen
			.getByText( 'Intro to Algebra is stalling' )
			.closest( 'li' ) as HTMLElement;
		expect( within( stalling ).queryByRole( 'link' ) ).toBeNull();
		expect(
			within( stalling ).getByRole( 'button', {
				name: 'Snooze for 7 days: Intro to Algebra is stalling',
			} )
		).toBeInTheDocument();
	} );

	it( 'snoozes an item, announces it and can undo', async () => {
		const user = userEvent.setup();
		show();
		await screen.findByRole( 'list' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Snooze for 7 days: Intro to Algebra is stalling',
			} )
		);
		expect( snoozeItem ).toHaveBeenCalledWith(
			'school:3',
			'stalling_course:41'
		);
		expect(
			screen.queryByText( 'Intro to Algebra is stalling' )
		).not.toBeInTheDocument();
		expect( await screen.findByRole( 'status' ) ).toHaveTextContent(
			'Snoozed for 7 days: Intro to Algebra is stalling.'
		);
		expect(
			screen.getByText( '2 open · sorted by urgency · 2 snoozed' )
		).toBeInTheDocument();

		await user.click( screen.getByRole( 'button', { name: 'Undo' } ) );
		expect( wakeItem ).toHaveBeenCalledWith(
			'school:3',
			'stalling_course:41'
		);
		expect(
			await screen.findByText( 'Intro to Algebra is stalling' )
		).toBeInTheDocument();
		await waitFor( () => expect( getWidget ).toHaveBeenCalledTimes( 2 ) );
	} );

	it( 'puts the item back when snoozing fails', async () => {
		const user = userEvent.setup();
		snoozeItem.mockRejectedValueOnce(
			new ApiError( {
				status: 403,
				code: 'ohmylms_dashboard_forbidden_scope',
				message: 'No.',
			} )
		);
		show();
		await screen.findByRole( 'list' );
		await user.click(
			screen.getAllByRole( 'button', {
				name: /^Snooze for 7 days/,
			} )[ 0 ]!
		);
		expect(
			await screen.findByText( 'You cannot view this school or class.', {
				selector: notice,
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'12 submissions waiting more than 3 days to be graded'
			)
		).toBeInTheDocument();
	} );

	it( 'says when nothing needs attention', async () => {
		getWidget.mockImplementation( async ( widget, scope ) =>
			envelope( widget, scope, emptyAttention )
		);
		show();
		expect(
			await screen.findByText(
				'Nothing needs your attention right now. Everything else is on track.'
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'list' ) ).not.toBeInTheDocument();
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

	it( 'forgets optimistic snoozes when the scope changes', async () => {
		const user = userEvent.setup();
		const { rerender } = show();
		await screen.findByRole( 'list' );
		await user.click(
			screen.getAllByRole( 'button', {
				name: /^Snooze for 7 days/,
			} )[ 0 ]!
		);
		await screen.findByRole( 'status' );
		rerender(
			<MemoryRouter>
				<NeedsAttentionWidget context={ context( algebraClass ) } />
			</MemoryRouter>
		);
		expect(
			await screen.findByText(
				'12 submissions waiting more than 3 days to be graded'
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument();
		expect( northScope.id ).toBe( 'school:3' );
	} );
} );
