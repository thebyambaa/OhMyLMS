import { post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	changeArchive,
	changeLink,
	listSkills,
	parseSummary,
	saveSkill,
} from './api';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../lib/api' ) >() ),
	post: vi.fn(),
	put: vi.fn(),
} ) );
vi.mock( '../../lib/api/collection', () => ( { getCollection: vi.fn() } ) );
const skill = {
	id: 7,
	name: 'Algebra',
	code: 'M1',
	description: 'Notes',
	parent: 0,
	prerequisites: [ 8 ],
	archived: false,
};
describe( 'Skills API boundary', () => {
	it( 'rejects malformed server fields and accepts literal text', () => {
		expect(
			parseSummary( { ...skill, name: '<script>text</script>' } ).name
		).toBe( '<script>text</script>' );
		for ( const malformed of [
			{ ...skill, id: 0 },
			{ ...skill, archived: 1 },
			{ ...skill, prerequisites: [ -1 ] },
			{ ...skill, parent: '0' },
		] ) {
			expect( () => parseSummary( malformed ) ).toThrow();
		}
	} );
	it( 'requests bounded summaries rather than the whole detailed catalogue', async () => {
		await listSkills( 2, 'Algebra' );
		expect( getCollection ).toHaveBeenCalledWith(
			'/ohmylms/v1/skills/catalog',
			expect.any( Function ),
			expect.objectContaining( {
				query: {
					page: 2,
					per_page: 20,
					search: 'Algebra',
					include: [],
				},
			} )
		);
	} );
	it( 'sends only writable fields and rejects an invalid save response', async () => {
		vi.mocked( put ).mockResolvedValueOnce( skill );
		await saveSkill( skill, 7 );
		expect( put ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/skills/7',
			{
				name: 'Algebra',
				code: 'M1',
				description: 'Notes',
				parent: 0,
				prerequisites: [ 8 ],
			},
			expect.any( Object )
		);
		vi.mocked( post ).mockResolvedValueOnce( { id: '7' } );
		await expect( saveSkill( skill, null ) ).rejects.toThrow();
	} );
	it( 'validates archive and single-link responses', async () => {
		vi.mocked( post ).mockResolvedValueOnce( { id: 7, archived: false } );
		await expect( changeArchive( 7, true ) ).rejects.toThrow();
		vi.mocked( put ).mockResolvedValueOnce( { id: 9, linked: true } );
		await changeLink( 7, 'lessons', {
			id: 9,
			title: 'Lesson',
			linked: false,
		} );
		expect( put ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/skills/7/relationships/lessons/9',
			{ linked: true },
			expect.any( Object )
		);
	} );
} );
