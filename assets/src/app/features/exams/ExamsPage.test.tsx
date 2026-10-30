import {
	configure,
	render,
	screen,
	waitFor,
	within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import ExamsPage from './ExamsPage';
import { exam, job } from './fixtures';

vi.mock( './api', () => ( {
	listExams: vi.fn(),
	listJobs: vi.fn(),
	createExam: vi.fn(),
	startImport: vi.fn(),
	checkRows: vi.fn(),
} ) );

// Rendering many WordPress controls can exceed the 1 s default under a parallel run.
configure( { asyncUtilTimeout: 5000 } );

function renderPage( entry = '/exams' ) {
	return render(
		<MemoryRouter initialEntries={ [ entry ] }>
			<Routes>
				<Route path="/exams" element={ <ExamsPage /> } />
				<Route
					path="/exams/:id"
					element={ <p>Editor route reached</p> }
				/>
				<Route
					path="/exams/imports/:jobId"
					element={ <p>Review route reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listExams ).mockResolvedValue( {
		items: [ exam ],
		total: 1,
		totalPages: 1,
	} );
	vi.mocked( api.listJobs ).mockResolvedValue( {
		items: [ job ],
		total: 1,
		totalPages: 1,
	} );
} );

describe( 'ExamsPage', () => {
	it( 'lists exams and imports with links to the editor and review', async () => {
		renderPage();
		const table = await screen.findByRole( 'table', { name: 'Exams' } );
		expect(
			within( table ).getByRole( 'link', { name: 'Mock paper 1' } )
		).toHaveAttribute( 'href', '/exams/12' );
		expect( within( table ).getByText( 'Draft' ) ).toBeInTheDocument();
		const imports = await screen.findByRole( 'table', { name: 'Imports' } );
		expect(
			within( imports ).getByRole( 'link', { name: 'June paper' } )
		).toHaveAttribute( 'href', '/exams/imports/7' );
		expect(
			within( imports ).getByText( 'Waiting for review' )
		).toBeInTheDocument();
	} );
	it( 'filters by search and status', async () => {
		const user = userEvent.setup();
		renderPage();
		await screen.findByRole( 'table', { name: 'Exams' } );
		await user.type(
			screen.getByRole( 'textbox', { name: 'Search exams' } ),
			'mock{Enter}'
		);
		await user.selectOptions(
			screen.getByRole( 'combobox', { name: 'Status' } ),
			'publish'
		);
		await waitFor( () =>
			expect( api.listExams ).toHaveBeenLastCalledWith(
				1,
				{ search: 'mock', status: 'publish' },
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'shows a permission error', async () => {
		vi.mocked( api.listExams ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_exam_cannot_view',
				message: 'Sorry, you are not allowed to view exams.',
			} )
		);
		renderPage();
		expect(
			await screen.findByText( /not allowed to view exams/, notice )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table', { name: 'Exams' } ) ).toBeNull();
	} );
	it( 'opens New from ?action=new, validates and opens the editor', async () => {
		vi.mocked( api.createExam ).mockResolvedValue( exam );
		const user = userEvent.setup();
		renderPage( '/exams?action=new' );
		const dialog = await screen.findByRole( 'dialog', {
			name: 'New exam',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create exam' } )
		);
		expect( within( dialog ).getByRole( 'alert' ) ).toHaveTextContent(
			'Enter a title of 1 to 190 characters.'
		);
		expect( api.createExam ).not.toHaveBeenCalled();
		await user.type(
			within( dialog ).getByRole( 'textbox', { name: 'Exam title' } ),
			'Mock'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Create exam' } )
		);
		expect(
			await screen.findByText( 'Editor route reached' )
		).toBeInTheDocument();
		expect( api.createExam ).toHaveBeenCalledWith(
			'Mock',
			60,
			expect.any( AbortSignal )
		);
	} );
	it( 'opens Import from ?action=import, checks pasted rows, then imports', async () => {
		vi.mocked( api.checkRows ).mockResolvedValue( {
			dry_run: true,
			counts: { create: 1, error: 1 },
			rows: [
				{ row: 1, status: 'create', errors: [], warnings: [] },
				{
					row: 2,
					status: 'error',
					errors: [ 'Unknown skill.' ],
					warnings: [],
				},
			],
		} );
		vi.mocked( api.startImport ).mockResolvedValue( {
			...job,
			id: 9,
			kind: 'rows',
			state: 'committed',
		} );
		const user = userEvent.setup();
		renderPage( '/exams?action=import' );
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Import an exam',
		} );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Import rows' } )
		);
		expect( within( dialog ).getByRole( 'alert' ) ).toHaveTextContent(
			/Choose a PDF/
		);
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Or paste CSV or JSON rows',
			} ),
			'exam,number'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Check rows' } )
		);
		expect(
			await within( dialog ).findByText( 'Row 2: Unknown skill.' )
		).toBeInTheDocument();
		expect( api.checkRows ).toHaveBeenCalledWith(
			expect.objectContaining( { text: 'exam,number', target: 'exam' } ),
			expect.any( AbortSignal )
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Import rows' } )
		);
		expect(
			await screen.findByText( 'Review route reached' )
		).toBeInTheDocument();
	} );
	it( 'keeps the import dialog open with the server reason', async () => {
		vi.mocked( api.startImport ).mockRejectedValue(
			new ApiError( {
				status: 503,
				code: 'exam_storage',
				message:
					'Configure a writable private import directory outside the web root.',
			} )
		);
		const user = userEvent.setup();
		renderPage();
		await user.click(
			await screen.findByRole( 'button', { name: 'Import exam' } )
		);
		const dialog = screen.getByRole( 'dialog', { name: 'Import an exam' } );
		const file = new File( [ '%PDF-1.4' ], 'june.pdf', {
			type: 'application/pdf',
		} );
		const input = dialog.querySelector(
			'input[type="file"]'
		) as HTMLInputElement;
		await user.upload( input, file );
		expect(
			within( dialog ).getByRole( 'button', { name: 'Paper: june.pdf' } )
		).toBeInTheDocument();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Upload paper' } )
		);
		expect(
			await within( dialog ).findByText(
				/private import directory/,
				notice
			)
		).toBeInTheDocument();
		expect( api.startImport ).toHaveBeenCalledWith(
			expect.objectContaining( { file, target: 'exam' } ),
			expect.any( AbortSignal )
		);
	} );
} );
