import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	createCertificate,
	deleteCertificate,
	duplicateCertificate,
	getCertificate,
	getOptions,
	listCertificates,
	listCourses,
	listMedia,
	previewCertificate,
	updateCertificate,
} from './api';
import { certificate, options } from './fixtures';
import { toInput, formFromCertificate } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

const input = toInput( formFromCertificate( certificate ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed certificates API', () => {
	it( 'requests bounded, ordered, filtered collections', async () => {
		await listCertificates( 2, 'gold', 'draft' );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/certificates',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					search: 'gold',
					status: 'draft',
					orderby: 'created_at',
					order: 'desc',
				},
			} )
		);
		await listCourses( 'alg', 3 );
		expect( getCollection ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/certificates/courses',
			expect.any( Function ),
			expect.objectContaining( {
				query: { search: 'alg', page: 3, per_page: 10 },
			} )
		);
		await listMedia( 'logo', 1 );
		const [ path, , extras ] = vi
			.mocked( getCollection )
			.mock.calls.at( -1 )!;
		expect( path ).toBe( '/wp/v2/media' );
		expect( extras?.query ).toMatchObject( {
			media_type: 'image',
			mime_type: 'image/jpeg,image/png,image/gif,image/webp',
			per_page: 12,
			search: 'logo',
		} );
	} );
	it( 'reads and validates one template and the options', async () => {
		vi.mocked( get ).mockResolvedValueOnce( certificate );
		expect( await getCertificate( 7 ) ).toEqual( certificate );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/certificates/7',
			expect.any( Object )
		);
		vi.mocked( get ).mockResolvedValueOnce( { ...certificate, id: 'x' } );
		await expect( getCertificate( 7 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		vi.mocked( get ).mockResolvedValueOnce( options );
		expect( await getOptions() ).toEqual( options );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/certificates/options',
			expect.any( Object )
		);
		await expect( getCertificate( 0 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'creates with the trimmed name only, and saves the full typed payload', async () => {
		vi.mocked( post ).mockResolvedValueOnce( certificate );
		await createCertificate( '  Gold  ' );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/certificates',
			{ name: 'Gold' },
			expect.any( Object )
		);
		vi.mocked( put ).mockResolvedValueOnce( certificate );
		const saved = await updateCertificate( 7, input );
		expect( saved ).toEqual( certificate );
		expect( put ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/certificates/7',
			input,
			expect.any( Object )
		);
		expect(
			vi.mocked( put ).mock.calls.at( -1 )?.[ 1 ]
		).not.toHaveProperty( 'id' );
		expect(
			vi.mocked( put ).mock.calls.at( -1 )?.[ 1 ]
		).not.toHaveProperty( 'html_contents' );
	} );
	it( 'duplicates, deletes and validates the results', async () => {
		vi.mocked( post ).mockResolvedValueOnce( { ...certificate, id: 8 } );
		expect( ( await duplicateCertificate( 7 ) ).id ).toBe( 8 );
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/certificates/7/duplicate',
			undefined,
			expect.any( Object )
		);
		vi.mocked( del ).mockResolvedValueOnce( { deleted: true } );
		await deleteCertificate( 7 );
		expect( del ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/certificates/7',
			expect.any( Object )
		);
		vi.mocked( del ).mockResolvedValueOnce( {} );
		await expect( deleteCertificate( 7 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
		await expect( deleteCertificate( -1 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'previews only the template fields', async () => {
		vi.mocked( post ).mockResolvedValueOnce( {
			html: '<p>x</p>',
			width_mm: 297,
			height_mm: 210,
		} );
		const preview = await previewCertificate( input );
		expect( preview.width_mm ).toBe( 297 );
		const body = vi.mocked( post ).mock.calls.at( -1 )?.[ 1 ];
		expect( post ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/certificates/preview',
			expect.any( Object ),
			expect.any( Object )
		);
		expect( body ).not.toHaveProperty( 'name' );
		expect( body ).not.toHaveProperty( 'course_ids' );
		expect( body ).toHaveProperty( 'blocks' );
	} );
} );
