import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { graded, pendingDetail } from './fixtures';
import GradingPage from './GradingPage';

vi.mock( './api', () => ( {
	getSubmission: vi.fn(),
	gradeSubmission: vi.fn(),
} ) );

function renderPage( path = '/assignments/31/submissions/501' ) {
	return render(
		<MemoryRouter initialEntries={ [ path ] }>
			<Routes>
				<Route
					path="/assignments/:id/submissions/:submissionId"
					element={ <GradingPage /> }
				/>
				<Route
					path="/assignments/:id/submissions"
					element={ <p>List reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getSubmission ).mockResolvedValue( pendingDetail );
} );

describe( 'GradingPage', () => {
	it( 'shows the learner work and the authorised file link', async () => {
		renderPage();
		expect(
			await screen.findByRole( 'heading', {
				name: 'Submission by Alex Learner',
			} )
		).toBeInTheDocument();
		expect( api.getSubmission ).toHaveBeenCalledWith(
			31,
			501,
			expect.any( AbortSignal )
		);
		expect( screen.getByText( 'My essay text.' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Needs grading' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'essay.pdf' } )
		).toHaveAttribute( 'href', pendingDetail.attachments[ 0 ]!.url );
		expect( screen.getByText( '(20 KB)' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'textbox', { name: 'Score (out of 50)' } )
		).toHaveValue( '' );
	} );
	it( 'enforces 0..max before sending', async () => {
		const user = userEvent.setup();
		renderPage();
		const score = await screen.findByRole( 'textbox', {
			name: 'Score (out of 50)',
		} );
		await user.type( score, '51' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save grade' } )
		);
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'Enter a whole number from 0 to 50.'
		);
		expect( score ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( api.gradeSubmission ).not.toHaveBeenCalled();
	} );
	it( 'grades with feedback and shows the persisted result', async () => {
		vi.mocked( api.gradeSubmission ).mockResolvedValue( graded );
		const user = userEvent.setup();
		renderPage();
		await user.type(
			await screen.findByRole( 'textbox', { name: 'Score (out of 50)' } ),
			'40'
		);
		await user.type(
			screen.getByRole( 'textbox', { name: 'Feedback for the learner' } ),
			'Well done.'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save grade' } )
		);
		expect(
			await screen.findByText(
				'Grade saved. The learner passed.',
				notice
			)
		).toBeInTheDocument();
		expect( api.gradeSubmission ).toHaveBeenCalledWith(
			31,
			501,
			{ score: 40, feedback: 'Well done.' },
			expect.any( AbortSignal )
		);
		expect( screen.getByText( 'Passed' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'textbox', { name: 'Feedback for the learner' } )
		).toHaveValue( '<p>Well done.</p>' );
		expect(
			screen.getByRole( 'button', { name: 'Update grade' } )
		).toBeInTheDocument();
	} );
	it( 'shows the server refusal and keeps the typed grade', async () => {
		vi.mocked( api.gradeSubmission ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_assignment_not_in_course',
				message:
					'This assignment is not placed in a course, so it cannot be graded. Add it to a course first.',
			} )
		);
		const user = userEvent.setup();
		renderPage();
		const score = await screen.findByRole( 'textbox', {
			name: 'Score (out of 50)',
		} );
		await user.type( score, '30' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save grade' } )
		);
		expect(
			await screen.findByText( /not placed in a course/, notice )
		).toBeInTheDocument();
		expect( score ).toHaveValue( '30' );
	} );
	it( 'reports a submission that cannot be loaded', async () => {
		vi.mocked( api.getSubmission ).mockRejectedValue(
			new ApiError( {
				status: 404,
				code: 'ohmylms_assignment_submission_not_found',
				message: 'Submission not found.',
			} )
		);
		renderPage();
		expect(
			await screen.findByText( 'Submission not found.', notice )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Back to submissions' } )
		).toHaveAttribute( 'href', '/assignments/31/submissions' );
	} );
} );
