import * as transport from '../../lib/api';
import {
	command,
	compare,
	importRows,
	readSyllabus,
	saveMapping,
	removeMapping,
} from './api';
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
it( 'uses read mapping revisions for both saves and removals', async () => {
	await saveMapping( 10, 20, 'equivalent', 'Reviewed', 'read-revision' );
	expect( transport.put ).toHaveBeenCalledWith(
		'/ohmylms/v1/syllabus-comparison/mapping',
		expect.objectContaining( {
			expected_revision: 'read-revision',
			specific_id: 10,
			shared_id: 20,
		} ),
		{ signal: undefined }
	);
	await removeMapping( 10, {
		shared_id: 20,
		name: 'Shared',
		relation: 'related',
		note: '',
		revision: 'read-revision',
		combined: false,
	} );
	expect( transport.post ).toHaveBeenCalledWith(
		'/ohmylms/v1/syllabus-comparison/mapping/remove',
		{ expected_revision: 'read-revision', specific_id: 10, shared_id: 20 },
		{ signal: undefined }
	);
} );
it( 'parses explicitly mapped comparison rows and keeps unmapped objectives separate', async () => {
	vi.mocked( transport.get ).mockResolvedValue( {
		a: { id: 5, name: 'A', objectives: [ { id: 10, name: 'Count' } ] },
		b: { id: 6, name: 'B', objectives: [] },
		skills: { '20': { name: 'Number sense' } },
		rows: [ { skill: 20, a: { '10': 'equivalent' }, b: [] } ],
		both: [],
		only_a: [ 20 ],
		only_b: [],
		unmapped_a: [],
		unmapped_b: [ 30 ],
		overlap: 0,
	} );
	expect( await compare( 5, 6 ) ).toMatchObject( {
		onlyA: 1,
		unmappedB: 1,
		rows: [ { name: 'Number sense', a: [ 'Count' ], b: [] } ],
	} );
} );
