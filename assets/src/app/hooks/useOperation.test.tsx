import { act, renderHook } from '@testing-library/react';
import { ApiError } from '../lib/api';
import { useOperation } from './useOperation';

const conflict = () =>
	new ApiError( {
		status: 409,
		code: 'ohmylms_student_already_enrolled',
		message: 'Already enrolled',
	} );

describe( 'useOperation', () => {
	it( 'rejects a second write while one runs', async () => {
		const view = renderHook( () => useOperation() );
		let finish!: () => void;
		let first!: Promise< boolean >;
		act( () => {
			first = view.result.current.run(
				() =>
					new Promise< void >( ( resolve ) => {
						finish = resolve;
					} )
			);
		} );
		expect( view.result.current.busy ).toBe( true );
		const second = vi.fn();
		let secondResult = true;
		await act( async () => {
			secondResult = await view.result.current.run( second );
		} );
		expect( secondResult ).toBe( false );
		expect( second ).not.toHaveBeenCalled();
		let firstResult = false;
		await act( async () => {
			finish();
			firstResult = await first;
		} );
		expect( firstResult ).toBe( true );
		expect( view.result.current.busy ).toBe( false );
	} );

	it( 'passes the result to the optional success callback', async () => {
		const view = renderHook( () => useOperation() );
		const success = vi.fn();
		await act( async () => {
			await view.result.current.run( async () => 7, success );
		} );
		expect( success ).toHaveBeenCalledWith( 7 );
	} );

	it( 'reports the failure message by default, and an empty string when there is none', async () => {
		const view = renderHook( () => useOperation() );
		expect( view.result.current.error ).toBe( '' );
		let done = true;
		await act( async () => {
			done = await view.result.current.run( () =>
				Promise.reject( conflict() )
			);
		} );
		expect( done ).toBe( false );
		expect( view.result.current.error ).toBe( 'Already enrolled' );
		act( () => view.result.current.clearError() );
		expect( view.result.current.error ).toBe( '' );
	} );

	it( 'reports the ApiError, or null, with errorShape object', async () => {
		const view = renderHook( () =>
			useOperation( { errorShape: 'object' } )
		);
		expect( view.result.current.error ).toBeNull();
		await act( async () => {
			await view.result.current.run( () =>
				Promise.reject( new Error( 'nope' ) )
			);
		} );
		expect( view.result.current.error ).toBeInstanceOf( ApiError );
		expect( view.result.current.error?.message ).toBe( 'nope' );
		await act( async () => {
			await view.result.current.run( () => Promise.reject( conflict() ) );
		} );
		expect( view.result.current.error?.status ).toBe( 409 );
		act( () => view.result.current.clearError() );
		expect( view.result.current.error ).toBeNull();
	} );

	it( 'keeps run stable across renders', () => {
		const view = renderHook( () => useOperation() );
		const run = view.result.current.run;
		view.rerender();
		expect( view.result.current.run ).toBe( run );
	} );

	it( 'cancels the running write on unmount and ignores its result', () => {
		const view = renderHook( () => useOperation() );
		let signal: AbortSignal | undefined;
		const success = vi.fn();
		act( () => {
			void view.result.current.run( ( received ) => {
				signal = received;
				return new Promise< void >( () => undefined );
			}, success );
		} );
		view.unmount();
		expect( signal?.aborted ).toBe( true );
		expect( success ).not.toHaveBeenCalled();
	} );
} );
