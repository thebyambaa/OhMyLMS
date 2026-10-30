import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import AssignmentEditorPage from './AssignmentEditorPage';
import { assignment } from './fixtures';

vi.mock( './api', () => ( {
	getAssignment: vi.fn(),
	updateAssignment: vi.fn(),
} ) );

function renderPage( path = '/assignments/31' ) {
	return render(
		<MemoryRouter initialEntries={ [ path ] }>
			<Routes>
				<Route
					path="/assignments/:id"
					element={ <AssignmentEditorPage /> }
				/>
				<Route path="/assignments" element={ <p>List reached</p> } />
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getAssignment ).mockResolvedValue( assignment );
} );

describe( 'AssignmentEditorPage', () => {
	it( 'loads the assignment with its settings and read-only placement', async () => {
		renderPage();
		expect(
			await screen.findByRole( 'heading', {
				name: 'Edit assignment: Essay on rivers',
			} )
		).toBeInTheDocument();
		expect( api.getAssignment ).toHaveBeenCalledWith(
			31,
			expect.any( AbortSignal )
		);
		expect( screen.getByRole( 'textbox', { name: 'Title' } ) ).toHaveValue(
			'Essay on rivers'
		);
		expect(
			screen.getByRole( 'textbox', { name: 'Instructions' } )
		).toHaveValue( '<p>Write 500 words.</p>' );
		expect(
			screen.getByRole( 'textbox', { name: 'Maximum score' } )
		).toHaveValue( '50' );
		expect(
			screen.getByRole( 'checkbox', { name: 'Learners submit files' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'link', { name: 'Geography' } )
		).toHaveAttribute( 'href', '/courses/12' );
		expect( screen.getByText( /Rivers/ ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Submissions (4)' } )
		).toHaveAttribute( 'href', '/assignments/31/submissions' );
		expect(
			screen.getByRole( 'button', { name: 'Save assignment' } )
		).toBeDisabled();
	} );
	it( 'saves the edited fields with the loaded version', async () => {
		const saved = { ...assignment, max_score: 80, updated_at: 'later' };
		vi.mocked( api.updateAssignment ).mockResolvedValue( saved );
		const user = userEvent.setup();
		renderPage();
		const max = await screen.findByRole( 'textbox', {
			name: 'Maximum score',
		} );
		await user.clear( max );
		await user.type( max, '80' );
		await user.click(
			screen.getByRole( 'checkbox', { name: 'Set a deadline' } )
		);
		expect( screen.getByText( 'You have unsaved changes.' ) ).toBeVisible();
		await user.click(
			screen.getByRole( 'button', { name: 'Save assignment' } )
		);
		expect(
			await screen.findByText( 'Assignment saved.', notice )
		).toBeInTheDocument();
		expect( api.updateAssignment ).toHaveBeenCalledWith(
			31,
			expect.objectContaining( {
				max_score: 80,
				time_limit_enabled: true,
				base_modified: '2026-10-30T09:20:00Z',
			} ),
			expect.any( AbortSignal )
		);
		expect(
			screen.queryByText( 'You have unsaved changes.' )
		).not.toBeInTheDocument();
	} );
	it( 'blocks invalid fields before saving', async () => {
		const user = userEvent.setup();
		renderPage();
		const pass = await screen.findByRole( 'textbox', {
			name: 'Passing score',
		} );
		await user.clear( pass );
		await user.type( pass, '60' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save assignment' } )
		);
		expect(
			screen.getByText(
				'The passing score cannot be higher than the maximum score.'
			)
		).toBeInTheDocument();
		expect( pass ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( api.updateAssignment ).not.toHaveBeenCalled();
	} );
	it( 'keeps edits on a stale-save conflict and offers a reload', async () => {
		vi.mocked( api.updateAssignment ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_assignment_conflict',
				message:
					'This assignment was changed by someone else. Reload it before saving.',
			} )
		);
		const user = userEvent.setup();
		renderPage();
		const title = await screen.findByRole( 'textbox', { name: 'Title' } );
		await user.type( title, ' (v2)' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save assignment' } )
		);
		expect(
			await screen.findByText( /changed by someone else/, notice )
		).toBeInTheDocument();
		expect( title ).toHaveValue( 'Essay on rivers (v2)' );
		await user.click(
			screen.getByRole( 'button', { name: 'Reload the latest version' } )
		);
		expect( api.getAssignment ).toHaveBeenCalledTimes( 2 );
		expect(
			await screen.findByRole( 'textbox', { name: 'Title' } )
		).toHaveValue( 'Essay on rivers' );
	} );
	it( 'asks before leaving with unsaved changes', async () => {
		const user = userEvent.setup();
		renderPage();
		await user.type(
			await screen.findByRole( 'textbox', { name: 'Title' } ),
			'!'
		);
		await user.click(
			screen.getByRole( 'link', { name: 'Back to assignments' } )
		);
		const dialog = screen.getByRole( 'dialog', {
			name: 'Discard unsaved changes?',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Discard changes' } )
		);
		expect( await screen.findByText( 'List reached' ) ).toBeInTheDocument();
	} );
	it( 'reports load failures and invalid links', async () => {
		vi.mocked( api.getAssignment ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_assignment_cannot_view',
				message: 'Sorry, you are not allowed to view this assignment.',
			} )
		);
		renderPage();
		expect(
			await screen.findByText(
				/not allowed to view this assignment/,
				notice
			)
		).toBeInTheDocument();
		renderPage( '/assignments/abc' );
		expect(
			screen.getByText( 'This assignment link is invalid.', notice )
		).toBeInTheDocument();
	} );
} );
