import { ApiError, post } from '../../lib/api';
import { addLessonForSkill, parseCoverage } from './coverage-api';

vi.mock( '../../lib/api', async ( importOriginal ) => ( {
	...( await importOriginal< typeof import( '../../lib/api' ) >() ),
	post: vi.fn(),
	get: vi.fn(),
} ) );

const skill = ( over: Record< string, unknown > = {} ) => ( {
	term_id: 5,
	name: 'Add halves',
	code: 'F.1',
	outcome: true,
	required: true,
	target: 'mastered',
	objective: false,
	practice: false,
	teaching: [
		{
			id: 9,
			type: 'lesson',
			title: 'Halves',
			unit_id: 3,
			unit: 'Fractions',
		},
	],
	assessing: [],
	questions: 5,
	questions_need: 5,
	families: 3,
	families_need: 3,
	need_basis: 'target',
	teaching_ok: true,
	questions_ok: true,
	status: 'covered',
	...over,
} );

const report = ( skills: unknown[] = [ skill() ], over = {} ) => ( {
	course_id: 7,
	revision: 'abc123',
	units: [
		{ id: 3, title: 'Fractions' },
		{ id: 4, title: 'Decimals' },
	],
	summary: {
		skills: skills.length,
		required: 1,
		covered: 1,
		needs_teaching: 0,
		needs_questions: 0,
		needs_both: 0,
		objectives: 2,
		objectives_taught: 1,
		required_not_ready: 0,
	},
	skills,
	...over,
} );

describe( 'parseCoverage', () => {
	it( 'reads a report into camelCase fields', () => {
		const parsed = parseCoverage( report() );
		expect( parsed.courseId ).toBe( 7 );
		expect( parsed.summary ).toMatchObject( {
			needsTeaching: 0,
			objectivesTaught: 1,
			requiredNotReady: 0,
		} );
		expect( parsed.skills[ 0 ] ).toMatchObject( {
			id: 5,
			name: 'Add halves',
			required: true,
			target: 'mastered',
			questionsNeed: 5,
			familiesNeed: 3,
			needBasis: 'target',
			status: 'covered',
		} );
		expect( parsed.skills[ 0 ]!.teaching[ 0 ] ).toEqual( {
			id: 9,
			type: 'lesson',
			title: 'Halves',
			unitId: 3,
			unit: 'Fractions',
		} );
	} );

	it( 'reads the revision and the units a fix can use', () => {
		const parsed = parseCoverage( report() );
		expect( parsed.revision ).toBe( 'abc123' );
		expect( parsed.units ).toEqual( [
			{ id: 3, title: 'Fractions' },
			{ id: 4, title: 'Decimals' },
		] );
	} );

	it( 'treats a missing need basis as the default', () => {
		expect(
			parseCoverage( report( [ skill( { need_basis: 'default' } ) ] ) )
				.skills[ 0 ]!.needBasis
		).toBe( 'default' );
		expect(
			parseCoverage( report( [ skill( { need_basis: undefined } ) ] ) )
				.skills[ 0 ]!.needBasis
		).toBe( 'default' );
	} );

	it( 'accepts every status the server uses', () => {
		for ( const status of [
			'covered',
			'needs-teaching',
			'needs-questions',
			'needs-both',
		] ) {
			expect(
				parseCoverage( report( [ skill( { status } ) ] ) ).skills[ 0 ]!
					.status
			).toBe( status );
		}
	} );

	it( 'rejects a response it cannot trust', () => {
		for ( const bad of [
			null,
			'text',
			{ ...report(), skills: 'no' },
			{ ...report(), units: 'no' },
			{ ...report(), units: [ { id: 0, title: 'x' } ] },
			{ ...report(), course_id: 0 },
			{ ...report(), summary: { skills: -1 } },
			report( [ skill( { status: 'unknown' } ) ] ),
			report( [ skill( { questions: 'many' } ) ] ),
			report( [ skill( { term_id: 0 } ) ] ),
			report( [ skill( { teaching: [ { id: 0 } ] } ) ] ),
		] ) {
			expect( () => parseCoverage( bad ) ).toThrow( ApiError );
		}
	} );
} );

describe( 'addLessonForSkill', () => {
	const input = {
		skillId: 5,
		unitId: 3,
		title: 'Halves',
		revision: 'abc123',
	};
	const answer = ( over = {} ) => ( {
		lesson: {
			id: 40,
			title: 'Halves',
			unit_id: 3,
			edit_url:
				'http://example.test/wp-admin/post.php?post=40&action=edit',
		},
		revision: 'def456',
		report: report(),
		...over,
	} );

	beforeEach( () => vi.mocked( post ).mockReset() );

	it( 'posts the skill, unit, title and the revision the author saw', async () => {
		vi.mocked( post ).mockResolvedValue( answer() );
		const signal = new AbortController().signal;
		await addLessonForSkill( 7, input, signal );
		expect( post ).toHaveBeenCalledWith(
			'/ohmylms/v1/courses/7/skill-coverage/lessons',
			{
				skill_id: 5,
				unit_id: 3,
				title: 'Halves',
				revision: 'abc123',
			},
			{ signal }
		);
	} );

	it( 'returns the new lesson, the new revision and the updated report', async () => {
		vi.mocked( post ).mockResolvedValue( answer() );
		const result = await addLessonForSkill( 7, input );
		expect( result.lesson ).toEqual( {
			id: 40,
			title: 'Halves',
			unitId: 3,
			editUrl:
				'http://example.test/wp-admin/post.php?post=40&action=edit',
		} );
		expect( result.revision ).toBe( 'def456' );
		expect( result.report.skills[ 0 ]!.name ).toBe( 'Add halves' );
	} );

	it( 'rejects an answer for a different course or without a lesson', async () => {
		vi.mocked( post ).mockResolvedValueOnce(
			answer( { report: report( [], { course_id: 8 } ) } )
		);
		await expect( addLessonForSkill( 7, input ) ).rejects.toBeInstanceOf(
			ApiError
		);
		vi.mocked( post ).mockResolvedValueOnce( answer( { lesson: null } ) );
		await expect( addLessonForSkill( 7, input ) ).rejects.toBeInstanceOf(
			ApiError
		);
	} );
} );
