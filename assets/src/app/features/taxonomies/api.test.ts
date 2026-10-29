import { get } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import { getTermDetail, listTerms } from './api';
import { child, detail } from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );

beforeEach( () => vi.clearAllMocks() );

describe( 'typed taxonomies API', () => {
	it.each( [
		[ 'categories', '/ohmylms/v1/categories' ],
		[ 'tags', '/ohmylms/v1/tags' ],
	] as const )(
		'lists %s with a bounded, sorted query',
		async ( kind, path ) => {
			await listTerms( kind, { page: 3, search: 'alg' } );
			expect( getCollection ).toHaveBeenCalledWith(
				path,
				expect.any( Function ),
				{
					signal: undefined,
					query: {
						page: 3,
						per_page: 20,
						orderby: 'name',
						order: 'asc',
						search: 'alg',
					},
				}
			);
		}
	);
	it( 'loads a term with the edit context and validates it', async () => {
		vi.mocked( get ).mockResolvedValue( { ...detail, _links: {} } );
		expect( await getTermDetail( 'categories', 4 ) ).toEqual( detail );
		expect( get ).toHaveBeenCalledWith( '/ohmylms/v1/categories/4', {
			signal: undefined,
			query: { context: 'edit' },
		} );
		vi.mocked( get ).mockResolvedValue( child );
		await expect( getTermDetail( 'tags', 4 ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
	it( 'refuses invalid ids before requesting', async () => {
		await expect( getTermDetail( 'tags', 0 ) ).rejects.toThrow();
		await expect( getTermDetail( 'tags', 1.5 ) ).rejects.toThrow();
		expect( get ).not.toHaveBeenCalled();
	} );
} );
