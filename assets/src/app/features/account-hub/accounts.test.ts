import { ApiError } from '../../lib/api';
import {
	failedField,
	parseAccount,
	parseAccountOptions,
	parseEditableAccount,
	validateAccount,
	validateNewAccount,
} from './accounts';
import { account, accountOptions, editableAccount } from './fixtures';
import { tabFromParam } from './model';

const wire = ( value: unknown ) => JSON.parse( JSON.stringify( value ) );

describe( 'all accounts model', () => {
	it( 'parses listed accounts and rejects malformed ones', () => {
		expect( parseAccount( wire( account ) ) ).toEqual( account );
		expect( () => parseAccount( { ...account, id: 0 } ) ).toThrow(
			ApiError
		);
		expect( () => parseAccount( { ...account, blocked: 'no' } ) ).toThrow(
			ApiError
		);
		expect( () =>
			parseAccount( { ...account, roles: [ { slug: 1 } ] } )
		).toThrow( ApiError );
		expect( () =>
			parseAccount( { ...account, can_edit: undefined } )
		).toThrow( ApiError );
	} );

	it( 'parses an editable account with its version', () => {
		expect( parseEditableAccount( wire( editableAccount ) ) ).toEqual(
			editableAccount
		);
		expect( () =>
			parseEditableAccount( { ...editableAccount, version: 'x' } )
		).toThrow( ApiError );
	} );

	it( 'parses options and refuses unknown account types', () => {
		expect( parseAccountOptions( wire( accountOptions ) ) ).toEqual(
			accountOptions
		);
		expect( () =>
			parseAccountOptions( {
				...accountOptions,
				types: [ { value: 'administrator', label: 'Admin' } ],
			} )
		).toThrow( ApiError );
	} );

	it( 'validates names, email and username like the server', () => {
		expect(
			validateAccount( {
				first_name: ' ',
				last_name: '',
				email: 'nope',
			} )
		).toEqual( {
			first_name: expect.any( String ),
			email: expect.any( String ),
		} );
		expect(
			validateNewAccount( {
				type: 'student',
				first_name: 'Ana',
				last_name: 'x'.repeat( 191 ),
				email: 'ana@example.org',
				username: '<b>',
				send_email: true,
			} )
		).toEqual( {
			last_name: expect.any( String ),
			username: expect.any( String ),
		} );
		expect(
			validateNewAccount( {
				type: 'teacher',
				first_name: 'Ana',
				last_name: '',
				email: 'ana@example.org',
				username: '',
				send_email: false,
			} )
		).toEqual( {} );
	} );

	it( 'maps server field errors and the users tab', () => {
		expect(
			failedField( { status: 409, field: 'email' }, [
				'first_name',
				'email',
			] )
		).toBe( 'email' );
		expect(
			failedField( { status: 409, field: 'role' }, [ 'email' ] )
		).toBeUndefined();
		expect( failedField( null, [ 'email' ] ) ).toBeUndefined();
		expect( tabFromParam( 'users' ) ).toBe( 'users' );
	} );
} );
