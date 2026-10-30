import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getAttempt, getQuiz, listAttempts, listQuizzes } from './api';
import { attempt, attemptDetail, quiz, summary } from './fixtures';
import { useAttemptDetail, useAttempts } from './useAttempts';
import { useQuizEditor } from './useQuizEditor';
import { useQuizzes } from './useQuizzes';
import type { Quiz } from './types';

vi.mock( './api', () => ( {
	listQuizzes: vi.fn(),
	getQuiz: vi.fn(),
	listAttempts: vi.fn(),
	getAttempt: vi.fn(),
} ) );

beforeEach( () => vi.resetAllMocks() );

describe( 'useQuizzes', () => {
	it( 'cancels obsolete searches and ignores late responses', async () => {
		let resolveOld!: ( value: Collection< Quiz > ) => void;
		vi.mocked( listQuizzes ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		vi.mocked( listQuizzes ).mockResolvedValue( {
			items: [ { ...summary, title: 'New result' } ],
			total: 1,
			totalPages: 1,
		} );
		const view = renderHook( useQuizzes );
		const oldSignal = vi.mocked( listQuizzes ).mock.calls[ 0 ]?.[ 4 ];
		act( () => view.result.current.search( 'new' ) );
		await waitFor( () =>
			expect( view.result.current.data.items[ 0 ]?.title ).toBe(
				'New result'
			)
		);
		expect( oldSignal?.aborted ).toBe( true );
		await act( async () => {
			resolveOld( { items: [ summary ], total: 1, totalPages: 1 } );
		} );
		expect( view.result.current.data.items[ 0 ]?.title ).toBe(
			'New result'
		);
		view.unmount();
		expect(
			vi.mocked( listQuizzes ).mock.calls.at( -1 )?.[ 4 ]?.aborted
		).toBe( true );
	} );
	it( 'resets to page one when the status or course changes', async () => {
		vi.mocked( listQuizzes ).mockResolvedValue( {
			items: [ summary ],
			total: 40,
			totalPages: 2,
		} );
		const view = renderHook( useQuizzes );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () => expect( view.result.current.page ).toBe( 2 ) );
		act( () => view.result.current.setCourse( 5 ) );
		await waitFor( () =>
			expect( listQuizzes ).toHaveBeenLastCalledWith(
				1,
				'',
				'all',
				5,
				expect.any( AbortSignal )
			)
		);
		act( () => view.result.current.setStatus( 'trash' ) );
		await waitFor( () =>
			expect( listQuizzes ).toHaveBeenLastCalledWith(
				1,
				'',
				'trash',
				5,
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'steps back when the page is past the end', async () => {
		vi.mocked( listQuizzes )
			.mockResolvedValueOnce( {
				items: [ summary ],
				total: 40,
				totalPages: 2,
			} )
			.mockRejectedValueOnce(
				new ApiError( {
					status: 400,
					code: 'ohmylms_quiz_invalid_page',
					message: 'Past the end.',
				} )
			)
			.mockResolvedValue( {
				items: [ summary ],
				total: 20,
				totalPages: 1,
			} );
		const view = renderHook( useQuizzes );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () => expect( view.result.current.page ).toBe( 1 ) );
		await waitFor( () => expect( view.result.current.error ).toBeNull() );
	} );
	it( 'reports a failure', async () => {
		vi.mocked( listQuizzes ).mockRejectedValue(
			new ApiError( { status: 500, code: 'boom', message: 'Broken.' } )
		);
		const view = renderHook( useQuizzes );
		await waitFor( () =>
			expect( view.result.current.error ).toBe( 'Broken.' )
		);
		expect( view.result.current.loading ).toBe( false );
	} );
} );

describe( 'useQuizEditor', () => {
	it( 'requests nothing for an invalid id', () => {
		const view = renderHook( () => useQuizEditor( null ) );
		expect( getQuiz ).not.toHaveBeenCalled();
		expect( view.result.current.loading ).toBe( false );
	} );
	it( 'loads, reloads and cancels on unmount', async () => {
		vi.mocked( getQuiz ).mockResolvedValue( quiz );
		const view = renderHook( () => useQuizEditor( 11 ) );
		await waitFor( () =>
			expect( view.result.current.quiz ).toEqual( quiz )
		);
		const signal = vi.mocked( getQuiz ).mock.calls[ 0 ]?.[ 1 ];
		act( () => view.result.current.reload() );
		await waitFor( () => expect( getQuiz ).toHaveBeenCalledTimes( 2 ) );
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );
	it( 'exposes the typed error', async () => {
		vi.mocked( getQuiz ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_quiz_cannot_view',
				message: 'No.',
			} )
		);
		const view = renderHook( () => useQuizEditor( 11 ) );
		await waitFor( () =>
			expect( view.result.current.error?.status ).toBe( 403 )
		);
		expect( view.result.current.quiz ).toBeNull();
	} );
} );

describe( 'useAttempts', () => {
	it( 'loads the quiz and the first page and resets the page on new filters', async () => {
		vi.mocked( getQuiz ).mockResolvedValue( quiz );
		vi.mocked( listAttempts ).mockResolvedValue( {
			items: [ attempt ],
			total: 40,
			totalPages: 2,
		} );
		const view = renderHook( () => useAttempts( 11 ) );
		await waitFor( () =>
			expect( view.result.current.data.items ).toHaveLength( 1 )
		);
		await waitFor( () =>
			expect( view.result.current.quiz?.id ).toBe( 11 )
		);
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () => expect( view.result.current.page ).toBe( 2 ) );
		act( () =>
			view.result.current.setFilters( {
				status: 'in-review',
				search: 'sam',
			} )
		);
		await waitFor( () =>
			expect( listAttempts ).toHaveBeenLastCalledWith(
				11,
				1,
				{ status: 'in-review', search: 'sam' },
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'keeps the permission error', async () => {
		vi.mocked( getQuiz ).mockRejectedValue(
			new ApiError( { status: 403, code: 'x', message: 'x' } )
		);
		vi.mocked( listAttempts ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_quiz_cannot_grade',
				message: 'Only graders.',
			} )
		);
		const view = renderHook( () => useAttempts( 11 ) );
		await waitFor( () =>
			expect( view.result.current.error?.status ).toBe( 403 )
		);
	} );
	it( 'requests nothing for an invalid id', () => {
		renderHook( () => useAttempts( null ) );
		expect( listAttempts ).not.toHaveBeenCalled();
	} );
} );

describe( 'useAttemptDetail', () => {
	it( 'loads an attempt, clears it when closed and cancels on unmount', async () => {
		vi.mocked( getAttempt ).mockResolvedValue( attemptDetail );
		const view = renderHook(
			( { id }: { id: number | null } ) => useAttemptDetail( 11, id ),
			{ initialProps: { id: 21 as number | null } }
		);
		await waitFor( () =>
			expect( view.result.current.attempt ).toEqual( attemptDetail )
		);
		const signal = vi.mocked( getAttempt ).mock.calls[ 0 ]?.[ 2 ];
		view.rerender( { id: null } );
		await waitFor( () => expect( view.result.current.attempt ).toBeNull() );
		expect( signal?.aborted ).toBe( true );
	} );
} );
