import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { assignment, graded, pending } from './fixtures';
import SubmissionsPage from './SubmissionsPage';

vi.mock( './api', () => ( {
	getAssignment: vi.fn(),
	listSubmissions: vi.fn(),
} ) );

function renderPage( path = '/assignments/31/submissions' ) {
	return render(
		<MemoryRouter initialEntries={ [ path ] }>
			<Routes>
				<Route
					path="/assignments/:id/submissions"
					element={ <SubmissionsPage /> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getAssignment ).mockResolvedValue( assignment );
	vi.mocked( api.listSubmissions ).mockResolvedValue( {
		items: [ pending, { ...graded, id: 502 } ],
		total: 2,
		totalPages: 1,
	} );
} );

describe( 'SubmissionsPage', () => {
	it( 'lists submissions with display names only and links to grading', async () => {
		renderPage();
		const table = await screen.findByRole( 'table', {
			name: 'Submissions',
		} );
		expect(
			await screen.findByRole( 'heading', {
				name: 'Submissions: Essay on rivers',
			} )
		).toBeInTheDocument();
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 3 );
		const first = within( rows[ 1 ]! );
		expect( first.getByText( 'Alex Learner' ) ).toBeInTheDocument();
		expect( first.getByText( 'Needs grading' ) ).toBeInTheDocument();
		expect(
			first.getByRole( 'link', {
				name: 'Open submission by Alex Learner',
			} )
		).toHaveAttribute( 'href', '/assignments/31/submissions/501' );
		expect( first.getByText( 'Grade' ) ).toBeInTheDocument();
		const second = within( rows[ 2 ]! );
		expect( second.getByText( 'Passed' ) ).toBeInTheDocument();
		expect( second.getByText( '40 / 50' ) ).toBeInTheDocument();
		expect( second.getByText( 'Review' ) ).toBeInTheDocument();
		expect( table ).not.toHaveTextContent( '@' );
	} );
	it( 'filters by grading state from page one', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Show' } ),
			'submitted'
		);
		await waitFor( () =>
			expect( api.listSubmissions ).toHaveBeenLastCalledWith(
				31,
				1,
				'submitted',
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'shows the server refusal for people who cannot grade', async () => {
		vi.mocked( api.listSubmissions ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_assignment_cannot_grade',
				message:
					'Sorry, you are not allowed to review submissions of this assignment.',
			} )
		);
		renderPage();
		expect(
			await screen.findByText(
				/not allowed to review submissions/,
				notice
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );
	it( 'shows an empty state and rejects an invalid link', async () => {
		vi.mocked( api.listSubmissions ).mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		renderPage();
		expect(
			await screen.findByText( 'No submissions found.' )
		).toBeInTheDocument();
		renderPage( '/assignments/0/submissions' );
		expect(
			screen.getByText( 'This assignment link is invalid.', notice )
		).toBeInTheDocument();
	} );
} );
