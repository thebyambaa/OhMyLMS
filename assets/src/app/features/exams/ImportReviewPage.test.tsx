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
import { job } from './fixtures';
import ImportReviewPage from './ImportReviewPage';
import type { ImportJobDetail } from './types';

// Poll immediately so the polling test needs no timers; other fixtures never poll.
vi.mock( './model', async ( original ) => ( {
	...( await original< typeof import( './model' ) >() ),
	POLL_MS: 0,
} ) );

// Rendering many WordPress controls can exceed the 1 s default under a parallel run.
configure( { asyncUtilTimeout: 5000 } );

vi.mock( './api', () => ( {
	getJob: vi.fn(),
	listFigures: vi.fn(),
	updateRow: vi.fn(),
	decideRow: vi.fn(),
	applyJob: vi.fn(),
	extractJob: vi.fn(),
	createFigure: vi.fn(),
	deleteJob: vi.fn(),
	searchSkills: vi.fn(),
} ) );

function renderPage( entry = '/exams/imports/7' ) {
	return render(
		<MemoryRouter initialEntries={ [ entry ] }>
			<Routes>
				<Route
					path="/exams/imports/:jobId"
					element={ <ImportReviewPage /> }
				/>
				<Route
					path="/exams/:id"
					element={ <p>Exam route reached</p> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

function withRows(
	statuses: Array< 'pending' | 'accepted' | 'rejected' >,
	extra: Partial< ImportJobDetail > = {}
): ImportJobDetail {
	return {
		...job,
		rows: job.rows.map( ( row, index ) => ( {
			...row,
			status: statuses[ index ] ?? 'pending',
		} ) ),
		accepted_count: statuses.filter( ( status ) => 'accepted' === status )
			.length,
		rejected_count: statuses.filter( ( status ) => 'rejected' === status )
			.length,
		...extra,
	};
}

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getJob ).mockResolvedValue( job );
	vi.mocked( api.listFigures ).mockResolvedValue( [] );
} );

describe( 'ImportReviewPage', () => {
	it( 'shows rows with parts and private marking notes for the owner', async () => {
		renderPage();
		expect(
			await screen.findByRole( 'heading', {
				name: 'Review import: June paper',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'heading', {
				name: 'Question 1 — Needs review — 2 marks',
			} )
		).toBeInTheDocument();
		expect(
			screen.getAllByRole( 'textbox', {
				name: 'Marking notes (teachers only)',
			} )[ 1 ]
		).toHaveValue( 'M1 A1' );
		expect( screen.getByRole( 'textbox', { name: 'Answer' } ) ).toHaveValue(
			'4'
		);
		expect(
			screen.getByRole( 'button', { name: 'Apply import' } )
		).toBeDisabled();
		// Crops load in an effect after the job renders.
		await waitFor( () =>
			expect( api.listFigures ).toHaveBeenCalledWith(
				7,
				expect.any( AbortSignal )
			)
		);
	} );
	it( "shows the owner-only error for another author's import", async () => {
		vi.mocked( api.getJob ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_exam_import_forbidden',
				message: 'Sorry, you are not allowed to open this import.',
			} )
		);
		renderPage();
		expect(
			await screen.findByText( /not allowed to open this import/, notice )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'button', { name: 'Accept' } ) ).toBeNull();
	} );
	it( 'saves a row edit with the revision, then accepts and rejects', async () => {
		const edited = (
			statuses: Array< 'pending' | 'accepted' | 'rejected' >,
			extra: Partial< ImportJobDetail >
		) => {
			const next = withRows( statuses, extra );
			return {
				...next,
				rows: next.rows.map( ( row ) =>
					0 === row.index
						? { ...row, question: 'Work out 3 + 3.' }
						: row
				),
			};
		};
		vi.mocked( api.updateRow ).mockResolvedValue(
			edited( [ 'pending', 'pending' ], { revision: 4 } )
		);
		vi.mocked( api.decideRow )
			.mockResolvedValueOnce(
				edited( [ 'accepted', 'pending' ], { revision: 5 } )
			)
			.mockResolvedValueOnce(
				edited( [ 'accepted', 'rejected' ], {
					revision: 6,
					can_apply: true,
				} )
			);
		const user = userEvent.setup();
		renderPage();
		const first = await screen.findByRole( 'article', {
			name: /Question 1/,
		} );
		const stem = within( first ).getByRole( 'textbox', {
			name: 'Question stem',
		} );
		await user.clear( stem );
		await user.type( stem, 'Work out 3 + 3.' );
		expect(
			within( first ).getByRole( 'button', { name: 'Accept' } )
		).toBeDisabled();
		await user.click(
			within( first ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( api.updateRow ).toHaveBeenCalledWith(
				7,
				0,
				{ question: 'Work out 3 + 3.' },
				3,
				expect.any( AbortSignal )
			)
		);
		// The Accept button exists while the save is in flight, so wait until it is usable.
		expect(
			await screen.findByText(
				'Question saved. Accept it when it is right.',
				notice
			)
		).toBeInTheDocument();
		const accept = within( first ).getByRole( 'button', {
			name: 'Accept',
		} );
		await waitFor( () => expect( accept ).toBeEnabled() );
		await user.click( accept );
		expect(
			await screen.findByText( 'Question accepted.', notice )
		).toBeInTheDocument();
		expect( api.decideRow ).toHaveBeenLastCalledWith(
			7,
			0,
			'accept',
			4,
			expect.any( AbortSignal )
		);
		const second = screen.getByRole( 'article', { name: /Question 2/ } );
		const reject = within( second ).getByRole( 'button', {
			name: 'Reject',
		} );
		await waitFor( () => expect( reject ).toBeEnabled() );
		await user.click( reject );
		expect(
			await screen.findByRole( 'heading', {
				name: 'Question 2 — Rejected — 2 marks',
			} )
		).toBeInTheDocument();
		expect( api.decideRow ).toHaveBeenLastCalledWith(
			7,
			1,
			'reject',
			5,
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'button', { name: 'Apply import' } )
		).toBeEnabled();
	} );
	it( 'offers a reload after a stale write', async () => {
		vi.mocked( api.decideRow ).mockRejectedValue(
			new ApiError( {
				status: 409,
				code: 'ohmylms_exam_import_conflict',
				message:
					'This review was changed somewhere else. Reload it before saving.',
			} )
		);
		const user = userEvent.setup();
		renderPage();
		await user.click(
			( await screen.findAllByRole( 'button', { name: 'Accept' } ) )[ 0 ]!
		);
		expect(
			await screen.findByText( /changed somewhere else/, notice )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', { name: 'Reload the latest version' } )
		);
		await waitFor( () => expect( api.getJob ).toHaveBeenCalledTimes( 2 ) );
	} );
	it( 'adds a found skill to the row skills', async () => {
		vi.mocked( api.searchSkills ).mockResolvedValue( {
			items: [ { id: 9, name: 'Indices', code: 'C2.4', path: 'Number' } ],
			total: 1,
			totalPages: 1,
		} );
		const user = userEvent.setup();
		renderPage();
		const first = await screen.findByRole( 'article', {
			name: /Question 1/,
		} );
		await user.type(
			within( first ).getByRole( 'textbox', { name: 'Find a skill' } ),
			'indices'
		);
		await user.click(
			within( first ).getByRole( 'button', { name: 'Search skills' } )
		);
		await user.click(
			await within( first ).findByRole( 'button', { name: 'Use' } )
		);
		expect(
			within( first ).getByRole( 'textbox', { name: 'Skills' } )
		).toHaveValue( '#5;#9' );
	} );
	it( 'applies with title and duration and links to the exam', async () => {
		vi.mocked( api.getJob ).mockResolvedValue(
			withRows( [ 'accepted', 'rejected' ], { can_apply: true } )
		);
		vi.mocked( api.applyJob ).mockResolvedValue(
			withRows( [ 'accepted', 'rejected' ], {
				state: 'committed',
				exam_id: 33,
				question_ids: [ 501 ],
				can_apply: false,
			} )
		);
		const user = userEvent.setup();
		renderPage();
		const duration = await screen.findByRole( 'textbox', {
			name: 'Duration (minutes)',
		} );
		await user.clear( duration );
		await user.type( duration, '75' );
		await user.click(
			screen.getByRole( 'button', { name: 'Apply import' } )
		);
		expect(
			await screen.findByText( 'Import applied.', notice )
		).toBeInTheDocument();
		expect( api.applyJob ).toHaveBeenCalledWith(
			7,
			3,
			{ title: 'June paper', duration_minutes: 75 },
			expect.any( AbortSignal )
		);
		await user.click(
			screen.getByRole( 'link', { name: 'Open the exam' } )
		);
		expect(
			await screen.findByText( 'Exam route reached' )
		).toBeInTheDocument();
	} );
	it( 'polls while the server extracts, then stops', async () => {
		vi.mocked( api.getJob )
			.mockResolvedValueOnce( {
				...job,
				state: 'extracting',
				rows: [],
				row_count: 0,
			} )
			.mockResolvedValueOnce( job );
		renderPage();
		expect(
			await screen.findByText( 'Extracting questions', { exact: false } )
		).toBeInTheDocument();
		expect(
			await screen.findByRole( 'article', { name: /Question 1/ } )
		).toBeInTheDocument();
		expect( api.getJob ).toHaveBeenCalledTimes( 2 );
		expect( api.getJob ).toHaveBeenLastCalledWith(
			7,
			expect.any( AbortSignal )
		);
		await new Promise( ( resolve ) => setTimeout( resolve, 20 ) );
		expect( api.getJob ).toHaveBeenCalledTimes( 2 );
	} );
} );
