import '@testing-library/jest-dom/vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { BootstrapProvider } from '../../bootstrap/BootstrapContext';
import { ApiError } from '../../lib/api';
import { createBootstrap } from '../../test/render';
import * as bank from '../questions/public';
import * as api from './api';
import * as exchange from './exchange';
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
vi.mock( './exchange', async ( original ) => ( {
	...( await original< typeof import( './exchange' ) >() ),
	checkQuizFile: vi.fn(),
	importQuizFile: vi.fn(),
	exportQuiz: vi.fn(),
} ) );
vi.mock( '../questions/public', async ( original ) => ( {
	...( await original< typeof import( '../questions/public' ) >() ),
	saveFile: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };
const file = '{"format":"ohmylms.quiz","version":1,"questions":[]}';
const checked: exchange.QuizImportResult = {
	id: null,
	name: 'Algebra',
	renamed: false,
	format: 'ohmylms',
	dry_run: true,
	summary: {
		questions: 3,
		points: 5,
		types: { 'single-choice': 3 },
		randomized: 1,
		media: 0,
	},
	warnings: [ 'Skill fractions does not exist here.' ],
	review: 'e'.repeat( 64 ),
};

function Search() {
	return <p data-testid="search">{ useLocation().search }</p>;
}

function renderPage( route = '/quizzes' ) {
	return render(
		<BootstrapProvider value={ createBootstrap( { edit_posts: true } ) }>
			<MemoryRouter initialEntries={ [ route ] }>
				<Routes>
					<Route
						path="/quizzes"
						element={
							<>
								<QuizzesPage />
								<Search />
							</>
						}
					/>
					<Route
						path="/quizzes/:id"
						element={ <p>Editor route reached</p> }
					/>
				</Routes>
			</MemoryRouter>
		</BootstrapProvider>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listQuizzes ).mockResolvedValue( {
		items: [ summary ],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.listCourses ).mockResolvedValue( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
} );

describe( 'quiz deep links', () => {
	it( 'opens the create dialog from ?action=new and drops the parameter', async () => {
		renderPage( '/quizzes?action=new' );
		expect(
			await screen.findByRole( 'dialog', { name: 'Add quiz' } )
		).toBeVisible();
		await waitFor( () =>
			expect( screen.getByTestId( 'search' ) ).toHaveTextContent( '' )
		);
	} );

	it( 'opens the import dialog from ?action=import', async () => {
		renderPage( '/quizzes?action=import' );
		expect(
			await screen.findByRole( 'dialog', { name: 'Import quiz' } )
		).toBeVisible();
	} );
} );

describe( 'quiz import', () => {
	it( 'checks the file, then imports it as a new draft and opens it', async () => {
		const user = userEvent.setup();
		vi.mocked( exchange.checkQuizFile ).mockResolvedValue( checked );
		vi.mocked( exchange.importQuizFile ).mockResolvedValue( {
			...checked,
			id: 77,
			dry_run: false,
			review: null,
		} );
		renderPage();
		await user.click(
			await screen.findByRole( 'button', { name: 'Import quiz' } )
		);
		const dialog = within(
			screen.getByRole( 'dialog', { name: 'Import quiz' } )
		);
		const importButton = dialog.getByRole( 'button', {
			name: 'Import as a new draft quiz',
		} );
		expect( importButton ).toBeDisabled();
		await user.click( dialog.getByLabelText( 'Or paste the file' ) );
		await user.paste( file );
		await user.click(
			dialog.getByRole( 'button', { name: 'Check file' } )
		);
		expect(
			await dialog.findByText( /Algebra: 3 questions, 5 points/ )
		).toBeVisible();
		expect(
			dialog.getByText( 'Skill fractions does not exist here.' )
		).toBeVisible();
		await user.click( importButton );
		expect(
			await screen.findByText( 'Editor route reached' )
		).toBeVisible();
		expect( exchange.importQuizFile ).toHaveBeenCalledWith(
			file,
			'',
			'e'.repeat( 64 )
		);
	} );

	it( 'lists every problem of a refused file and needs a new check after edits', async () => {
		const user = userEvent.setup();
		vi.mocked( exchange.checkQuizFile )
			.mockRejectedValueOnce(
				new ApiError( {
					status: 400,
					code: 'ohmylms_quiz_import_invalid',
					message: 'The file has problems.',
					details: {
						status: 400,
						errors: [
							{
								question: 2,
								message: 'Mark exactly one option as correct.',
							},
							{ question: 0, message: 'Unknown version.' },
						],
					},
				} )
			)
			.mockResolvedValueOnce( checked );
		renderPage( '/quizzes?action=import' );
		const dialog = within(
			await screen.findByRole( 'dialog', { name: 'Import quiz' } )
		);
		await user.click( dialog.getByLabelText( 'Or paste the file' ) );
		await user.paste( file );
		await user.click(
			dialog.getByRole( 'button', { name: 'Check file' } )
		);
		expect(
			await dialog.findByText(
				'Question 2: Mark exactly one option as correct.'
			)
		).toBeVisible();
		expect( dialog.getByText( 'Unknown version.' ) ).toBeVisible();
		await user.click(
			dialog.getByRole( 'button', { name: 'Check file' } )
		);
		await dialog.findByText( /Algebra: 3 questions/ );
		await user.type( dialog.getByLabelText( 'Quiz name (optional)' ), 'X' );
		expect(
			dialog.getByText(
				'The file or name changed after the check. Check it again.',
				notice
			)
		).toBeVisible();
		expect(
			dialog.getByRole( 'button', { name: 'Import as a new draft quiz' } )
		).toBeDisabled();
	} );
} );

describe( 'quiz export', () => {
	it( 'downloads the quiz file from the row action', async () => {
		const user = userEvent.setup();
		const quizFile = {
			filename: 'fractions.ohmylms.json',
			mime: 'application/json',
			content: '{}',
			warnings: [],
		};
		vi.mocked( exchange.exportQuiz ).mockResolvedValue( quizFile );
		renderPage();
		await user.click(
			await screen.findByRole( 'button', {
				name: `Export ${ summary.title }`,
			} )
		);
		await screen.findByText( 'Quiz exported.', notice );
		expect( exchange.exportQuiz ).toHaveBeenCalledWith(
			summary.id,
			expect.any( AbortSignal )
		);
		expect( bank.saveFile ).toHaveBeenCalledWith( quizFile );
	} );
} );
