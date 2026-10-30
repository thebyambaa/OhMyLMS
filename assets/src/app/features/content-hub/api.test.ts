import { post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	changeArchive,
	changeLink,
	draftOf,
	listSkills,
	parseDetail,
	parseSummary,
	rebase,
	saveSkill,
	skillPatch,
	updateSkill,
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
	it( 'parses editor details and rejects malformed ones', () => {
		const value = {
			skill: {
				...skill,
				objective: '',
				effective_objective: 'Notes',
				public_practice: false,
				translations: { mn: { name: 'Алгебр' } },
			},
			revision: 'b'.repeat( 64 ),
			resources: { lessons: 1, courses: 0, questions: 3 },
			permissions: { public_practice: false },
		};
		const detail = parseDetail( value );
		expect( detail.skill.translations.mn ).toEqual( {
			name: 'Алгебр',
			objective: '',
			description: '',
		} );
		expect( detail.skill.translations.en.name ).toBe( '' );
		for ( const malformed of [
			{ ...value, revision: 'short' },
			{ ...value, resources: { ...value.resources, lessons: -1 } },
			{ ...value, skill: { ...value.skill, translations: [] } },
			{
				...value,
				skill: { ...value.skill, translations: { en: { name: 3 } } },
			},
			{ ...value, permissions: {} },
		] ) {
			expect( () => parseDetail( malformed ) ).toThrow();
		}
	} );
	it( 'sends only changed fields with the read revision', async () => {
		const baseline = draftOf(
			parseDetail( {
				skill: {
					...skill,
					objective: 'Old',
					effective_objective: 'Old',
					public_practice: false,
					translations: { en: { name: 'Algebra', objective: 'Old' } },
				},
				revision: 'c'.repeat( 64 ),
				resources: { lessons: 0, courses: 0, questions: 0 },
				permissions: { public_practice: true },
			} )
		);
		const draft = {
			...baseline,
			objective: ' New ',
			translations: {
				...baseline.translations,
				mn: { name: 'Алгебр', objective: '', description: '' },
			},
		};
		expect( skillPatch( draft, baseline ) ).toEqual( {
			objective: 'New',
			translations: { mn: { name: 'Алгебр' } },
		} );
		expect( () =>
			skillPatch(
				{
					...baseline,
					translations: {
						...baseline.translations,
						en: { ...baseline.translations.en, name: '' },
					},
				},
				baseline
			)
		).toThrow( /cannot be cleared/ );
		vi.mocked( put ).mockResolvedValueOnce( { id: 7 } );
		await expect(
			updateSkill( 7, draft, baseline, 'c'.repeat( 64 ) )
		).rejects.toThrow();
		expect( put ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/authoring/skills/7',
			{
				objective: 'New',
				translations: { mn: { name: 'Алгебр' } },
				expected_revision: 'c'.repeat( 64 ),
			},
			expect.any( Object )
		);
	} );
	it( 'keeps local edits and takes fresh values for untouched fields', () => {
		const baseline = {
			...skill,
			objective: 'A',
			public_practice: false,
			translations: {
				en: { name: '', objective: '', description: '' },
				mn: { name: 'Old', objective: '', description: '' },
			},
		};
		const draft = { ...baseline, objective: 'Mine' };
		const fresh = {
			...baseline,
			code: 'Server',
			objective: 'Theirs',
			translations: {
				...baseline.translations,
				mn: { name: 'New', objective: '', description: '' },
			},
		};
		const merged = rebase( draft, baseline, fresh );
		expect( merged.objective ).toBe( 'Mine' );
		expect( merged.code ).toBe( 'Server' );
		expect( merged.translations.mn.name ).toBe( 'New' );
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
