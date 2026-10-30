import {
	act,
	render,
	renderHook,
	screen,
	waitFor,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Link, MemoryRouter, Route, Routes } from 'react-router-dom';
import type { ReactNode } from 'react';
import { ApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { getCourse, getOptions, listCourses } from './api';
import { detail, options, summary } from './fixtures';
import { useCourseEditor } from './useCourseEditor';
import { useCourses } from './useCourses';
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard';
import type { Course } from './types';

vi.mock( './api', () => ( {
	listCourses: vi.fn(),
	getCourse: vi.fn(),
	getOptions: vi.fn(),
} ) );

beforeEach( () => vi.resetAllMocks() );

const filters = ( changes = {} ) => ( {
	search: '',
	status: 'all',
	price_type: 'all',
	created_after: '',
	created_before: '',
	...changes,
} );

describe( 'useCourses', () => {
	it( 'cancels obsolete searches and ignores late responses', async () => {
		let resolveOld!: ( value: Collection< Course > ) => void;
		vi.mocked( listCourses ).mockImplementationOnce(
			() =>
				new Promise( ( resolve ) => {
					resolveOld = resolve;
				} )
		);
		vi.mocked( listCourses ).mockResolvedValue( {
			items: [ { ...summary, title: 'New result' } ],
			total: 1,
			totalPages: 1,
		} );
		const view = renderHook( useCourses );
		const oldSignal = vi.mocked( listCourses ).mock.calls[ 0 ]?.[ 2 ];
		act( () =>
			view.result.current.applyFilters( filters( { search: 'new' } ) )
		);
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
		expect( listCourses ).toHaveBeenLastCalledWith(
			1,
			filters( { search: 'new' } ),
			expect.any( AbortSignal )
		);
	} );
	it( 'goes back to page one when the filters change', async () => {
		vi.mocked( listCourses ).mockResolvedValue( {
			items: [ summary ],
			total: 50,
			totalPages: 3,
		} );
		const view = renderHook( useCourses );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 3 ) );
		await waitFor( () => expect( view.result.current.page ).toBe( 3 ) );
		act( () =>
			view.result.current.applyFilters( filters( { status: 'draft' } ) )
		);
		await waitFor( () => expect( view.result.current.page ).toBe( 1 ) );
		expect( vi.mocked( listCourses ).mock.calls.at( -1 )?.[ 0 ] ).toBe( 1 );
	} );
	it( 'reports errors and recovers on reload', async () => {
		vi.mocked( listCourses ).mockRejectedValueOnce(
			new ApiError( { status: 500, code: 'x', message: 'Broken' } )
		);
		vi.mocked( listCourses ).mockResolvedValueOnce( {
			items: [ summary ],
			total: 1,
			totalPages: 1,
		} );
		const view = renderHook( useCourses );
		await waitFor( () =>
			expect( view.result.current.error ).toBe( 'Broken' )
		);
		act( () => view.result.current.reload() );
		await waitFor( () => expect( view.result.current.error ).toBeNull() );
		expect( view.result.current.data.items ).toHaveLength( 1 );
	} );
	it( 'steps back when the requested page is past the end', async () => {
		vi.mocked( listCourses ).mockResolvedValueOnce( {
			items: [ summary ],
			total: 21,
			totalPages: 2,
		} );
		const view = renderHook( useCourses );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		vi.mocked( listCourses ).mockRejectedValueOnce(
			new ApiError( {
				status: 400,
				code: 'ohmylms_course_invalid_page',
				message: 'past the end',
			} )
		);
		vi.mocked( listCourses ).mockResolvedValue( {
			items: [ summary ],
			total: 1,
			totalPages: 1,
		} );
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () => expect( view.result.current.page ).toBe( 1 ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		expect( view.result.current.error ).toBeNull();
	} );
	it( 'steps back after removing the last row of a later page', async () => {
		vi.mocked( listCourses ).mockResolvedValue( {
			items: [ summary ],
			total: 21,
			totalPages: 2,
		} );
		const view = renderHook( useCourses );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.setPage( 2 ) );
		await waitFor( () => expect( view.result.current.page ).toBe( 2 ) );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		act( () => view.result.current.afterRemoval() );
		await waitFor( () => expect( view.result.current.page ).toBe( 1 ) );
	} );
} );

describe( 'useCourseEditor', () => {
	it( 'loads the course and the options together', async () => {
		vi.mocked( getCourse ).mockResolvedValue( detail );
		vi.mocked( getOptions ).mockResolvedValue( options );
		const view = renderHook( () => useCourseEditor( 7 ) );
		expect( view.result.current.loading ).toBe( true );
		await waitFor( () =>
			expect( view.result.current.loading ).toBe( false )
		);
		expect( view.result.current.course ).toEqual( detail );
		expect( view.result.current.options ).toEqual( options );
		expect( getCourse ).toHaveBeenCalledWith(
			7,
			expect.any( AbortSignal )
		);
	} );
	it( 'requests nothing for an invalid id', () => {
		const view = renderHook( () => useCourseEditor( null ) );
		expect( view.result.current.loading ).toBe( false );
		expect( getCourse ).not.toHaveBeenCalled();
	} );
	it( 'exposes the error status and retries', async () => {
		vi.mocked( getOptions ).mockResolvedValue( options );
		vi.mocked( getCourse ).mockRejectedValueOnce(
			new ApiError( { status: 403, code: 'denied', message: 'No' } )
		);
		vi.mocked( getCourse ).mockResolvedValueOnce( detail );
		const view = renderHook( () => useCourseEditor( 7 ) );
		await waitFor( () =>
			expect( view.result.current.error?.status ).toBe( 403 )
		);
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.course ).toEqual( detail )
		);
		expect( view.result.current.error ).toBeNull();
	} );
	it( 'aborts on unmount and ignores a late result', async () => {
		let resolve!: ( value: typeof detail ) => void;
		vi.mocked( getCourse ).mockImplementation(
			() =>
				new Promise( ( done ) => {
					resolve = done;
				} )
		);
		vi.mocked( getOptions ).mockResolvedValue( options );
		const view = renderHook( () => useCourseEditor( 7 ) );
		const signal = vi.mocked( getCourse ).mock.calls[ 0 ]?.[ 1 ];
		view.unmount();
		expect( signal?.aborted ).toBe( true );
		await act( async () => resolve( detail ) );
	} );
} );

describe( 'useUnsavedChangesGuard', () => {
	function Harness( { dirty }: { dirty: boolean } ) {
		const guard = useUnsavedChangesGuard( dirty );
		return (
			<>
				<Link to="/elsewhere">Elsewhere</Link>
				<a href="https://example.test/out">External</a>
				{ guard.pendingPath && (
					<>
						<p>Waiting for { guard.pendingPath }</p>
						<button onClick={ guard.stay }>Stay</button>
						<button onClick={ guard.leave }>Leave</button>
					</>
				) }
			</>
		);
	}
	const wrap = ( dirty: boolean ): ReactNode => (
		<MemoryRouter initialEntries={ [ '/here' ] }>
			<Routes>
				<Route path="/here" element={ <Harness dirty={ dirty } /> } />
				<Route path="/elsewhere" element={ <p>Arrived</p> } />
			</Routes>
		</MemoryRouter>
	);
	it( 'lets links through when nothing changed', async () => {
		render( wrap( false ) );
		await userEvent.click(
			screen.getByRole( 'link', { name: 'Elsewhere' } )
		);
		expect( screen.getByText( 'Arrived' ) ).toBeInTheDocument();
	} );
	it( 'holds an in-app link while dirty until the user decides', async () => {
		const user = userEvent.setup();
		render( wrap( true ) );
		await user.click( screen.getByRole( 'link', { name: 'Elsewhere' } ) );
		expect(
			screen.getByText( 'Waiting for /elsewhere' )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Stay' } ) );
		expect( screen.queryByText( /Waiting/ ) ).not.toBeInTheDocument();
		await user.click( screen.getByRole( 'link', { name: 'Elsewhere' } ) );
		await user.click( screen.getByRole( 'button', { name: 'Leave' } ) );
		expect( screen.getByText( 'Arrived' ) ).toBeInTheDocument();
	} );
	it( 'asks the browser before the page unloads while dirty', () => {
		render( wrap( true ) );
		const event = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( event );
		expect( event.defaultPrevented ).toBe( true );
	} );
	it( 'ignores external links', () => {
		render( wrap( true ) );
		const click = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		screen.getByRole( 'link', { name: 'External' } ).dispatchEvent( click );
		expect( screen.queryByText( /Waiting/ ) ).not.toBeInTheDocument();
	} );
} );
