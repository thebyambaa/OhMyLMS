import * as transport from '../../lib/api';
import { readOutline, saveOutline, createCourse, listCourses } from './api';
import { outline } from './fixtures';
import { savePayload } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	put: vi.fn(),
	post: vi.fn(),
} ) );
beforeEach( () => vi.resetAllMocks() );
it( 'sends the original read revision and cancellation signal, never auto-publishes', async () => {
	const controller = new AbortController();
	vi.mocked( transport.put ).mockResolvedValue( outline );
	await saveOutline( outline, savePayload( outline ), controller.signal );
	expect( transport.put ).toHaveBeenCalledWith(
		'/ohmylms/v1/courses/12/structure',
		savePayload( outline ),
		{
			signal: controller.signal,
			headers: { 'X-OhMyLMS-Structure-Revision': 'read-token' },
		}
	);
} );
it( 'rejects responses for the wrong course', async () => {
	vi.mocked( transport.get ).mockResolvedValue( {
		...outline,
		course_id: 99,
	} );
	await expect( readOutline( 12 ) ).rejects.toMatchObject( {
		code: 'invalid_response',
	} );
} );
it( 'creates only a named draft and validates its response', async () => {
	vi.mocked( transport.post ).mockResolvedValue( {
		id: 4,
		title: 'Math',
		status: 'draft',
	} );
	await createCourse( 'Math' );
	expect( transport.post ).toHaveBeenCalledWith(
		'/ohmylms/v1/authoring/courses',
		{ title: 'Math' },
		{ signal: undefined }
	);
} );
it( 'rejects invalid pagination metadata', async () => {
	vi.mocked( transport.get ).mockResolvedValue( {
		items: [],
		total: 0,
		pages: -1,
	} );
	await expect( listCourses( 1, '' ) ).rejects.toMatchObject( {
		code: 'invalid_response',
	} );
} );
