import { questionList, sharedSlot, slot } from './fixtures';
import {
	addBlocker,
	deliveryLabel,
	moveQuestion,
	parseQuizQuestionCreated,
	parseQuizQuestions,
	slotName,
	slotWarning,
} from './model';

describe( 'question list parser', () => {
	it( 'accepts the documented shape', () => {
		expect( parseQuizQuestions( questionList ) ).toEqual( questionList );
		const pool = {
			id: 'pool-1',
			title: 'Warm-up',
			skill: { id: 7, name: 'Fractions' },
			count: 3,
			marks: 2,
			difficulty: '',
			bank_id: 0,
			include_secure: true,
		};
		expect(
			parseQuizQuestions( { ...questionList, pools: [ pool ] } ).pools
		).toEqual( [ pool ] );
	} );
	it.each( [
		[ 'revision', { revision: 'ABC' } ],
		[ 'questions', { questions: {} } ],
		[ 'totals', { totals: null } ],
		[ 'slot status', { questions: [ { ...slot, status: 'gone' } ] } ],
		[
			'delivery',
			{
				questions: [
					{ ...slot, delivery: { ...slot.delivery, source: 'x' } },
				],
			},
		],
		[
			'permissions',
			{ questions: [ { ...slot, permissions: { can_edit: 1 } } ] },
		],
	] )( 'rejects an invalid %s', ( _label, patch ) => {
		expect( () =>
			parseQuizQuestions( { ...questionList, ...patch } )
		).toThrowError(
			expect.objectContaining( { code: 'invalid_response' } )
		);
	} );
	it( 'requires the created question to be in the list', () => {
		expect(
			parseQuizQuestionCreated( { ...questionList, created_id: 102 } )
				.created_id
		).toBe( 102 );
		expect( () =>
			parseQuizQuestionCreated( { ...questionList, created_id: 5 } )
		).toThrowError(
			expect.objectContaining( { code: 'invalid_response' } )
		);
	} );
} );

describe( 'question list helpers', () => {
	it( 'moves one question and refuses moves off the list', () => {
		expect( moveQuestion( [ 1, 2, 3 ], 0, 1 ) ).toEqual( [ 2, 1, 3 ] );
		expect( moveQuestion( [ 1, 2, 3 ], 2, -1 ) ).toEqual( [ 1, 3, 2 ] );
		expect( moveQuestion( [ 1, 2, 3 ], 0, -1 ) ).toBeNull();
		expect( moveQuestion( [ 1, 2, 3 ], 2, 1 ) ).toBeNull();
		expect( moveQuestion( [ 1, 2, 3 ], 5, -1 ) ).toBeNull();
	} );
	it( 'names restricted and untitled questions', () => {
		expect( slotName( slot ) ).toBe( 'Pick the larger fraction' );
		expect( slotName( { ...slot, name: ' ' } ) ).toBe(
			'Untitled question'
		);
		expect(
			slotName( {
				...slot,
				name: '',
				permissions: { can_edit: false, can_use: false },
			} )
		).toBe( 'Restricted question #101' );
	} );
	it( 'describes the delivered version', () => {
		expect( deliveryLabel( slot ) ).toBe( 'Latest version (3)' );
		expect( deliveryLabel( sharedSlot ) ).toBe( 'Pinned to version 1' );
		expect(
			deliveryLabel( {
				...slot,
				delivery: { source: 'approved', version_id: 9, version_no: 2 },
			} )
		).toBe( 'Approved version 2' );
		expect(
			deliveryLabel( {
				...slot,
				delivery: {
					source: 'unavailable',
					version_id: 0,
					version_no: 0,
				},
			} )
		).toMatch( /No approved version/ );
		expect( slotWarning( slot ) ).toBe( '' );
		expect( slotWarning( { ...slot, status: 'archived' } ) ).toBe(
			'Archived'
		);
	} );
	it( 'blocks questions that cannot be added', () => {
		const inQuiz = new Set( [ 101 ] );
		const row = { id: 7, status: 'draft', permissions: { can_edit: true } };
		expect( addBlocker( row, inQuiz ) ).toBeNull();
		expect( addBlocker( { ...row, id: 101 }, inQuiz ) ).toBe(
			'Already in this quiz'
		);
		expect( addBlocker( { ...row, status: 'archived' }, inQuiz ) ).toBe(
			'Archived'
		);
		expect(
			addBlocker( { ...row, permissions: { can_edit: false } }, inQuiz )
		).toMatch( /without an approved version/ );
		expect(
			addBlocker(
				{
					...row,
					status: 'approved',
					permissions: { can_edit: false },
				},
				inQuiz
			)
		).toBeNull();
	} );
} );
