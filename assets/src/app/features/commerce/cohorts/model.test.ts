import {
	cohortBody,
	cohortDraft,
	emptyCohortDraft,
	parseCohort,
	seatsLabel,
	stateLabel,
	validateCohort,
} from './model';
import { cohortRow } from './fixtures';

describe( 'cohort model', () => {
	it( 'parses a cohort and refuses a malformed one', () => {
		const cohort = parseCohort( cohortRow );
		expect( cohort ).toMatchObject( {
			id: 5,
			scope: 'course',
			capacity: 20,
			enrollment_open: true,
		} );
		expect( () =>
			parseCohort( { ...cohortRow, scope: 'class' } )
		).toThrow();
		expect( () =>
			parseCohort( { ...cohortRow, capacity: '20' } )
		).toThrow();
	} );

	it( 'validates like the server', () => {
		const empty = validateCohort( emptyCohortDraft() );
		expect( Object.keys( empty ).sort() ).toEqual( [
			'course_id',
			'start_date',
			'title',
		] );
		const draft = {
			...emptyCohortDraft(),
			title: 'A',
			targetId: 40,
			start: '2026-11-02T09:00',
		};
		expect( validateCohort( draft ) ).toEqual( {} );
		expect(
			validateCohort( { ...draft, end: '2026-11-01T09:00' } ).end_date
		).toBeTruthy();
		expect(
			validateCohort( {
				...draft,
				end: '2026-11-20T09:00',
				enrollmentEnd: '2026-11-21T09:00',
			} ).enrollment_end
		).toBeTruthy();
		expect(
			validateCohort( {
				...draft,
				enrollmentStart: '2026-11-05T00:00',
				enrollmentEnd: '2026-11-04T00:00',
			} ).enrollment_start
		).toBeTruthy();
		expect(
			validateCohort( { ...draft, capacity: '0' } ).capacity
		).toBeTruthy();
		expect(
			validateCohort( { ...draft, capacity: '2.5' } ).capacity
		).toBeTruthy();
		expect( validateCohort( { ...draft, capacity: '' } ) ).toEqual( {} );
	} );

	it( 'creates with the scope and updates only what changed', () => {
		const draft = {
			...emptyCohortDraft(),
			title: ' Batch ',
			scope: 'membership' as const,
			targetId: 9,
			start: '2026-11-02T09:00',
			capacity: '5',
		};
		expect( cohortBody( draft ) ).toEqual( {
			scope: 'membership',
			membership_id: 9,
			title: 'Batch',
			start_date: '2026-11-02T09:00',
			end_date: null,
			enrollment_start: null,
			enrollment_end: null,
			capacity: 5,
			status: 'active',
			close_access_at_end: false,
		} );
		const cohort = parseCohort( cohortRow );
		const edit = cohortDraft( cohort );
		expect( cohortBody( edit, cohort ) ).toEqual( {} );
		expect(
			cohortBody(
				{ ...edit, capacity: '', closeAccessAtEnd: true },
				cohort
			)
		).toEqual( { capacity: null, close_access_at_end: true } );
	} );

	it( 'labels state and seats', () => {
		const cohort = parseCohort( cohortRow );
		expect( stateLabel( cohort ) ).toBe( 'Upcoming' );
		expect( stateLabel( { ...cohort, status: 'archived' } ) ).toBe(
			'Archived'
		);
		expect( seatsLabel( cohort ) ).toBe( '3 of 20' );
		expect( seatsLabel( { ...cohort, capacity: null } ) ).toBe(
			'3 (no limit)'
		);
	} );
} );
