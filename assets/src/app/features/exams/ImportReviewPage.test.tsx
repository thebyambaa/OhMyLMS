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
import { job, source, triangle } from './fixtures';
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
	proposeFigure: vi.fn(),
	decideProposal: vi.fn(),
	getSource: vi.fn(),
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
	vi.mocked( api.getSource ).mockResolvedValue( source );
} );

describe( 'ImportReviewPage', () => {
	it( 'reviews the JSXGraph proposal of a saved figure and adopts the result', async () => {
		const asset = '9f0a7c52-6c3e-4a4e-9a55-1f4e2b2c8d11';
		const placed = {
			id: 'f1',
			page: 1,
			bounds: [ 0, 0, 1, 1 ],
			part_ids: [],
			asset_id: asset,
			alt: 'Triangle ABC',
			not_to_scale: false,
			selected: 'original' as const,
			approved: false,
			proposal: {
				status: 'proposed' as const,
				origin: 'authored' as const,
				display: 'replace' as const,
				spec: triangle,
			},
		};
		const stored = {
			...job,
			can_reconstruct: true,
			rows: [
				{ ...job.rows[ 0 ]!, figures: [ placed ] },
				job.rows[ 1 ]!,
			],
		};
		vi.mocked( api.getJob ).mockResolvedValue( stored );
		vi.mocked( api.listFigures ).mockResolvedValue( [
			{
				id: asset,
				state: 'ready',
				attempts: 1,
				error: '',
				page: 1,
				bounds: [ 0, 0, 1, 1 ],
				width: 10,
				height: 10,
				url: 'https://example.test/crop.png',
			},
		] );
		vi.mocked( api.decideProposal ).mockResolvedValue( {
			...stored,
			revision: 4,
			rows: [
				{
					...stored.rows[ 0 ]!,
					figures: [
						{
							...placed,
							selected: 'jsxgraph',
							spec: triangle,
							proposal: {
								...placed.proposal,
								status: 'accepted',
							},
						},
					],
				},
				job.rows[ 1 ]!,
			],
		} );
		const user = userEvent.setup();
		renderPage();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Accept instead of the original',
			} )
		);
		expect(
			await screen.findByText(
				'Reconstruction accepted. Approve the figure again.',
				notice
			)
		).toBeInTheDocument();
		expect( api.decideProposal ).toHaveBeenCalledWith(
			7,
			0,
			'f1',
			3,
			'replace',
			expect.any( AbortSignal )
		);
		expect(
			screen.getByText(
				'Learners see the accepted JSXGraph reconstruction.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', {
				name: 'Regenerate with the exam converter',
			} )
		).toBeInTheDocument();
	} );
	it( 'shows the source page beside the review and adds a crop drawn on it to the row', async () => {
		const asset = '1b6f0c1e-2a3b-4c5d-8e9f-0a1b2c3d4e5f';
		const queued = {
			id: asset,
			state: 'queued' as const,
			attempts: 0,
			error: '',
			page: 2,
			bounds: [ 0.1, 0.1, 0.9, 0.5 ],
			width: 0,
			height: 0,
			url: '',
		};
		vi.mocked( api.listFigures )
			.mockResolvedValueOnce( [] )
			.mockResolvedValue( [
				{
					...queued,
					state: 'ready',
					attempts: 1,
					width: 1920,
					height: 480,
					url: 'https://example.test/crop.png',
				},
			] );
		vi.mocked( api.createFigure ).mockResolvedValue( queued );
		vi.mocked( api.updateRow ).mockResolvedValue( job );
		const user = userEvent.setup();
		renderPage();
		expect(
			await screen.findByRole( 'heading', {
				name: 'Source: Question 1, page 1',
			} )
		).toBeInTheDocument();
		expect( api.getSource ).toHaveBeenCalledWith(
			7,
			expect.any( AbortSignal )
		);
		const second = screen.getByRole( 'article', { name: /Question 2/ } );
		await user.click(
			within( second ).getByRole( 'button', {
				name: 'Show source page 2',
			} )
		);
		expect(
			screen.getByRole( 'img', { name: 'Page 2 of the source paper' } )
		).toHaveAttribute( 'src', expect.stringContaining( '/pages/2?' ) );
		expect( second ).toHaveClass( 'is-source-focus' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Add as a new figure of Question 2',
			} )
		);
		expect(
			await screen.findByText( /Crop requested as a new figure/, notice )
		).toBeInTheDocument();
		expect( api.createFigure ).toHaveBeenCalledWith(
			7,
			2,
			[ 0.1, 0.1, 0.9, 0.5 ],
			expect.any( AbortSignal )
		);
		// The queued crop is polled until it is ready.
		await waitFor( () =>
			expect(
				second.querySelector(
					'img[src="https://example.test/crop.png"]'
				)
			).not.toBeNull()
		);
		await user.click(
			within( second ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( api.updateRow ).toHaveBeenCalledWith(
				7,
				1,
				{
					figures: [
						{
							id: 'f1',
							page: 2,
							bounds: [ 0.1, 0.1, 0.9, 0.5 ],
							asset_id: asset,
							part_ids: [],
							alt: '',
							not_to_scale: false,
							selected: 'original',
							approved: false,
						},
					],
				},
				3,
				expect.any( AbortSignal )
			)
		);
	} );
	it( "replaces a figure's crop from the page", async () => {
		const before = '9f0a7c52-6c3e-4a4e-9a55-1f4e2b2c8d11';
		const after = '2c7d1e0f-3b4a-4d5e-9f60-718293a4b5c6';
		const placed = {
			id: 'f1',
			page: 1,
			bounds: [ 0.2, 0.2, 0.6, 0.4 ],
			part_ids: [],
			asset_id: before,
			alt: 'Triangle ABC',
			not_to_scale: false,
			selected: 'original' as const,
			approved: true,
		};
		vi.mocked( api.getJob ).mockResolvedValue( {
			...job,
			rows: [
				{ ...job.rows[ 0 ]!, figures: [ placed ] },
				job.rows[ 1 ]!,
			],
		} );
		vi.mocked( api.createFigure ).mockResolvedValue( {
			id: after,
			state: 'ready',
			attempts: 1,
			error: '',
			page: 1,
			bounds: [ 0.21, 0.2, 0.61, 0.4 ],
			width: 960,
			height: 480,
			url: 'https://example.test/new.png',
		} );
		vi.mocked( api.updateRow ).mockResolvedValue( job );
		const user = userEvent.setup();
		renderPage();
		const first = await screen.findByRole( 'article', {
			name: /Question 1/,
		} );
		await user.click(
			within( first ).getByRole( 'button', {
				name: 'Adjust the crop on the page',
			} )
		);
		expect(
			screen.getByRole( 'heading', {
				name: 'Source: Question 1, figure f1, page 1',
			} )
		).toBeInTheDocument();
		const crop = screen.getByRole( 'button', { name: /^Crop area/ } );
		expect( crop ).toHaveAccessibleName(
			'Crop area: left 20%, top 20%, right 60%, bottom 40%'
		);
		crop.focus();
		await user.keyboard( '{ArrowRight}' );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Use this area for figure f1',
			} )
		);
		expect(
			await screen.findByText( /Crop requested for figure f1/, notice )
		).toBeInTheDocument();
		expect( api.createFigure ).toHaveBeenCalledWith(
			7,
			1,
			[ 0.21, 0.2, 0.61, 0.4 ],
			expect.any( AbortSignal )
		);
		await user.click(
			within( first ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( api.updateRow ).toHaveBeenCalledWith(
				7,
				0,
				{
					figures: [
						{
							...placed,
							asset_id: after,
							bounds: [ 0.21, 0.2, 0.61, 0.4 ],
							approved: false,
						},
					],
				},
				3,
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'gives a figure the page and area of the crop chosen for it, and offers a redraw when the server refuses it', async () => {
		const before = '9f0a7c52-6c3e-4a4e-9a55-1f4e2b2c8d11';
		const other = '2c7d1e0f-3b4a-4d5e-9f60-718293a4b5c6';
		const placed = {
			id: 'f1',
			page: 1,
			bounds: [ 0.2, 0.2, 0.6, 0.4 ],
			part_ids: [],
			asset_id: before,
			alt: 'Triangle ABC',
			not_to_scale: false,
			selected: 'original' as const,
			approved: true,
		};
		const ready = {
			state: 'ready' as const,
			attempts: 1,
			error: '',
			width: 960,
			height: 480,
			url: 'https://example.test/crop.png',
		};
		vi.mocked( api.getJob ).mockResolvedValue( {
			...job,
			rows: [
				{ ...job.rows[ 0 ]!, figures: [ placed ] },
				job.rows[ 1 ]!,
			],
		} );
		vi.mocked( api.listFigures ).mockResolvedValue( [
			{ ...ready, id: before, page: 1, bounds: placed.bounds },
			{ ...ready, id: other, page: 2, bounds: [ 0.1, 0.5, 0.7, 0.9 ] },
		] );
		vi.mocked( api.updateRow ).mockRejectedValue(
			new ApiError( {
				status: 400,
				code: 'ohmylms_exam_import_figure_bounds',
				message:
					'Figure f1 does not match its crop: a figure keeps the page and area of the crop it shows.',
				details: {
					status: 400,
					reason: 'mismatch',
					index: 0,
					figure: 'f1',
				},
			} )
		);
		const user = userEvent.setup();
		renderPage();
		const first = await screen.findByRole( 'article', {
			name: /Question 1/,
		} );
		const select = within( first ).getByRole( 'combobox', {
			name: 'Crop',
		} );
		await waitFor( () =>
			expect(
				within( select ).getByRole( 'option', { name: 'Page 2 crop' } )
			).toBeInTheDocument()
		);
		await user.selectOptions( select, other );
		await user.click(
			within( first ).getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( api.updateRow ).toHaveBeenCalledWith(
				7,
				0,
				{
					figures: [
						{
							...placed,
							asset_id: other,
							page: 2,
							bounds: [ 0.1, 0.5, 0.7, 0.9 ],
							approved: false,
						},
					],
				},
				3,
				expect.any( AbortSignal )
			)
		);
		expect(
			await screen.findByText( /does not match its crop/, notice )
		).toBeInTheDocument();
		await user.click(
			screen.getByRole( 'button', {
				name: 'Redraw figure f1 on the page',
			} )
		);
		expect(
			screen.getByRole( 'heading', {
				name: 'Source: Question 1, figure f1, page 1',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /does not match its crop/, notice )
		).toBeNull();
	} );
	it( 'refuses a typed crop past the last page or below the smallest size', async () => {
		const user = userEvent.setup();
		renderPage();
		const form = await screen.findByRole( 'group', {
			name: 'Request a crop',
		} );
		const field = ( name: string ) =>
			within( form ).getByRole( 'textbox', { name } );
		await user.clear( field( 'Page' ) );
		await user.type( field( 'Page' ), '9' );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Request crop' } )
		);
		expect(
			await screen.findByText( /Choose a page from 1 to 6/, notice )
		).toBeInTheDocument();
		await user.clear( field( 'Page' ) );
		await user.type( field( 'Page' ), '2' );
		await user.clear( field( 'Left (%)' ) );
		await user.type( field( 'Left (%)' ), '99.8' );
		await user.click(
			within( form ).getByRole( 'button', { name: 'Request crop' } )
		);
		expect(
			await screen.findByText( /at least half a percent/, notice )
		).toBeInTheDocument();
		expect( api.createFigure ).not.toHaveBeenCalled();
	} );
	it( 'shows the source page and renderer guidance before extraction', async () => {
		vi.mocked( api.getJob ).mockResolvedValue( {
			...job,
			state: 'ready_for_extraction',
			rows: [],
			row_count: 0,
		} );
		vi.mocked( api.getSource ).mockResolvedValue( {
			...source,
			page_count: 0,
			renderer: {
				...source.renderer,
				available: false,
				engine: '',
				version: '',
				reason: 'process_disabled',
				crops: false,
			},
		} );
		renderPage();
		expect(
			await screen.findByText( /proc_open is disabled/, notice )
		).toBeInTheDocument();
		expect( screen.getByTitle( 'Source paper (PDF)' ) ).toHaveAttribute(
			'src',
			`${ source.document }#page=1`
		);
	} );
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
