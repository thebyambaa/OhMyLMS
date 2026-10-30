import * as client from '../../lib/api';
import { readLesson, saveLesson, publishLesson, createLesson } from './api';
import { parseLesson, savePayload } from './model';
import { lesson } from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof client >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
} ) );
beforeEach( () => vi.resetAllMocks() );
it( 'rejects malformed and cross-lesson responses', async () => {
	expect(
		parseLesson( { ...lesson, type: 'extension', native_editor_url: '' } )
			.native_editor_url
	).toBe( '' );
	expect( () =>
		parseLesson( { ...lesson, native_editor_url: 'javascript:alert(1)' } )
	).toThrow();
	expect( () =>
		parseLesson( { ...lesson, preview_enable: 'yes' } )
	).toThrow();
	expect( () =>
		parseLesson( { ...lesson, resources: [ { name: 'File', url: 5 } ] } )
	).toThrow();
	vi.mocked( client.get ).mockResolvedValue( { ...lesson, id: 13 } );
	await expect(
		readLesson( 12, new AbortController().signal )
	).rejects.toThrow();
} );
it( 'saves exactly the editable fields and publishes only a confirmed revision', async () => {
	const signal = new AbortController().signal;
	vi.mocked( client.put ).mockResolvedValue( lesson );
	vi.mocked( client.post ).mockResolvedValue( lesson );
	await saveLesson( lesson, signal );
	expect( client.put ).toHaveBeenCalledWith(
		'/ohmylms/v1/authoring/lessons/12',
		savePayload( lesson ),
		{ signal }
	);
	expect( savePayload( lesson ) ).not.toHaveProperty( 'status' );
	expect( savePayload( lesson ) ).not.toHaveProperty( 'chapter_id' );
	await publishLesson( lesson, signal );
	expect( client.post ).toHaveBeenCalledWith(
		'/ohmylms/v1/authoring/lessons/12/publish',
		{ revision: 'lesson-token', confirmed: true },
		{ signal }
	);
} );
it( 'preserves the creation identifier across transport retries', async () => {
	const signal = new AbortController().signal;
	vi.mocked( client.post ).mockResolvedValue( lesson );
	await createLesson( 'New lesson', 'request-uuid', signal );
	expect( client.post ).toHaveBeenCalledWith(
		'/ohmylms/v1/authoring/lessons',
		{ title: 'New lesson', request_id: 'request-uuid' },
		{ signal }
	);
} );
