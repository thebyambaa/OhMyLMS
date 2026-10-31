import * as transport from '../../lib/api';
import {
	compareSyllabuses,
	removeSkillMapping,
	saveSkillMapping,
	searchSharedSkills,
} from './mappings';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
	put: vi.fn(),
} ) );
beforeEach( () => vi.resetAllMocks() );

it( 'searches shared skills by name and page', async () => {
	vi.mocked( transport.get ).mockResolvedValue( {
		total: 31,
		items: [ { id: 20, name: 'Number sense', subject: 'Maths' } ],
	} );
	await expect( searchSharedSkills( 'num', 2 ) ).resolves.toEqual( {
		total: 31,
		items: [ { id: 20, name: 'Number sense' } ],
	} );
	expect( transport.get ).toHaveBeenCalledWith(
		'/ohmylms/v1/syllabus-comparison/shared-skills',
		{ query: { search: 'num', page: 2 }, signal: undefined }
	);
	vi.mocked( transport.get ).mockResolvedValue( { total: -1, items: [] } );
	await expect( searchSharedSkills( '', 1 ) ).rejects.toMatchObject( {
		code: 'invalid_response',
	} );
} );
it( 'uses read mapping revisions for both saves and removals', async () => {
	await saveSkillMapping( 10, 20, 'equivalent', 'Reviewed', 'read-revision' );
	expect( transport.put ).toHaveBeenCalledWith(
		'/ohmylms/v1/syllabus-comparison/mapping',
		expect.objectContaining( {
			expected_revision: 'read-revision',
			specific_id: 10,
			shared_id: 20,
		} ),
		{ signal: undefined }
	);
	await removeSkillMapping( 10, 20, 'read-revision' );
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
	expect( await compareSyllabuses( 5, 6 ) ).toMatchObject( {
		onlyA: 1,
		unmappedB: 1,
		rows: [ { name: 'Number sense', a: [ 'Count' ], b: [] } ],
	} );
	expect( transport.get ).toHaveBeenCalledWith(
		'/ohmylms/v1/syllabus-comparison/compare',
		{ query: { a: 5, b: 6 }, signal: undefined }
	);
	await expect( compareSyllabuses( 5, 7 ) ).rejects.toMatchObject( {
		code: 'invalid_response',
	} );
} );
