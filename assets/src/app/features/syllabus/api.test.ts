import * as transport from '../../lib/api';
import { command, importRows, readMappings, readSyllabus } from './api';
import { outline, report } from './fixtures';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
} ) );
beforeEach( () => vi.resetAllMocks() );
it( 'rejects data for the wrong syllabus on reads and writes', async () => {
	vi.mocked( transport.get ).mockResolvedValue( outline );
	await expect( readSyllabus( 99 ) ).rejects.toMatchObject( {
		code: 'invalid_response',
	} );
	vi.mocked( transport.post ).mockResolvedValue( outline );
	await expect( command( 99, 'sync_course', {} ) ).rejects.toMatchObject( {
		code: 'invalid_response',
	} );
} );
it( 'requests server preview before an apply and preserves cancellation', async () => {
	vi.mocked( transport.post ).mockResolvedValue( { report } );
	const controller = new AbortController();
	const rows = [ { line: 2, skill: 'Count' } ];
	const result = await importRows( 5, rows, true, controller.signal );
	expect( result.outline ).toBeNull();
	expect( transport.post ).toHaveBeenCalledWith(
		'/ohmylms/v1/authoring/syllabuses/5/commands',
		{ operation: 'import', rows, dry_run: true },
		{ signal: controller.signal }
	);
} );
it( 'reads the shared-skill mappings of a syllabus objective with their revisions', async () => {
	vi.mocked( transport.get ).mockResolvedValue( {
		maps_to: [
			{
				shared_id: 20,
				shared: { name: 'Number sense' },
				relation: 'related',
				note: '',
				revision: 'read-revision',
				group_uuid: '',
			},
		],
	} );
	await expect( readMappings( 10 ) ).resolves.toEqual( [
		{
			shared_id: 20,
			name: 'Number sense',
			relation: 'related',
			note: '',
			revision: 'read-revision',
			combined: false,
		},
	] );
	expect( transport.get ).toHaveBeenCalledWith(
		'/ohmylms/v1/skill-mappings',
		{ query: { skill_id: 10 }, signal: undefined }
	);
} );
