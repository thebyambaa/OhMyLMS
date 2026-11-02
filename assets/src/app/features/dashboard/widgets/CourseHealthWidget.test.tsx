import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ApiError } from '../../../lib/api';
import * as api from '../shell/api';
import { algebraClass, context, envelope } from '../shell/fixtures';
import type { WidgetContext } from '../shell/model';
import { healthBand, parseCourseHealth } from './CourseHealthModel';
import CourseHealthWidget from './CourseHealthWidget';

vi.mock( '../shell/api', () => ( {
	getScopes: vi.fn(),
	getWidget: vi.fn(),
	snoozeItem: vi.fn(),
	wakeItem: vi.fn(),
} ) );

const getWidget = vi.mocked( api.getWidget );

const raw = {
	courses: [
		{
			id: 41,
			title: 'Digital SAT Math Prep',
			enrolled: 120,
			completed: 18,
			completion: 15,
			rating: { average: 3.8, count: 12 },
			items: 9,
			lessons: 7,
			median_progress: 44,
			drop_off: {
				content_id: 406,
				title: 'Lesson 6',
				type: 'lesson',
				position: 6,
				lost: 70,
				percent: 58,
			},
			report: '/reports/courses/41',
		},
		{
			id: 42,
			title: 'Essay Writing Workshop',
			enrolled: 40,
			completed: 28,
			completion: 70,
			rating: { average: 4.6, count: 9 },
			items: 5,
			lessons: 5,
			median_progress: 100,
			drop_off: null,
			report: null,
		},
		{
			id: 43,
			title: 'Fractions',
			enrolled: 3,
			completed: 1,
			completion: 33,
			rating: null,
			items: 0,
			lessons: 0,
			median_progress: null,
			drop_off: null,
			report: null,
		},
	],
	total: 5,
	limit: 3,
};

function show( current: WidgetContext = context() ) {
	return render(
		<MemoryRouter>
			<CourseHealthWidget context={ current } />
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.clearAllMocks();
	getWidget.mockImplementation( async ( widget, scope, period, parse ) =>
		envelope( widget, scope, parse( raw ), period )
	);
} );

describe( 'course health data', () => {
	it( 'validates courses and rejects malformed ones', () => {
		const data = parseCourseHealth( raw );
		expect( data.courses ).toHaveLength( 3 );
		expect( data.courses[ 0 ]?.dropOff ).toEqual( {
			contentId: 406,
			title: 'Lesson 6',
			type: 'lesson',
			position: 6,
			lost: 70,
			percent: 58,
		} );
		expect( data.courses[ 2 ]?.medianProgress ).toBeNull();
		expect( data.total ).toBe( 5 );
		const bad = ( change: Record< string, unknown > ) => () =>
			parseCourseHealth( {
				...raw,
				courses: [ { ...raw.courses[ 0 ], ...change } ],
			} );
		expect( bad( { completion: 120 } ) ).toThrow();
		expect( bad( { rating: { average: 7, count: 1 } } ) ).toThrow();
		expect( bad( { report: 'https://evil.test/' } ) ).toThrow();
		expect( bad( { id: 0 } ) ).toThrow();
		expect( () => parseCourseHealth( { courses: {} } ) ).toThrow();
	} );

	it( 'bands courses by completion and rating', () => {
		const [ sat, essay, fractions ] = parseCourseHealth( raw ).courses;
		expect( healthBand( sat! ) ).toBe( 'poor' );
		expect( healthBand( essay! ) ).toBe( 'good' );
		expect( healthBand( fractions! ) ).toBe( 'fair' );
		expect(
			healthBand( {
				...essay!,
				rating: { average: 2.5, count: 4 },
			} )
		).toBe( 'poor' );
	} );
} );

describe( 'course health widget', () => {
	it( 'asks for the scope and period and lists each course with its figures', async () => {
		show( context( algebraClass, 90 ) );
		const table = await screen.findByRole( 'table' );
		expect( getWidget ).toHaveBeenCalledWith(
			'course-health',
			'class:9',
			90,
			parseCourseHealth,
			expect.any( AbortSignal )
		);
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 4 );
		const sat = rows[ 1 ] as HTMLElement;
		expect( sat ).toHaveTextContent( 'At risk' );
		expect( sat ).toHaveTextContent( '120' );
		expect( sat ).toHaveTextContent( '15%' );
		expect( sat ).toHaveTextContent( '3.8 of 5 (12 reviews)' );
		expect( sat ).toHaveTextContent( '44%' );
		expect( sat ).toHaveTextContent(
			'“Lesson 6” (lesson, item 6) · 58% stop'
		);
		expect( rows[ 3 ] ).toHaveTextContent( 'No reviews' );
		expect( rows[ 3 ] ).toHaveTextContent( '—' );
		expect(
			screen.getByText( '3 of 5 courses · most enrolled first' )
		).toBeInTheDocument();
	} );

	it( 'links a course to its report only when the user may open it', async () => {
		show();
		const link = await screen.findByRole( 'link', {
			name: 'Digital SAT Math Prep',
		} );
		expect( link ).toHaveAttribute( 'href', '/reports/courses/41' );
		expect(
			screen.queryByRole( 'link', { name: 'Essay Writing Workshop' } )
		).not.toBeInTheDocument();
	} );

	it( 'plots rated courses with a text alternative', async () => {
		show();
		const chart = await screen.findByRole( 'img', {
			name: /Completion against rating/,
		} );
		expect( chart ).toHaveAccessibleName(
			'Completion against rating. Digital SAT Math Prep: 15% completion, rated 3.8; Essay Writing Workshop: 70% completion, rated 4.6.'
		);
		expect( chart.querySelectorAll( 'circle' ) ).toHaveLength( 2 );
	} );

	it( 'says when the scope has no enrolled learners', async () => {
		getWidget.mockImplementation( async ( widget, scope, period, parse ) =>
			envelope(
				widget,
				scope,
				parse( { courses: [], total: 0, limit: 12 } ),
				period
			)
		);
		show();
		expect(
			await screen.findByText(
				'No learner of this scope is enrolled in a course yet.'
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
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
		await user.click(
			await screen.findByRole( 'button', { name: 'Try again' } )
		);
		expect( await screen.findByRole( 'table' ) ).toBeInTheDocument();
	} );
} );
