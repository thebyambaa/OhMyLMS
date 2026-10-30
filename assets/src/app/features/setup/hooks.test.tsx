import { act, renderHook, waitFor } from '@testing-library/react';
import { ApiError } from '../../lib/api';
import * as api from './api';
import { noResult, rawState } from './fixtures';
import { useSetup } from './hooks';
import { parseState } from './model';

vi.mock( './api', () => ( {
	getSetup: vi.fn(),
	saveStep: vi.fn(),
	skipStep: vi.fn(),
	completeSetup: vi.fn(),
	dismissSetup: vi.fn(),
	resetSetup: vi.fn(),
} ) );

const fresh = parseState( rawState() );
const afterWelcome = parseState( rawState( { welcome: 'done' } ) );

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( api.getSetup ).mockResolvedValue( fresh );
} );

async function loaded() {
	const view = renderHook( () => useSetup() );
	await waitFor( () => expect( view.result.current.setup ).not.toBeNull() );
	return view;
}

describe( 'useSetup', () => {
	it( 'loads the state and reports load failures with a retry', async () => {
		vi.mocked( api.getSetup ).mockRejectedValueOnce(
			new ApiError( { status: 500, code: 'x', message: 'Broken' } )
		);
		const view = renderHook( () => useSetup() );
		expect( view.result.current.loading ).toBe( true );
		await waitFor( () =>
			expect( view.result.current.loadError?.message ).toBe( 'Broken' )
		);
		expect( view.result.current.loading ).toBe( false );
		act( () => view.result.current.reload() );
		await waitFor( () =>
			expect( view.result.current.setup ).toEqual( fresh )
		);
		expect( view.result.current.loadError ).toBeNull();
	} );

	it( 'saves a step and adopts the returned state', async () => {
		const view = await loaded();
		vi.mocked( api.saveStep ).mockResolvedValue( {
			state: afterWelcome,
			result: noResult,
		} );
		let ok = false;
		await act( async () => {
			ok = await view.result.current.save( 'welcome', {} );
		} );
		expect( ok ).toBe( true );
		expect( api.saveStep ).toHaveBeenCalledWith(
			'welcome',
			{},
			expect.any( AbortSignal )
		);
		expect( view.result.current.setup ).toEqual( afterWelcome );
		expect( view.result.current.lastResult ).toEqual( noResult );
		expect( view.result.current.busy ).toBe( false );
	} );

	it( 'keeps the state and exposes field errors when a save fails', async () => {
		const view = await loaded();
		vi.mocked( api.saveStep ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_setup_invalid_value',
				message: 'Some values are not valid.',
				details: { params: { currency: 'Unknown currency.' } },
			} )
		);
		let ok = true;
		await act( async () => {
			ok = await view.result.current.save( 'preferences', {} );
		} );
		expect( ok ).toBe( false );
		expect( view.result.current.setup ).toEqual( fresh );
		expect( view.result.current.actionError ).toMatchObject( {
			permission: false,
			fieldErrors: { currency: 'Unknown currency.' },
		} );
		act( () => view.result.current.clearError() );
		expect( view.result.current.actionError ).toBeNull();
	} );

	it( 'flags permission failures', async () => {
		const view = await loaded();
		vi.mocked( api.skipStep ).mockRejectedValue(
			new ApiError( {
				status: 403,
				code: 'ohmylms_setup_cannot_manage',
				message: 'No.',
			} )
		);
		await act( async () => {
			await view.result.current.skip( 'pages' );
		} );
		expect( view.result.current.actionError?.permission ).toBe( true );
	} );

	it( 'ignores a second action while one is pending', async () => {
		const view = await loaded();
		let resolve: ( value: never ) => void = () => undefined;
		vi.mocked( api.completeSetup ).mockReturnValue(
			new Promise( ( r ) => {
				resolve = r as never;
			} )
		);
		let first: Promise< boolean > = Promise.resolve( false );
		act( () => {
			first = view.result.current.complete();
		} );
		await waitFor( () => expect( view.result.current.busy ).toBe( true ) );
		let second = true;
		await act( async () => {
			second = await view.result.current.complete();
		} );
		expect( second ).toBe( false );
		expect( api.completeSetup ).toHaveBeenCalledTimes( 1 );
		await act( async () => {
			resolve( afterWelcome as never );
			await first;
		} );
		expect( view.result.current.busy ).toBe( false );
	} );

	it( 'dismisses and resets through the matching calls', async () => {
		const view = await loaded();
		vi.mocked( api.dismissSetup ).mockResolvedValue( afterWelcome );
		vi.mocked( api.resetSetup ).mockResolvedValue( fresh );
		await act( async () => {
			await view.result.current.dismiss( 'niche' );
		} );
		expect( api.dismissSetup ).toHaveBeenCalledWith(
			'niche',
			expect.any( AbortSignal )
		);
		expect( view.result.current.setup ).toEqual( afterWelcome );
		await act( async () => {
			await view.result.current.reset();
		} );
		expect( view.result.current.setup ).toEqual( fresh );
	} );

	it( 'cancels the load and discards a late answer after unmount', async () => {
		let signal: AbortSignal | undefined;
		let resolve: ( state: typeof fresh ) => void = () => undefined;
		vi.mocked( api.getSetup ).mockImplementation( ( s ) => {
			signal = s;
			return new Promise( ( r ) => {
				resolve = r;
			} );
		} );
		const view = renderHook( () => useSetup() );
		view.unmount();
		expect( signal?.aborted ).toBe( true );
		resolve( fresh );
	} );

	it( 'aborts a pending action on unmount', async () => {
		const view = await loaded();
		let signal: AbortSignal | undefined;
		vi.mocked( api.skipStep ).mockImplementation( ( _step, s ) => {
			signal = s;
			return new Promise( () => undefined );
		} );
		act( () => {
			void view.result.current.skip( 'pages' );
		} );
		view.unmount();
		expect( signal?.aborted ).toBe( true );
	} );
} );
