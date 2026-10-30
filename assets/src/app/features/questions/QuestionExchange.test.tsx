import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import * as exchange from './exchange';
import { summary, types } from './fixtures';
import QuestionsPage from './QuestionsPage';

vi.mock( './api', () => ( {
	listQuestions: vi.fn(),
	listQuestionTypes: vi.fn(),
	archiveQuestion: vi.fn(),
	restoreQuestion: vi.fn(),
	duplicateQuestion: vi.fn(),
} ) );
vi.mock( './exchange', async ( original ) => ( {
	...( await original< typeof import( './exchange' ) >() ),
	checkImport: vi.fn(),
	applyImport: vi.fn(),
	exportQuestions: vi.fn(),
	saveFile: vi.fn(),
} ) );

const notice = { selector: '.components-notice__content' };
const csv =
	'exam,type,question,answers,correct,skills\nX,true-false,Sky?,,true,#1';
const checked: exchange.ImportReport = {
	dry_run: true,
	committed: false,
	approved: 0,
	counts: { create: 1, update: 0, unchanged: 0, error: 1 },
	rows: [
		{
			row: 1,
			key: 'x',
			status: 'create',
			id: 0,
			errors: [],
			warnings: [],
		},
		{
			row: 2,
			key: 'y',
			status: 'error',
			id: 0,
			errors: [ 'Add at least one skill.' ],
			warnings: [],
		},
	],
	review: 'd'.repeat( 64 ),
	stopped: null,
};

function Search() {
	return <p data-testid="search">{ useLocation().search }</p>;
}

function renderPage( route = '/content-hub/question-bank' ) {
	return render(
		<MemoryRouter initialEntries={ [ route ] }>
			<Routes>
				<Route
					path="/content-hub/question-bank"
					element={
						<>
							<QuestionsPage />
							<Search />
						</>
					}
				/>
			</Routes>
		</MemoryRouter>
	);
}

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listQuestions ).mockResolvedValue( {
		items: [ summary ],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.listQuestionTypes ).mockResolvedValue( types );
} );

describe( 'question bank deep links', () => {
	it( 'opens the new question dialog from ?action=new and drops the parameter', async () => {
		renderPage( '/content-hub/question-bank?action=new' );
		expect(
			await screen.findByRole( 'dialog', { name: 'New question' } )
		).toBeVisible();
		await waitFor( () =>
			expect( screen.getByTestId( 'search' ) ).toHaveTextContent( '' )
		);
	} );

	it( 'opens the import dialog from ?action=import', async () => {
		renderPage( '/content-hub/question-bank?action=import' );
		expect(
			await screen.findByRole( 'dialog', { name: 'Import questions' } )
		).toBeVisible();
	} );
} );

describe( 'question import', () => {
	it( 'checks first, then imports the checked rows with the review token', async () => {
		const user = userEvent.setup();
		vi.mocked( exchange.checkImport ).mockResolvedValue( checked );
		vi.mocked( exchange.applyImport ).mockResolvedValue( {
			...checked,
			dry_run: false,
			committed: true,
			review: null,
			rows: [ { ...checked.rows[ 0 ]!, id: 900 }, checked.rows[ 1 ]! ],
		} );
		renderPage();
		await user.click(
			await screen.findByRole( 'button', { name: 'Import questions' } )
		);
		const dialog = within(
			screen.getByRole( 'dialog', { name: 'Import questions' } )
		);
		await user.click( dialog.getByLabelText( 'Rows' ) );
		await user.paste( csv );
		await user.click(
			dialog.getByRole( 'button', { name: 'Check file' } )
		);
		expect(
			await dialog.findByRole( 'table', { name: 'Checked rows' } )
		).toBeVisible();
		expect( dialog.getByText( 'Add at least one skill.' ) ).toBeVisible();
		const importButton = dialog.getByRole( 'button', {
			name: 'Import checked rows',
		} );
		expect( importButton ).toBeDisabled();
		await user.click(
			dialog.getByRole( 'checkbox', {
				name: 'Import the valid rows and skip rows with errors',
			} )
		);
		await user.click( importButton );
		await dialog.findByRole( 'table', { name: 'Imported rows' } );
		expect( exchange.applyImport ).toHaveBeenCalledWith(
			csv,
			'auto',
			'd'.repeat( 64 ),
			{ skip_invalid: true, approve: false },
			expect.any( AbortSignal )
		);
		await waitFor( () =>
			expect( api.listQuestions ).toHaveBeenCalledTimes( 2 )
		);
	} );

	it( 'asks for a new check after the rows or the questions changed', async () => {
		const user = userEvent.setup();
		vi.mocked( exchange.checkImport ).mockResolvedValue( {
			...checked,
			counts: { create: 1, update: 0, unchanged: 0, error: 0 },
			rows: [ checked.rows[ 0 ]! ],
		} );
		vi.mocked( exchange.applyImport ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_import_review_stale',
				message: 'Changed.',
			} )
		);
		renderPage( '/content-hub/question-bank?action=import' );
		const dialog = within(
			await screen.findByRole( 'dialog', { name: 'Import questions' } )
		);
		await user.click( dialog.getByLabelText( 'Rows' ) );
		await user.paste( csv );
		await user.click(
			dialog.getByRole( 'button', { name: 'Check file' } )
		);
		await dialog.findByRole( 'table', { name: 'Checked rows' } );
		await user.type( dialog.getByLabelText( 'Rows' ), ' ' );
		expect(
			dialog.getByText(
				'The rows changed after the check. Check them again.',
				notice
			)
		).toBeVisible();
		expect(
			dialog.queryByRole( 'button', { name: 'Import checked rows' } )
		).not.toBeInTheDocument();
		await user.click(
			dialog.getByRole( 'button', { name: 'Check file' } )
		);
		await user.click(
			await dialog.findByRole( 'button', { name: 'Import checked rows' } )
		);
		expect(
			await dialog.findByText( /changed since the check/, notice )
		).toBeVisible();
	} );
} );

describe( 'question export', () => {
	const file: exchange.ExportFile = {
		filename: 'questions.csv',
		mime: 'text/csv',
		content: 'exam\nX',
		count: 1,
		skipped: 0,
		warnings: [],
	};

	it( 'exports selected questions and downloads the file', async () => {
		const user = userEvent.setup();
		vi.mocked( exchange.exportQuestions ).mockResolvedValue( file );
		renderPage();
		const selectedCsv = await screen.findByRole( 'button', {
			name: 'Export 0 selected (CSV)',
		} );
		expect( selectedCsv ).toBeDisabled();
		await user.click(
			screen.getByRole( 'checkbox', { name: `Select ${ summary.name }` } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Export 1 selected (CSV)' } )
		);
		await screen.findByText( 'Exported 1 question.', notice );
		expect( exchange.exportQuestions ).toHaveBeenCalledWith(
			{ ids: [ summary.id ] },
			'csv',
			expect.any( AbortSignal )
		);
		expect( exchange.saveFile ).toHaveBeenCalledWith( file );
	} );

	it( 'exports every question matching the current filters', async () => {
		const user = userEvent.setup();
		vi.mocked( exchange.exportQuestions ).mockResolvedValue( {
			...file,
			skipped: 2,
		} );
		renderPage();
		await user.type(
			await screen.findByRole( 'textbox', { name: 'Search questions' } ),
			'algebra'
		);
		await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
		await user.click(
			screen.getByRole( 'button', { name: 'Export all matching (JSON)' } )
		);
		await screen.findByText( /2 you cannot edit were left out/, notice );
		expect( exchange.exportQuestions ).toHaveBeenCalledWith(
			{
				filters: expect.objectContaining( {
					search: 'algebra',
					status: 'active',
				} ),
			},
			'json',
			expect.any( AbortSignal )
		);
	} );
} );
