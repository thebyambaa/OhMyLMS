import * as transport from '../../lib/api';
import { createAssignment } from '../assignments/public';
import { assignment } from '../assignments/fixtures';
import { createQuiz } from '../quizzes/public';
import { quiz } from '../quizzes/fixtures';
import {
	createResource,
	deleteUnit,
	findResources,
	readOutline,
	saveOutline,
} from './api';
import { outline } from './fixtures';
import { savePayload } from './model';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	put: vi.fn(),
	post: vi.fn(),
	del: vi.fn(),
} ) );
vi.mock( '../quizzes/public', () => ( { createQuiz: vi.fn() } ) );
vi.mock( '../assignments/public', () => ( { createAssignment: vi.fn() } ) );
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
it( 'deletes a saved unit with the read revision and validates the fresh outline', async () => {
	vi.mocked( transport.del ).mockResolvedValue( {
		...outline,
		revision: 'next',
	} );
	const result = await deleteUnit( outline, 9 );
	expect( transport.del ).toHaveBeenCalledWith(
		'/ohmylms/v1/courses/12/structure/units/9',
		{
			signal: undefined,
			headers: { 'X-OhMyLMS-Structure-Revision': 'read-token' },
		}
	);
	expect( result.revision ).toBe( 'next' );
	vi.mocked( transport.del ).mockResolvedValue( {
		...outline,
		course_id: 99,
	} );
	await expect( deleteUnit( outline, 9 ) ).rejects.toMatchObject( {
		code: 'invalid_response',
	} );
} );
it( 'searches the requested resource type and rejects invalid pagination', async () => {
	vi.mocked( transport.get ).mockResolvedValue( {
		items: [ { id: 4, title: 'Quiz' } ],
		pages: 1,
	} );
	await findResources( 12, 'quiz', 'eq', 2 );
	expect( transport.get ).toHaveBeenCalledWith(
		'/ohmylms/v1/courses/12/structure/resources',
		{ query: { type: 'quiz', search: 'eq', page: 2 }, signal: undefined }
	);
	vi.mocked( transport.get ).mockResolvedValue( { items: [], pages: -1 } );
	await expect(
		findResources( 12, 'assignment', '', 1 )
	).rejects.toMatchObject( { code: 'invalid_response' } );
} );
it( 'creates draft quizzes and assignments through their own APIs', async () => {
	const controller = new AbortController();
	vi.mocked( createQuiz ).mockResolvedValue( { ...quiz, id: 5 } );
	vi.mocked( createAssignment ).mockResolvedValue( {
		...assignment,
		id: 6,
	} );
	await expect(
		createResource( 'quiz', ' Check ', controller.signal )
	).resolves.toEqual( { id: 5, title: quiz.title } );
	expect( createQuiz ).toHaveBeenCalledWith( ' Check ', controller.signal );
	await expect( createResource( 'assignment', 'Essay' ) ).resolves.toEqual( {
		id: 6,
		title: assignment.title,
	} );
	expect( createAssignment ).toHaveBeenCalledWith( 'Essay', undefined );
	expect( transport.post ).not.toHaveBeenCalled();
} );
