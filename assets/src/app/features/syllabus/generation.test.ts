import * as transport from '../../lib/api';
import { preview } from './fixtures';
import {
	createFromSyllabus,
	parsePreview,
	planCourse,
	readSources,
	sourceSkillIds,
} from './generation';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	post: vi.fn(),
} ) );
beforeEach( () => vi.resetAllMocks() );
it( 'validates the source read and keeps syllabus order', () => {
	expect( parsePreview( preview ) ).toEqual( preview );
	expect( sourceSkillIds( preview ) ).toEqual( [ 10, 11, 12 ] );
	expect( () => parsePreview( { ...preview, topics: {} } ) ).toThrow();
	expect( () =>
		parsePreview( {
			...preview,
			topics: [ { ...preview.topics[ 0 ], lessons: [ { id: 7 } ] } ],
		} )
	).toThrow();
} );
it( 'plans only the topics and groups that hold selected skills', () => {
	expect( planCourse( preview, new Set( [ 12 ] ) ) ).toEqual( [
		{
			id: 6,
			name: 'Geometry',
			lessons: [ preview.topics[ 1 ]!.lessons[ 0 ] ],
		},
	] );
	const units = planCourse( preview, new Set( [ 11, 12 ] ) );
	expect( units.map( ( unit ) => unit.name ) ).toEqual( [
		'Mathematics syllabus',
		'Geometry',
	] );
	expect(
		units[ 0 ]!.lessons[ 0 ]!.skills.map( ( skill ) => skill.term_id )
	).toEqual( [ 11 ] );
	expect( planCourse( preview, new Set() ) ).toEqual( [] );
} );
it( 'sends the reviewed selection with its source revision', async () => {
	vi.mocked( transport.post ).mockResolvedValue( {
		id: 40,
		name: 'Algebra course',
		units: [ { id: 41, name: 'Mathematics syllabus' } ],
		objectives: 2,
		lessons: 1,
	} );
	await expect(
		createFromSyllabus( {
			syllabusId: 5,
			title: 'Algebra course',
			skillIds: [ 10, 11 ],
			revision: 'source-revision',
		} )
	).resolves.toMatchObject( { id: 40, lessons: 1, objectives: 2 } );
	expect( transport.post ).toHaveBeenCalledWith(
		'/ohmylms/v1/courses/from-syllabus',
		{
			syllabus_id: 5,
			title: 'Algebra course',
			skill_ids: [ 10, 11 ],
			revision: 'source-revision',
		},
		{ signal: undefined }
	);
	vi.mocked( transport.post ).mockResolvedValue( { id: 40 } );
	await expect(
		createFromSyllabus( {
			syllabusId: 5,
			title: '',
			skillIds: [ 10 ],
			revision: 'r',
		} )
	).rejects.toMatchObject( { code: 'invalid_response' } );
} );
it( 'reads syllabus choices', async () => {
	vi.mocked( transport.get ).mockResolvedValue( [
		{
			id: 5,
			item_type: 'syllabus',
			name: 'Mathematics',
			code: '0580',
			version: '2026',
			grade: '',
			subject: 'Mathematics',
		},
	] );
	await expect( readSources() ).resolves.toEqual( [
		{
			id: 5,
			item_type: 'syllabus',
			name: 'Mathematics',
			code: '0580',
			version: '2026',
		},
	] );
} );
