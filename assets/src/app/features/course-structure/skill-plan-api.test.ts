import { ApiError, get, put } from '../../lib/api';
import {
	getSkillPlan,
	linkContentSkills,
	parsePlan,
	setUnitTopics,
} from './skill-plan-api';

vi.mock( '../../lib/api', async ( importOriginal ) => ( {
	...( await importOriginal< typeof import( '../../lib/api' ) >() ),
	get: vi.fn(),
	put: vi.fn(),
} ) );

const answer = ( over: Record< string, unknown > = {} ) => ( {
	course_id: 7,
	revision: 'abc',
	curricula: [ { id: 1, name: 'Digital SAT' } ],
	curriculum: 1,
	topics: [
		{
			id: 10,
			name: 'Algebra',
			depth: 1,
			groups: [ { id: 100, name: 'Linear', skills: [ 5, 6 ] } ],
		},
	],
	names: {
		5: { name: 'One-step', code: 'A.1' },
		6: { name: 'Two-step', code: '' },
	},
	skills: {
		5: {
			name: 'One-step',
			code: 'A.1',
			status: 'covered',
			teaching: [
				{
					id: 20,
					type: 'lesson',
					title: 'Halves',
					unit_id: 3,
					unit: 'Fractions',
				},
			],
			assessing: [
				{
					id: 30,
					type: 'quiz',
					title: 'Quiz',
					unit_id: 3,
					unit: 'Fractions',
				},
			],
			questions: 4,
			questions_need: 3,
			teaching_ok: true,
			questions_ok: true,
		},
	},
	units: [],
	unit_topics: { 3: [ 10 ] },
	...over,
} );

describe( 'parsePlan', () => {
	it( 'reads topics, names, teaching and unit topics', () => {
		const plan = parsePlan( answer() );
		expect( plan.curriculum ).toBe( 1 );
		expect( plan.topics[ 0 ]!.groups[ 0 ]!.skills ).toEqual( [ 5, 6 ] );
		expect( plan.names[ 6 ] ).toEqual( { name: 'Two-step', code: '' } );
		expect( plan.unitTopics ).toEqual( { 3: [ 10 ] } );
		expect( plan.skills[ 5 ]!.questionsNeed ).toBe( 3 );
	} );

	it( 'keeps only lessons as what teaches a skill, and quizzes as what assesses it', () => {
		const skill = parsePlan( answer() ).skills[ 5 ]!;
		expect( skill.teaching ).toEqual( [
			{ id: 20, title: 'Halves', unitId: 3, unit: 'Fractions' },
		] );
		expect( skill.assessing ).toEqual( [
			{ id: 30, title: 'Quiz', unitId: 3, unit: 'Fractions' },
		] );
	} );

	it( 'accepts the empty arrays PHP sends for empty maps', () => {
		const plan = parsePlan(
			answer( { names: [], skills: [], unit_topics: [], topics: [] } )
		);
		expect( plan.names ).toEqual( {} );
		expect( plan.skills ).toEqual( {} );
		expect( plan.unitTopics ).toEqual( {} );
	} );

	it( 'rejects a response it cannot trust', () => {
		for ( const bad of [
			null,
			{ ...answer(), curricula: 'no' },
			{ ...answer(), course_id: 0 },
			{ ...answer(), topics: [ { id: 1, name: 'x', depth: 0 } ] },
			{ ...answer(), unit_topics: { 3: 'no' } },
			{ ...answer(), skills: { 5: { status: 'x', teaching: 'no' } } },
		] ) {
			expect( () => parsePlan( bad ) ).toThrow( ApiError );
		}
	} );
} );

describe( 'the skill plan requests', () => {
	beforeEach( () => vi.resetAllMocks() );

	it( 'reads the course default, or the chosen curriculum', async () => {
		vi.mocked( get ).mockResolvedValue( answer() );
		const signal = new AbortController().signal;
		await getSkillPlan( 7, 0, signal );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses/7/skill-plan',
			{ signal }
		);
		await getSkillPlan( 7, 2 );
		expect( get ).toHaveBeenLastCalledWith(
			'/ohmylms/v1/courses/7/skill-plan?curriculum=2',
			{ signal: undefined }
		);
	} );

	it( 'rejects a plan for another course', async () => {
		vi.mocked( get ).mockResolvedValue( answer( { course_id: 8 } ) );
		await expect( getSkillPlan( 7, 0 ) ).rejects.toBeInstanceOf( ApiError );
	} );

	it( 'sends the topics of a unit and returns every unit topics', async () => {
		vi.mocked( put ).mockResolvedValue( {
			unit_topics: { 3: [ 10, 11 ] },
		} );
		expect( await setUnitTopics( 7, 3, [ 10, 11 ] ) ).toEqual( {
			3: [ 10, 11 ],
		} );
		expect( put ).toHaveBeenCalledWith(
			'/ohmylms/v1/courses/7/skill-plan/units/3',
			{ topic_ids: [ 10, 11 ] },
			{ signal: undefined }
		);
	} );

	it( 'connects and disconnects a lesson, returning its skills and the teaching map', async () => {
		vi.mocked( put ).mockResolvedValue( {
			content_skills: [ 5 ],
			skills: answer().skills,
		} );
		const result = await linkContentSkills( 7, 'lesson', 20, {
			add: [ 5 ],
		} );
		expect( put ).toHaveBeenCalledWith(
			'/ohmylms/v1/courses/7/skill-plan/lessons/20',
			{ add: [ 5 ], remove: [] },
			{ signal: undefined }
		);
		expect( result.contentSkills ).toEqual( [ 5 ] );
		expect( result.skills[ 5 ]!.name ).toBe( 'One-step' );
	} );

	it( 'connects a quiz through its own route', async () => {
		vi.mocked( put ).mockResolvedValue( {
			content_skills: [ 5 ],
			skills: answer().skills,
		} );
		await linkContentSkills( 7, 'quiz', 50, { remove: [ 6 ] } );
		expect( put ).toHaveBeenCalledWith(
			'/ohmylms/v1/courses/7/skill-plan/quizzes/50',
			{ add: [], remove: [ 6 ] },
			{ signal: undefined }
		);
	} );
} );
