import '@testing-library/jest-dom/vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { BootstrapProvider } from '../../bootstrap/BootstrapContext';
import { ApiError } from '../../lib/api';
import { createBootstrap } from '../../test/render';
import * as api from './api';
import { summary } from './fixtures';
import QuizzesPage from './QuizzesPage';

vi.mock( './api', () => ( {
	listQuizzes: vi.fn(),
	listCourses: vi.fn(),
	createQuiz: vi.fn(),
	trashQuiz: vi.fn(),
	restoreQuiz: vi.fn(),
	deleteQuiz: vi.fn(),
} ) );

const second = {
	...summary,
	id: 12,
	title: 'Trashed quiz',
	status: 'trash' as const,
	course: null,
	pending_review_count: 0,
	native_editor_url: null,
};

function renderPage() {
	return render(
		<BootstrapProvider value={ createBootstrap( { edit_posts: true } ) }>
			<MemoryRouter initialEntries={ [ '/quizzes' ] }>
				<Routes>
					<Route path="/quizzes" element={ <QuizzesPage /> } />
					<Route
						path="/quizzes/:id"
						element={ <p>Editor route reached</p> }
					/>
				</Routes>
			</MemoryRouter>
		</BootstrapProvider>
	);
}

const notice = { selector: '.components-notice__content' };
const rejection = ( status: number, code: string, message: string ) =>
	new ApiError( { status, code, message } );

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listQuizzes ).mockResolvedValue( {
		items: [ summary, second ],
		total: 2,
		totalPages: 1,
	} );
	vi.mocked( api.listCourses ).mockResolvedValue( {
		items: [ { id: 5, title: 'Maths 101', status: 'publish' } ],
		total: 1,
		totalPages: 1,
	} );
} );

describe( 'QuizzesPage', () => {
	it( 'lists quizzes with course, counts and links', async () => {
		renderPage();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading quizzes'
		);
		const table = await screen.findByRole( 'table', { name: 'Quizzes' } );
		const rows = within( table ).getAllByRole( 'row' );
		expect( rows ).toHaveLength( 4 );
		const first = within( rows[ 1 ]! );
		expect(
			first.getByRole( 'link', { name: 'Fractions check' } )
		).toHaveAttribute( 'href', '/quizzes/11' );
		expect( first.getByText( 'Maths 101' ) ).toBeInTheDocument();
		expect( first.getByText( /3\s+\(1 to grade\)/ ) ).toBeInTheDocument();
		expect(
			first.getByRole( 'link', { name: 'Attempts for Fractions check' } )
		).toHaveAttribute( 'href', '/quizzes/11/attempts' );
		expect(
			first.getByRole( 'link', {
				name: 'Edit questions of Fractions check',
			} )
		).toHaveAttribute( 'href', '/quizzes/11' );
		const trashed = within( rows[ 2 ]! );
		expect(
			trashed.getByRole( 'button', { name: 'Restore Trashed quiz' } )
		).toBeInTheDocument();
		expect( screen.getAllByText( '2 quizzes found.' ) ).toHaveLength( 2 );
	} );
	it( 'hides actions the user may not use', async () => {
		vi.mocked( api.listQuizzes ).mockResolvedValue( {
			items: [ { ...summary, can_edit: false, can_delete: false } ],
			total: 1,
			totalPages: 1,
		} );
		renderPage();
		const table = await screen.findByRole( 'table' );
		expect(
			within( table ).queryByRole( 'link', { name: 'Fractions check' } )
		).toBeNull();
		expect(
			within( table ).queryByRole( 'button', { name: /Trash/ } )
		).toBeNull();
		expect(
			within( table ).getByRole( 'link', { name: /Attempts for/ } )
		).toBeInTheDocument();
	} );
	it( 'shows an empty state', async () => {
		vi.mocked( api.listQuizzes ).mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		renderPage();
		expect(
			await screen.findByText( 'No quizzes found.' )
		).toBeInTheDocument();
	} );
	it( 'shows a server error with a retry that reloads', async () => {
		vi.mocked( api.listQuizzes ).mockRejectedValueOnce(
			rejection( 500, 'rest_error', 'The server broke.' )
		);
		const user = userEvent.setup();
		renderPage();
		expect(
			await screen.findByText( /The server broke\./, notice )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		expect(
			await screen.findByRole( 'table', { name: 'Quizzes' } )
		).toBeInTheDocument();
	} );
	it( 'shows a permission error', async () => {
		vi.mocked( api.listQuizzes ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_quiz_cannot_view',
				'Sorry, you are not allowed to view quizzes.'
			)
		);
		renderPage();
		expect(
			await screen.findByText( /not allowed to view quizzes/, notice )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );
	it( 'searches by keyboard, and filters by status and course', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search quizzes' } ),
			'frac{Enter}'
		);
		await waitFor( () =>
			expect( api.listQuizzes ).toHaveBeenLastCalledWith(
				1,
				'frac',
				'all',
				0,
				expect.any( AbortSignal )
			)
		);
		await user.click(
			await screen.findByRole( 'link', { name: 'Trash' } )
		);
		await waitFor( () =>
			expect( api.listQuizzes ).toHaveBeenLastCalledWith(
				1,
				'frac',
				'trash',
				0,
				expect.any( AbortSignal )
			)
		);
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Course' } ),
			'Maths 101'
		);
		await waitFor( () =>
			expect( api.listQuizzes ).toHaveBeenLastCalledWith(
				1,
				'frac',
				'trash',
				5,
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'validates the title before creating, then opens the editor', async () => {
		vi.mocked( api.createQuiz ).mockResolvedValue( {
			...summary,
			id: 77,
			description: '',
			settings: {} as never,
			total_marks: 0,
		} );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click( screen.getByRole( 'button', { name: 'Add quiz' } ) );
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Add quiz',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create quiz' } )
		);
		expect( within( dialog ).getByRole( 'alert' ) ).toHaveTextContent(
			/1 to 200 characters/
		);
		expect( api.createQuiz ).not.toHaveBeenCalled();
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: /Quiz title/ } ),
			'Algebra{Enter}'
		);
		expect( api.createQuiz ).toHaveBeenCalledWith(
			'Algebra',
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByText( 'Editor route reached' )
		).toBeInTheDocument();
	} );
	it( 'ignores a second submit while creating', async () => {
		let finish!: () => void;
		vi.mocked( api.createQuiz ).mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					finish = () =>
						resolve( {
							...summary,
							id: 77,
							description: '',
							settings: {} as never,
							total_marks: 0,
						} );
				} )
		);
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click( screen.getByRole( 'button', { name: 'Add quiz' } ) );
		const dialog = await screen.findByRole( 'dialog' );
		await user.type(
			within( dialog ).getByRole( 'textbox' ),
			'Algebra{Enter}{Enter}'
		);
		expect( api.createQuiz ).toHaveBeenCalledTimes( 1 );
		finish();
		await screen.findByText( 'Editor route reached' );
	} );
	it( 'shows a server error inside the create dialog and keeps the title', async () => {
		vi.mocked( api.createQuiz ).mockRejectedValue(
			rejection(
				403,
				'ohmylms_quiz_cannot_create',
				'Not allowed to create.'
			)
		);
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click( screen.getByRole( 'button', { name: 'Add quiz' } ) );
		const dialog = await screen.findByRole( 'dialog' );
		await user.type(
			within( dialog ).getByRole( 'textbox' ),
			'Algebra{Enter}'
		);
		expect(
			await within( dialog ).findByText( 'Not allowed to create.' )
		).toBeInTheDocument();
		expect( within( dialog ).getByRole( 'textbox' ) ).toHaveValue(
			'Algebra'
		);
	} );
	it( 'confirms before moving a quiz to the trash and reloads', async () => {
		vi.mocked( api.trashQuiz ).mockResolvedValue( undefined );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Move Fractions check to the trash',
			} )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Move quiz to the trash',
		} );
		expect( api.trashQuiz ).not.toHaveBeenCalled();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Move to trash' } )
		);
		expect( api.trashQuiz ).toHaveBeenCalledWith(
			11,
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByText( 'Quiz moved to the trash.', notice )
		).toBeInTheDocument();
		await waitFor( () =>
			expect( api.listQuizzes ).toHaveBeenCalledTimes( 2 )
		);
	} );
	it( 'explains why a permanent delete was refused', async () => {
		vi.mocked( api.deleteQuiz ).mockRejectedValue(
			rejection(
				409,
				'ohmylms_quiz_has_attempts',
				'This quiz has student attempts and cannot be deleted permanently.'
			)
		);
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Delete Trashed quiz permanently',
			} )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Delete quiz' } )
		);
		expect(
			await within( dialog ).findByText( /cannot be deleted permanently/ )
		).toBeInTheDocument();
	} );
	it( 'restores a trashed quiz', async () => {
		vi.mocked( api.restoreQuiz ).mockResolvedValue( {} as never );
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table' );
		await user.click(
			screen.getByRole( 'button', { name: 'Restore Trashed quiz' } )
		);
		expect( api.restoreQuiz ).toHaveBeenCalledWith(
			12,
			expect.any( AbortSignal )
		);
		expect(
			await screen.findByText( 'Quiz restored as a draft.', notice )
		).toBeInTheDocument();
	} );
} );
