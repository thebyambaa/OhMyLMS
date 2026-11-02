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
import ExamEditorPage from './ExamEditorPage';
import { exam, results } from './fixtures';

vi.mock( './api', () => ( {
	getExam: vi.fn(),
	updateExam: vi.fn(),
	saveComposition: vi.fn(),
	searchBank: vi.fn(),
	publishExam: vi.fn(),
	getResults: vi.fn(),
	getResultsLog: vi.fn(),
	saveResultsPolicy: vi.fn(),
	releaseResults: vi.fn(),
	revokeResults: vi.fn(),
} ) );

// Rendering many WordPress controls can exceed the 1 s default under a parallel run.
configure( { asyncUtilTimeout: 5000 } );

function renderPage( entry = '/exams/12' ) {
	return render(
		<MemoryRouter initialEntries={ [ entry ] }>
			<Routes>
				<Route path="/exams/:id" element={ <ExamEditorPage /> } />
				<Route path="/exams" element={ <p>List route reached</p> } />
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getExam ).mockResolvedValue( exam );
	vi.mocked( api.getResults ).mockResolvedValue( {
		view: results,
		totalPages: 1,
	} );
} );

describe( 'ExamEditorPage', () => {
	it( 'rejects an invalid link without a request', () => {
		renderPage( '/exams/abc' );
		expect(
			screen.getByText( 'This exam link is invalid.', notice )
		).toBeInTheDocument();
		expect( api.getExam ).not.toHaveBeenCalled();
	} );
	it( 'saves settings with the revision and adopts the result', async () => {
		vi.mocked( api.updateExam ).mockResolvedValue( {
			...exam,
			title: 'Mock paper 2',
			revision: 'b'.repeat( 40 ),
		} );
		const user = userEvent.setup();
		renderPage();
		const title = await screen.findByRole( 'textbox', { name: 'Title' } );
		await user.clear( title );
		await user.type( title, 'Mock paper 2' );
		expect(
			screen.getByText( 'You have unsaved changes.' )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Save settings' } )
		);
		expect(
			await screen.findByText( 'Exam settings saved.', notice )
		).toBeInTheDocument();
		expect( api.updateExam ).toHaveBeenCalledWith(
			12,
			expect.objectContaining( {
				revision: exam.revision,
				title: 'Mock paper 2',
				duration_minutes: 90,
				header: expect.objectContaining( { board: 'Cambridge' } ),
			} ),
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'heading', { name: 'Edit exam: Mock paper 2' } )
		).toBeInTheDocument();
	} );
	it( 'blocks invalid settings locally', async () => {
		const user = userEvent.setup();
		renderPage();
		const duration = await screen.findByRole( 'textbox', {
			name: 'Duration (minutes)',
		} );
		await user.clear( duration );
		await user.type( duration, '0' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save settings' } )
		);
		expect(
			screen.getByText( 'Enter a duration from 1 to 1440 minutes.' )
		).toBeInTheDocument();
		expect( api.updateExam ).not.toHaveBeenCalled();
	} );
	it( 'offers a reload when the exam changed elsewhere', async () => {
		vi.mocked( api.updateExam ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_exam_conflict',
				message:
					'This exam was changed somewhere else. Reload it before saving.',
			} )
		);
		const user = userEvent.setup();
		renderPage();
		await user.type(
			await screen.findByRole( 'textbox', {
				name: 'Instructions for learners',
			} ),
			' Show working.'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save settings' } )
		);
		expect(
			await screen.findByText( /changed somewhere else/, notice )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Reload the latest version' } )
		);
		await waitFor( () => expect( api.getExam ).toHaveBeenCalledTimes( 2 ) );
	} );
	it( 'reorders, removes, adds sections and bank questions, then saves the composition', async () => {
		vi.mocked( api.searchBank ).mockResolvedValue( {
			items: [
				{
					id: 103,
					name: 'Factorise',
					type: 'structured',
					marks: 4,
					approved: true,
					available: true,
				},
				{
					id: 104,
					name: 'Shared draft',
					type: 'structured',
					marks: 1,
					approved: false,
					available: false,
				},
			],
			total: 2,
			totalPages: 1,
		} );
		vi.mocked( api.saveComposition ).mockImplementation(
			async ( _id, items ) => ( {
				...exam,
				revision: 'c'.repeat( 40 ),
				composition: exam.composition,
				question_count: items.length,
			} )
		);
		const user = userEvent.setup();
		renderPage();
		const list = await screen.findByRole( 'list', { name: 'Composition' } );
		const rows = within( list ).getAllByRole( 'listitem' );
		await user.click(
			within( rows[ 2 ]! ).getByRole( 'button', { name: 'Move up' } )
		);
		await user.click(
			within(
				screen.getByRole( 'list', { name: 'Composition' } )
			).getAllByRole( 'button', { name: 'Remove' } )[ 2 ]!
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Add section' } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Add questions' } )
		);
		const picker = await screen.findByRole( 'dialog', {
			name: 'Add questions from the bank',
		} );
		expect(
			await within( picker ).findByRole( 'checkbox', {
				name: /Shared draft/,
			} )
		).toBeDisabled();
		await user.click(
			within( picker ).getByRole( 'checkbox', { name: /Factorise/ } )
		);
		await user.click(
			within( picker ).getByRole( 'button', { name: 'Add 1 questions' } )
		);
		expect( screen.getByText( 'Total: 7 marks' ) ).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', {
				name: 'Save questions and sections',
			} )
		);
		await waitFor( () =>
			expect( api.saveComposition ).toHaveBeenCalledWith(
				12,
				[
					{ kind: 'section', title: 'Section A', new_page: false },
					{ kind: 'question', question_id: 102 },
					{ kind: 'section', title: 'New section', new_page: false },
					{ kind: 'question', question_id: 103 },
				],
				exam.revision,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText( 'Questions and sections saved.', notice )
		).toBeInTheDocument();
	} );
	it( 'is read only when published', async () => {
		vi.mocked( api.getExam ).mockResolvedValue( {
			...exam,
			status: 'publish',
			permissions: {
				can_edit: false,
				can_compose: false,
				can_publish: false,
				can_release: true,
			},
		} );
		renderPage();
		expect(
			await screen.findByText(
				'This exam is published and can no longer be edited.',
				notice
			)
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Save settings' } )
		).toBeNull();
		expect(
			screen.queryByRole( 'button', { name: 'Add section' } )
		).toBeNull();
		expect(
			screen.getByRole( 'textbox', { name: 'Title' } )
		).toBeDisabled();
	} );
	it( 'publishes after confirming, and asks again for overlap warnings', async () => {
		vi.mocked( api.publishExam )
			.mockRejectedValueOnce(
				new ApiError( {
					status: 409,
					code: 'exam_overlap',
					message:
						'Question 1 is also used in "Practice" (practice set).',
				} )
			)
			.mockResolvedValueOnce( {
				...exam,
				status: 'publish',
				permissions: {
					can_edit: false,
					can_compose: false,
					can_publish: false,
					can_release: true,
				},
			} );
		const user = userEvent.setup();
		renderPage();
		await user.click(
			await screen.findByRole( 'button', { name: 'Publish exam' } )
		);
		const dialog = screen.getByRole( 'dialog', { name: 'Publish exam' } );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Publish' } )
		);
		expect(
			await within( dialog ).findByText( /also used in/ )
		).toBeInTheDocument();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Publish anyway' } )
		);
		expect(
			await screen.findByText( 'Exam published.', notice )
		).toBeInTheDocument();
		expect( api.publishExam ).toHaveBeenNthCalledWith(
			1,
			12,
			exam.revision,
			false,
			expect.any( AbortSignal )
		);
		expect( api.publishExam ).toHaveBeenNthCalledWith(
			2,
			12,
			exam.revision,
			true,
			expect.any( AbortSignal )
		);
	} );
} );
