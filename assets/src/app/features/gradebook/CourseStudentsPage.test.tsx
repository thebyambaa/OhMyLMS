import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import CourseStudentsPage from './CourseStudentsPage';
import { context, detail, grid } from './fixtures';

vi.mock( './api', () => ( {
	getContext: vi.fn(),
	getCourseGrid: vi.fn(),
	getCell: vi.fn(),
	changeCell: vi.fn(),
	saveWeights: vi.fn(),
	saveItem: vi.fn(),
	listViews: vi.fn(),
	createView: vi.fn(),
	deleteView: vi.fn(),
} ) );

const mocked = vi.mocked( api );

function Search() {
	return <output aria-label="address">{ useLocation().search }</output>;
}

function show( route = '/content-hub/gradebook?course=12' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<CourseStudentsPage />
			<Search />
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.clearAllMocks();
	mocked.getContext.mockResolvedValue( context );
	mocked.getCourseGrid.mockResolvedValue( grid );
	mocked.getCell.mockResolvedValue( detail );
	mocked.listViews.mockResolvedValue( [] );
} );

describe( 'Course × Students', () => {
	it( 'shows learners, sources, rosters, totals, weighting and the legend', async () => {
		show();
		const table = await screen.findByRole( 'grid', {
			name: 'Grades for Algebra',
		} );
		expect(
			within( table ).getByRole( 'rowheader', { name: /Ana Example/ } )
		).toBeInTheDocument();
		expect(
			within( table ).getByRole( 'gridcell', { name: 'Total 32% F' } )
		).toBeInTheDocument();
		expect(
			within( table ).getByRole( 'gridcell', {
				name: 'Practice set: Missing',
			} )
		).toBeInTheDocument();
		expect(
			within( table ).getByRole( 'gridcell', {
				name: 'Retake: Not assigned',
			} )
		).toBeInTheDocument();
		expect(
			within( table ).getByRole( 'gridcell', {
				name: 'Linear quiz: 90% (9 of 10), set manually; computed: To grade',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				/Weighting for Algebra \(per course\): homework 40% · quiz 30% · test 30%/
			)
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Grade 8A override: homework 20% · quiz 30% · test 50%'
			)
		).toBeInTheDocument();
		expect( screen.getAllByText( 'Not assigned' ).length ).toBeGreaterThan(
			0
		);
		expect( mocked.getCourseGrid ).toHaveBeenCalledWith(
			expect.objectContaining( { course: 12, audience: 'all' } ),
			expect.anything()
		);
	} );

	it( 'picks the first course when none is chosen', async () => {
		show( '/content-hub/gradebook' );
		await waitFor( () =>
			expect( screen.getByLabelText( 'address' ) ).toHaveTextContent(
				'course=12'
			)
		);
	} );

	it( 'keeps filters in the address and sends them to the server', async () => {
		show();
		const user = userEvent.setup();
		await screen.findByRole( 'grid' );
		await user.selectOptions(
			screen.getByLabelText( 'Audience' ),
			'class:7'
		);
		expect( screen.getByLabelText( 'address' ) ).toHaveTextContent(
			'audience=class%3A7'
		);
		await user.selectOptions(
			screen.getByLabelText( 'Status' ),
			'missing'
		);
		await user.selectOptions( screen.getByLabelText( 'Type' ), 'homework' );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search learners' } ),
			'ana{Enter}'
		);
		await waitFor( () =>
			expect( mocked.getCourseGrid ).toHaveBeenLastCalledWith(
				expect.objectContaining( {
					audience: 'class:7',
					status: 'missing',
					type: 'homework',
					search: 'ana',
				} ),
				expect.anything()
			)
		);
		await user.selectOptions(
			screen.getByLabelText( 'Audience' ),
			'individuals'
		);
		expect( screen.getByLabelText( 'address' ) ).toHaveTextContent(
			'audience=individuals'
		);
	} );

	it( 'opens a grade from the keyboard and saves an override with a reason', async () => {
		mocked.changeCell.mockResolvedValue( {
			...detail,
			cell: {
				...detail.cell,
				state: 'score',
				score: 15,
				percent: 75,
				overridden: true,
				version: 1,
				reason: 'Paper copy',
			},
			history: [
				{
					id: 1,
					action: 'override',
					old: { kind: 'none', score: null },
					new: { kind: 'score', score: 15 },
					max: 20,
					computed_score: null,
					reason: 'Paper copy',
					by: { id: 9, name: 'Teacher' },
					at: '2026-10-31T10:00:00+00:00',
				},
			],
		} );
		show();
		const user = userEvent.setup();
		const table = await screen.findByRole( 'grid' );
		within( table )
			.getByRole( 'gridcell', { name: 'Practice set: To grade' } )
			.focus();
		await user.keyboard( '{Enter}' );
		const dialog = await screen.findByRole( 'dialog' );
		expect( mocked.getCell ).toHaveBeenCalledWith(
			'',
			12,
			102,
			2,
			expect.anything()
		);
		await within( dialog ).findByText( 'Computed' );
		await user.type( within( dialog ).getByLabelText( /Score/ ), '15' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save grade' } )
		);
		expect(
			within( dialog ).getByText( 'Give a reason for the change.' )
		).toBeInTheDocument();
		expect( mocked.changeCell ).not.toHaveBeenCalled();
		await user.type(
			within( dialog ).getByLabelText( /Reason/ ),
			'Paper copy'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save grade' } )
		);
		expect( mocked.changeCell ).toHaveBeenCalledWith( '', 12, 102, 2, {
			action: 'override',
			score: 15,
			reason: 'Paper copy',
			expected_version: 0,
		} );
		expect(
			await within( dialog ).findByText(
				'Saved. The total is recalculated.'
			)
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'cell', { name: 'Paper copy' } )
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'button', {
				name: 'Revert to computed',
			} )
		).toBeInTheDocument();
		expect( mocked.getCourseGrid ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'shows the current grade after a stale write (409)', async () => {
		mocked.changeCell.mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_gradebook_stale',
				message: 'Someone changed this grade since you opened it.',
				details: {
					status: 409,
					current: {
						...detail,
						cell: {
							...detail.cell,
							state: 'excused',
							overridden: true,
							version: 4,
							reason: 'Medical',
						},
					},
				},
			} )
		);
		show();
		const user = userEvent.setup();
		const table = await screen.findByRole( 'grid' );
		await user.click(
			within( table ).getByRole( 'gridcell', {
				name: 'Practice set: To grade',
			} )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await within( dialog ).findByText( 'Computed' );
		await user.type( within( dialog ).getByLabelText( /Reason/ ), 'Late' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Excuse' } )
		);
		expect(
			await within( dialog ).findByText(
				/Someone changed this grade while you had it open/
			)
		).toBeInTheDocument();
		expect(
			within( dialog ).getByText( /Reason: Medical/ )
		).toBeInTheDocument();
	} );

	it( 'does not open not-assigned cells', async () => {
		show();
		const user = userEvent.setup();
		const table = await screen.findByRole( 'grid' );
		await user.click(
			within( table ).getByRole( 'gridcell', {
				name: 'Retake: Not assigned',
			} )
		);
		expect( screen.queryByRole( 'dialog' ) ).toBeNull();
	} );

	it( 'edits weights and blocks sums other than 100', async () => {
		mocked.saveWeights.mockResolvedValue( {
			class_id: 0,
			weights: { homework: 50, quiz: 30, test: 20 },
			version: 2,
		} );
		show();
		const user = userEvent.setup();
		await screen.findByRole( 'grid' );
		await user.click(
			screen.getByRole( 'button', { name: 'Edit weights' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Category weights',
		} );
		const homework =
			within( dialog ).getByLabelText( 'Weight of homework' );
		await user.clear( homework );
		await user.type( homework, '60' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save weights' } )
		);
		expect(
			within( dialog ).getByText( /add up to 120%/ )
		).toBeInTheDocument();
		expect( mocked.saveWeights ).not.toHaveBeenCalled();
		const test = within( dialog ).getByLabelText( 'Weight of test' );
		await user.clear( test );
		await user.type( test, '10' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Save weights' } )
		);
		expect( mocked.saveWeights ).toHaveBeenCalledWith(
			'',
			12,
			0,
			{ homework: 60, quiz: 30, test: 10 },
			1
		);
	} );

	it( 'saves a view and shares the address', async () => {
		mocked.createView.mockResolvedValue( {
			id: 'abcdefabcdef',
			name: 'Missing work',
			section: 'course',
			query: 'course=12',
			created_at: '',
		} );
		show();
		const user = userEvent.setup();
		await screen.findByRole( 'grid' );
		await user.click( screen.getByRole( 'button', { name: 'Save view' } ) );
		const name = screen.getByLabelText( 'Name of the view' );
		await user.clear( name );
		await user.type( name, 'Missing work{Enter}' );
		expect( mocked.createView ).toHaveBeenCalledWith(
			'Missing work',
			'course',
			'course=12'
		);
		expect(
			( await screen.findAllByText( 'Saved the view “Missing work”.' ) )
				.length
		).toBeGreaterThan( 0 );
		await user.click( screen.getByRole( 'button', { name: 'Share' } ) );
		await expect( window.navigator.clipboard.readText() ).resolves.toBe(
			window.location.href
		);
		expect(
			( await screen.findAllByText( /Link copied/ ) ).length
		).toBeGreaterThan( 0 );
	} );

	it( 'reports a load error with a retry', async () => {
		mocked.getCourseGrid.mockRejectedValueOnce(
			new ApiError( {
				status: 404,
				code: 'ohmylms_gradebook_course_not_found',
				message: 'This course is not in the selected school or class.',
			} )
		);
		show();
		expect(
			await screen.findByText(
				'This course is not in the selected school or class.'
			)
		).toBeInTheDocument();
		await userEvent
			.setup()
			.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect( await screen.findByRole( 'grid' ) ).toBeInTheDocument();
	} );
} );
