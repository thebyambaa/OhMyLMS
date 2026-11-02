import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import * as api from '../shell/api';
import { context, envelope, northScope } from '../shell/fixtures';
import { parseActivity, timeAgo } from './ActivityModel';
import ActivityWidget from './ActivityWidget';

vi.mock( '../shell/api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );

const getWidget = vi.mocked( api.getWidget );
const now = Date.parse( '2026-10-31T09:30:00+08:00' );

const raw = {
	events: [
		{
			id: 'quiz_passed:9',
			type: 'quiz_passed',
			at: '2026-10-31T09:30:00+08:00',
			text: 'Ana Example passed Algebra Quiz 3 · Algebra',
		},
		{
			id: 'lesson_completed:4',
			type: 'lesson_completed',
			at: '2026-10-31T09:24:00+08:00',
			text: 'Chen Sample completed the lesson Lesson 7 · SAT Prep',
		},
		{
			id: 'enrolled:2',
			type: 'enrolled',
			at: '2026-10-29T08:00:00+08:00',
			text: 'Lena Dummy enrolled in Essay Workshop',
		},
	],
	limit: 10,
};

beforeEach( () => {
	vi.clearAllMocks();
	getWidget.mockImplementation( async ( widget, scope, period, parse ) =>
		envelope( widget, scope, parse( raw ), period )
	);
} );

describe( 'activity data', () => {
	it( 'validates events', () => {
		expect( parseActivity( raw ).events ).toHaveLength( 3 );
		expect( () =>
			parseActivity( { events: [ { id: 1 } ], limit: 10 } )
		).toThrow();
		expect( () => parseActivity( { events: [] } ) ).toThrow();
	} );

	it( 'tells how long ago an event happened', () => {
		expect( timeAgo( '2026-10-31T09:29:40+08:00', now, 'en' ) ).toBe(
			'just now'
		);
		expect( timeAgo( '2026-10-31T09:24:00+08:00', now, 'en' ) ).toBe(
			'6 minutes ago'
		);
		expect( timeAgo( '2026-10-31T06:30:00+08:00', now, 'en' ) ).toBe(
			'3 hours ago'
		);
		expect( timeAgo( '2026-10-29T08:00:00+08:00', now, 'en' ) ).toBe(
			'2 days ago'
		);
		expect( timeAgo( 'not a date', now, 'en' ) ).toBe( '' );
	} );
} );

describe( 'activity widget', () => {
	it( 'lists the newest events with their times', async () => {
		render(
			<MemoryRouter>
				<ActivityWidget context={ context( northScope, 7 ) } />
			</MemoryRouter>
		);
		const list = await screen.findByRole( 'list' );
		expect( getWidget ).toHaveBeenCalledWith(
			'activity',
			'school:3',
			7,
			parseActivity,
			expect.any( AbortSignal )
		);
		const items = within( list ).getAllByRole( 'listitem' );
		expect( items ).toHaveLength( 3 );
		expect( items[ 0 ] ).toHaveTextContent(
			'Ana Example passed Algebra Quiz 3 · Algebra'
		);
		expect( items[ 0 ]?.querySelector( 'time' ) ).toHaveAttribute(
			'datetime',
			'2026-10-31T09:30:00+08:00'
		);
		expect( screen.getByText( 'latest 10 events' ) ).toBeInTheDocument();
	} );

	it( 'says when nothing happened', async () => {
		getWidget.mockImplementation( async ( widget, scope, period, parse ) =>
			envelope(
				widget,
				scope,
				parse( { events: [], limit: 10 } ),
				period
			)
		);
		render(
			<MemoryRouter>
				<ActivityWidget context={ context() } />
			</MemoryRouter>
		);
		expect(
			await screen.findByText( 'No learner activity in this period yet.' )
		).toBeInTheDocument();
	} );
} );
