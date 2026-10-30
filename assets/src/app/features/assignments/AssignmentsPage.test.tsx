import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import AssignmentsPage from './AssignmentsPage';
import { assignment, summary } from './fixtures';

vi.mock( './api', () => ( {
	listAssignments: vi.fn(),
	listCourses: vi.fn(),
	createAssignment: vi.fn(),
	trashAssignment: vi.fn(),
} ) );

const draft = {
	...summary,
	id: 32,
	title: 'Map drawing',
	status: 'draft' as const,
	course: null,
	submission_count: 0,
	pending_count: 0,
};

function renderPage() {
	return render(
		<MemoryRouter initialEntries={ [ '/assignments' ] }>
			<Routes>
				<Route path="/assignments" element={ <AssignmentsPage /> } />
				<Route
					path="/assignments/:id"
					element={ <p>Editor route reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listAssignments ).mockResolvedValue( {
		items: [ summary, draft ],
		total: 2,
		totalPages: 1,
	} );
	vi.mocked( api.listCourses ).mockResolvedValue( {
		items: [ { id: 12, title: 'Geography', status: 'publish' } ],
		total: 1,
		totalPages: 1,
	} );
} );

describe( 'AssignmentsPage', () => {
	it( 'lists assignments with status, course and submissions', async () => {
		renderPage();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading assignments'
		);
		const table = await screen.findByRole( 'table', {
			name: 'Assignments',
		} );
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 4 );
		const first = within( rows[ 1 ]! );
		expect(
			first.getByRole( 'link', { name: 'Essay on rivers' } )
		).toHaveAttribute( 'href', '/assignments/31' );
		expect( first.getByText( 'Published' ) ).toBeInTheDocument();
		expect( first.getByText( 'Geography' ) ).toBeInTheDocument();
		expect( first.getByText( '4 (1 to grade)' ) ).toBeInTheDocument();
		expect(
			first.getByRole( 'link', {
				name: 'Submissions of Essay on rivers',
			} )
		).toHaveAttribute( 'href', '/assignments/31/submissions' );
		const second = within( rows[ 2 ]! );
		expect( second.getByText( 'Draft' ) ).toBeInTheDocument();
		expect( second.getByText( 'Not in a course' ) ).toBeInTheDocument();
		expect(
			screen.getAllByText( '2 assignments found.' )[ 0 ]
		).toBeInTheDocument();
	} );
	it( 'filters by search, status and course', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search assignments' } ),
			'essay{Enter}'
		);
		await waitFor( () =>
			expect( api.listAssignments ).toHaveBeenLastCalledWith(
				1,
				{ search: 'essay', status: 'all', course: 0 },
				expect.any( AbortSignal )
			)
		);
		await user.click(
			await screen.findByRole( 'link', { name: 'Draft' } )
		);
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Course' } ),
			'12'
		);
		await waitFor( () =>
			expect( api.listAssignments ).toHaveBeenLastCalledWith(
				1,
				{ search: 'essay', status: 'draft', course: 12 },
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'shows a permission error and an empty state', async () => {
		vi.mocked( api.listAssignments ).mockRejectedValueOnce(
			new ApiError( {
				status: 403,
				code: 'ohmylms_assignment_cannot_view',
				message: 'Sorry, you are not allowed to view assignments.',
			} )
		);
		renderPage();
		expect(
			await screen.findByText( /not allowed to view assignments/, notice )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
		vi.mocked( api.listAssignments ).mockResolvedValueOnce( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		await userEvent
			.setup()
			.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByText( 'No assignments found.' )
		).toBeInTheDocument();
	} );
	it( 'validates the title, creates a draft and opens the editor', async () => {
		vi.mocked( api.createAssignment ).mockResolvedValue( assignment );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', { name: 'Add assignment' } )
		);
		const dialog = screen.getByRole( 'dialog' );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Create assignment',
			} )
		);
		expect( within( dialog ).getByRole( 'alert' ) ).toHaveTextContent(
			'Enter a title of 1 to 200 characters.'
		);
		expect( api.createAssignment ).not.toHaveBeenCalled();
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Assignment title',
			} ),
			'Essay{Enter}'
		);
		expect(
			await screen.findByText( 'Editor route reached' )
		).toBeInTheDocument();
		expect( api.createAssignment ).toHaveBeenCalledWith(
			'Essay',
			expect.any( AbortSignal )
		);
	} );
	it( 'keeps the dialog open with the server reason when trashing is refused', async () => {
		vi.mocked( api.trashAssignment ).mockRejectedValueOnce(
			new ApiError( {
				status: 409,
				code: 'ohmylms_assignment_has_submissions',
				message:
					'This assignment has learner submissions and cannot be deleted. Set it to draft instead.',
			} )
		);
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Move Essay on rivers to the trash',
			} )
		);
		const dialog = screen.getByRole( 'dialog' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Move to trash' } )
		);
		expect(
			await within( dialog ).findByText( /has learner submissions/ )
		).toBeInTheDocument();
		vi.mocked( api.trashAssignment ).mockResolvedValueOnce( undefined );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Move to trash' } )
		);
		expect(
			await screen.findByText(
				'Moved Essay on rivers to the trash.',
				notice
			)
		).toBeInTheDocument();
		expect( api.trashAssignment ).toHaveBeenLastCalledWith(
			31,
			expect.any( AbortSignal )
		);
		expect( api.listAssignments ).toHaveBeenCalledTimes( 2 );
	} );
} );
