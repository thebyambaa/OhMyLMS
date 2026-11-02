import { get, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	formatIssuedDate,
	issueCertificate,
	listIssued,
	parseIssued,
	reissueIssued,
	revokeIssued,
	searchLearners,
} from './issuedApi';
import { issued } from './issuedFixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'issued certificates API', () => {
	it( 'validates an issued certificate', () => {
		expect( parseIssued( issued ) ).toEqual( issued );
		expect( () => parseIssued( { ...issued, status: 'void' } ) ).toThrow();
		expect( () =>
			parseIssued( { ...issued, links: { pdf: 1 } } )
		).toThrow();
		expect( () => parseIssued( { ...issued, id: 0 } ) ).toThrow();
	} );
	it( 'lists with only the filters that are set', async () => {
		vi.mocked( getCollection ).mockResolvedValue( {
			items: [],
			total: 0,
			totalPages: 0,
		} );
		await listIssued( {
			page: 2,
			search: ' ABCD ',
			status: 'revoked',
			course: '12',
			certificate: '',
			source: '',
			orderby: 'student_name',
			order: 'asc',
		} );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/issued-certificates',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					search: 'ABCD',
					status: 'revoked',
					course: 12,
					certificate: undefined,
					source: undefined,
					orderby: 'student_name',
					order: 'asc',
				},
			} )
		);
	} );
	it( 'issues, revokes and re-issues through their routes', async () => {
		vi.mocked( post ).mockResolvedValue( issued );
		await issueCertificate( {
			user_id: 3,
			course_id: 12,
			certificate_id: 0,
		} );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/issued-certificates',
			{ user_id: 3, course_id: 12, certificate_id: 0 },
			{ signal: undefined }
		);
		await revokeIssued( 41 );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/issued-certificates/41/revoke',
			undefined,
			{ signal: undefined }
		);
		await reissueIssued( 41 );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/issued-certificates/41/reissue',
			undefined,
			{ signal: undefined }
		);
	} );
	it( 'searches learners', async () => {
		vi.mocked( get ).mockResolvedValueOnce( [
			{ id: 3, name: 'Ada Lovelace', email: 'ada@example.test' },
		] );
		expect( await searchLearners( ' ada ' ) ).toEqual( [
			{ id: 3, name: 'Ada Lovelace', email: 'ada@example.test' },
		] );
		expect( get ).toHaveBeenCalledWith(
			'/ohmylms/v1/issued-certificates/learners',
			{ signal: undefined, query: { search: 'ada' } }
		);
		vi.mocked( get ).mockResolvedValueOnce( { id: 3 } );
		await expect( searchLearners( '' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'formats dates and tolerates missing ones', () => {
		expect( formatIssuedDate( null ) ).toBe( '' );
		expect( formatIssuedDate( 'not a date' ) ).toBe( '' );
		expect( formatIssuedDate( '2026-10-20T09:30:00Z' ) ).toMatch( /2026/ );
	} );
} );
