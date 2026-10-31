import { ApiError } from './api';
import {
	createValidators,
	invalidResponseError,
	positiveId,
	record,
	text,
} from './validation';

const MESSAGE = 'The server returned invalid exam data.';

function thrown( work: () => unknown ): unknown {
	try {
		work();
	} catch ( error ) {
		return error;
	}
	throw new Error( 'Expected a throw.' );
}

describe( 'validators', () => {
	it( 'turn a message into an invalid_response ApiError with status 502', () => {
		const error = thrown( () => record( [], MESSAGE ) );
		expect( error ).toBeInstanceOf( ApiError );
		expect( error ).toMatchObject( {
			code: 'invalid_response',
			status: 502,
			message: MESSAGE,
		} );
	} );

	it( 'throw what an error factory returns, unchanged', () => {
		const plain = new Error( 'The gradebook sent an unexpected response.' );
		expect( thrown( () => text( 1, () => plain ) ) ).toBe( plain );
		expect( invalidResponseError( () => plain ) ).toBe( plain );
	} );

	it( 'accept plain objects only', () => {
		const row = { a: 1 };
		expect( record( row, MESSAGE ) ).toBe( row );
		for ( const bad of [ null, undefined, 0, '', 'x', [], [ 1 ] ] ) {
			expect( () => record( bad, MESSAGE ) ).toThrow( MESSAGE );
		}
	} );

	it( 'accept strings, the empty string included', () => {
		expect( text( '', MESSAGE ) ).toBe( '' );
		expect( text( 'a', MESSAGE ) ).toBe( 'a' );
		for ( const bad of [ null, undefined, 1, {}, [] ] ) {
			expect( () => text( bad, MESSAGE ) ).toThrow( MESSAGE );
		}
	} );

	it( 'accept safe integers of at least 1 as IDs', () => {
		expect( positiveId( 1, MESSAGE ) ).toBe( 1 );
		expect( positiveId( Number.MAX_SAFE_INTEGER, MESSAGE ) ).toBe(
			Number.MAX_SAFE_INTEGER
		);
		for ( const bad of [
			0,
			-1,
			1.5,
			'1',
			null,
			Number.MAX_SAFE_INTEGER + 1,
			Number.NaN,
		] ) {
			expect( () => positiveId( bad, MESSAGE ) ).toThrow( MESSAGE );
		}
	} );

	it( 'bind to one feature error with createValidators', () => {
		let calls = 0;
		const invalid = () => {
			calls++;
			return new ApiError( {
				code: 'invalid_response',
				status: 502,
				message: 'The server returned invalid webhook data.',
			} );
		};
		const bound = createValidators( invalid );
		expect( bound.record( { id: 1 } ) ).toEqual( { id: 1 } );
		expect( bound.text( 'x' ) ).toBe( 'x' );
		expect( bound.positiveId( 3 ) ).toBe( 3 );
		expect( calls ).toBe( 0 );
		expect( () => bound.record( null ) ).toThrow(
			'The server returned invalid webhook data.'
		);
		expect( () => bound.text( 2 ) ).toThrow( ApiError );
		expect( () => bound.positiveId( 0 ) ).toThrow( ApiError );
		expect( calls ).toBe( 3 );
	} );
} );
