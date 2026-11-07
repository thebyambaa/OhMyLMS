import { ApiError } from '../../lib/api';
import { parseCoverage } from './coverage-api';

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

const report = ( skills: unknown[] = [ skill() ] ) => ( {
	course_id: 7,
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
