import { get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	createAccount,
	getAccount,
	getAccountOptions,
	listAccounts,
	updateAccount,
} from './accountsApi';
import { account, accountOptions, editableAccount } from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	patch: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const BASE = '/ohmylms/v1/account-hub/users';

beforeEach( () => vi.clearAllMocks() );

describe( 'all accounts API', () => {
	it( 'requests a bounded, filtered page', async () => {
		await listAccounts( 2, 'tess', {
			role: 'ohmylms_teacher',
			registered: 'last_30_days',
			status: 'blocked',
		} );
		expect( getCollection ).toHaveBeenLastCalledWith(
			BASE,
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					search: 'tess',
					role: 'ohmylms_teacher',
					registered: 'last_30_days',
					status: 'blocked',
				},
			} )
		);
		await listAccounts( 1, '', {
			role: '',
			registered: 'all',
			status: 'all',
		} );
		expect(
			vi.mocked( getCollection ).mock.lastCall?.[ 2 ]?.query
		).not.toHaveProperty( 'role' );
	} );

	it( 'reads options and one account', async () => {
		vi.mocked( get ).mockResolvedValueOnce( accountOptions );
		await expect( getAccountOptions() ).resolves.toEqual( accountOptions );
		expect( get ).toHaveBeenLastCalledWith( `${ BASE }/options`, {
			signal: undefined,
		} );
		vi.mocked( get ).mockResolvedValueOnce( editableAccount );
		await expect( getAccount( 41 ) ).resolves.toEqual( editableAccount );
		expect( get ).toHaveBeenLastCalledWith( `${ BASE }/41`, {
			signal: undefined,
		} );
		await expect( getAccount( 0 ) ).rejects.toThrow();
	} );

	it( 'creates with trimmed fields and never sends a role', async () => {
		vi.mocked( post ).mockResolvedValueOnce( account );
		await createAccount( {
			type: 'teacher',
			first_name: ' Tess ',
			last_name: ' Teacher ',
			email: ' tess@example.org ',
			username: '',
			send_email: true,
		} );
		expect( post ).toHaveBeenLastCalledWith(
			BASE,
			{
				type: 'teacher',
				first_name: 'Tess',
				last_name: 'Teacher',
				email: 'tess@example.org',
				username: '',
				send_email: true,
			},
			{ signal: undefined }
		);
	} );

	it( 'saves with the loaded version', async () => {
		vi.mocked( patch ).mockResolvedValueOnce( editableAccount );
		await updateAccount(
			41,
			{ first_name: 'Tess', last_name: '', email: 'tess@example.org' },
			'0123456789abcdef',
			true
		);
		expect( patch ).toHaveBeenLastCalledWith(
			`${ BASE }/41`,
			{
				first_name: 'Tess',
				last_name: '',
				email: 'tess@example.org',
				send_reset: true,
				version: '0123456789abcdef',
			},
			{ signal: undefined }
		);
	} );
} );
