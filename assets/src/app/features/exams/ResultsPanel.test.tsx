import {
	configure,
	fireEvent,
	render,
	screen,
	waitFor,
	within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import * as api from './api';
import ResultsPanel from './ResultsPanel';
import { results } from './fixtures';

vi.mock( './api', () => ( {
	getResults: vi.fn(),
	getResultsLog: vi.fn(),
	saveResultsPolicy: vi.fn(),
	releaseResults: vi.fn(),
	revokeResults: vi.fn(),
} ) );

configure( { asyncUtilTimeout: 5000 } );

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.getResults ).mockResolvedValue( {
		view: results,
		totalPages: 1,
	} );
} );

function row( name: string ) {
	return screen.getByRole( 'row', { name: new RegExp( name ) } );
}

describe( 'ResultsPanel', () => {
	it( 'lists attempts by release state with their actions', async () => {
		render( <ResultsPanel examId={ 12 } canRelease /> );
		expect(
			await screen.findByText(
				'1 pending release · 0 awaiting marking · 2 released · 1 seen · 0 still writing'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'combobox', { name: 'Show results' } )
		).toHaveValue( 'manual' );
		expect(
			within( row( 'Learner 1' ) ).getByText( 'Results pending' )
		).toBeInTheDocument();
		expect(
			within( row( 'Learner 1' ) ).getByRole( 'button', {
				name: 'Release',
			} )
		).toBeInTheDocument();
		expect(
			within( row( 'Learner 2' ) ).getByRole( 'button', {
				name: 'Undo release',
			} )
		).toBeInTheDocument();
		// Seen results cannot be released again or undone.
		expect(
			within( row( 'Learner 3' ) ).queryAllByRole( 'button' )
		).toEqual( [] );
		expect( api.getResults ).toHaveBeenCalledWith(
			12,
			1,
			expect.any( AbortSignal )
		);
	} );
	it( 'releases one attempt with an email and reports skipped ones', async () => {
		vi.mocked( api.releaseResults ).mockResolvedValue( {
			released: [ 31 ],
			skipped: [ { id: 34, reason: 'awaiting_marking' } ],
			notified: 1,
			remaining: 0,
			counts: results.counts,
		} );
		const user = userEvent.setup();
		render( <ResultsPanel examId={ 12 } canRelease /> );
		await screen.findByRole( 'table' );
		await user.click(
			within( row( 'Learner 1' ) ).getByRole( 'button', {
				name: 'Release',
			} )
		);
		expect(
			await screen.findByText(
				'Released 1 attempts. Emailed 1 learners. Not released: attempt 34: awaiting marking.',
				notice
			)
		).toBeInTheDocument();
		expect( api.releaseResults ).toHaveBeenCalledWith(
			12,
			[ 31 ],
			true,
			expect.any( AbortSignal )
		);
		await waitFor( () =>
			expect( api.getResults ).toHaveBeenCalledTimes( 2 )
		);
	} );
	it( 'releases every pending attempt without email when asked', async () => {
		vi.mocked( api.releaseResults ).mockResolvedValue( {
			released: [ 31 ],
			skipped: [],
			notified: 0,
			remaining: 0,
			counts: results.counts,
		} );
		const user = userEvent.setup();
		render( <ResultsPanel examId={ 12 } canRelease /> );
		await user.click(
			await screen.findByRole( 'checkbox', {
				name: 'Email learners when their results are released',
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Release all pending (1)' } )
		);
		await waitFor( () =>
			expect( api.releaseResults ).toHaveBeenCalledWith(
				12,
				'all',
				false,
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'undoes selected releases and explains refusals', async () => {
		vi.mocked( api.revokeResults ).mockResolvedValue( {
			revoked: [],
			skipped: [ { id: 32, reason: 'viewed' } ],
			counts: results.counts,
		} );
		const user = userEvent.setup();
		render( <ResultsPanel examId={ 12 } canRelease /> );
		await user.click(
			await screen.findByRole( 'checkbox', { name: 'Select Learner 2' } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Undo selected (1)' } )
		);
		expect(
			await screen.findByText(
				'Undid 0 releases. Not undone: attempt 32: the learner has already seen the results.',
				notice
			)
		).toBeInTheDocument();
		expect( api.revokeResults ).toHaveBeenCalledWith(
			12,
			[ 32 ],
			expect.any( AbortSignal )
		);
	} );
	it( 'needs a date for a scheduled release, then saves it in UTC', async () => {
		vi.mocked( api.saveResultsPolicy ).mockResolvedValue( {
			...results,
			policy: { mode: 'scheduled', release_at: '2026-12-01T09:00:00Z' },
		} );
		const user = userEvent.setup();
		render( <ResultsPanel examId={ 12 } canRelease /> );
		await user.selectOptions(
			await screen.findByRole( 'combobox', { name: 'Show results' } ),
			'scheduled'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save results policy' } )
		);
		expect(
			screen.getByText( 'Enter the release date and time (UTC).' )
		).toBeInTheDocument();
		expect( api.saveResultsPolicy ).not.toHaveBeenCalled();
		fireEvent.change( screen.getByLabelText( 'Release on (UTC)' ), {
			target: { value: '2026-12-01T09:00' },
		} );
		await user.click(
			screen.getByRole( 'button', { name: 'Save results policy' } )
		);
		expect(
			await screen.findByText( 'Results policy saved.', notice )
		).toBeInTheDocument();
		expect( api.saveResultsPolicy ).toHaveBeenCalledWith(
			12,
			'scheduled',
			'2026-12-01T09:00:00Z',
			expect.any( AbortSignal )
		);
	} );
	it( 'is read only without the release capability', async () => {
		render( <ResultsPanel examId={ 12 } canRelease={ false } /> );
		expect(
			await screen.findByRole( 'combobox', { name: 'Show results' } )
		).toBeDisabled();
		expect(
			screen.queryByRole( 'button', { name: /Release all pending/ } )
		).toBeNull();
		expect(
			within( row( 'Learner 1' ) ).getByRole( 'button', {
				name: 'Release',
			} )
		).toBeDisabled();
	} );
	it( 'shows the release log on request', async () => {
		vi.mocked( api.getResultsLog ).mockResolvedValue( {
			items: [
				{
					id: 5,
					attempt_id: 31,
					action: 'view',
					detail: '',
					actor: { id: 200, name: 'Learner 1' },
					at: '2026-10-31T11:00:00Z',
				},
			],
			total: 1,
			totalPages: 1,
		} );
		const user = userEvent.setup();
		render( <ResultsPanel examId={ 12 } canRelease /> );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Show the release log',
			} )
		);
		const log = await screen.findByRole( 'list', { name: 'Release log' } );
		expect(
			within( log ).getByText( /Learner saw the results · attempt 31/ )
		).toBeInTheDocument();
	} );
	it( 'offers a retry when the results cannot be loaded', async () => {
		vi.mocked( api.getResults ).mockRejectedValue( new Error( 'Down' ) );
		render( <ResultsPanel examId={ 12 } canRelease /> );
		expect(
			await screen.findByRole( 'button', { name: 'Try again' } )
		).toBeInTheDocument();
	} );
} );
