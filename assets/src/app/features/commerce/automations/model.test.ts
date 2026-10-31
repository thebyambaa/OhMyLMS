import {
	emptyRuleDraft,
	keepConditions,
	newAction,
	parseOptions,
	parseRule,
	ruleBody,
	ruleDraft,
	validateRule,
} from './model';
import { optionsBody, ruleRow } from './fixtures';

describe( 'automation model', () => {
	it( 'parses rules, including the empty conditions object PHP may send as a list', () => {
		const rule = parseRule( ruleRow );
		expect( rule.conditions ).toEqual( { course_ids: [ 40 ] } );
		expect( rule.actions[ 1 ] ).toEqual( {
			type: 'enrol_course',
			course_id: 41,
		} );
		expect(
			parseRule( { ...ruleRow, conditions: [] } ).conditions
		).toEqual( {} );
		expect( () =>
			parseRule( {
				...ruleRow,
				actions: [ { type: 'run_php', code: 'x' } ],
			} )
		).toThrow();
	} );

	it( 'validates names, rates and action parameters like the server', () => {
		const options = parseOptions( optionsBody );
		const draft = emptyRuleDraft( options );
		const errors = validateRule( draft, options );
		expect( errors.name ).toBeTruthy();
		expect( errors[ 'actions.0.subject' ] ).toBeTruthy();
		expect( errors[ 'actions.0.body' ] ).toBeTruthy();
		const progress = validateRule(
			{
				...draft,
				name: 'Halfway',
				event: 'course_progress',
				actions: [ { type: 'enrol_course', course_id: 0 } ],
			},
			options
		);
		expect( progress[ 'conditions.rate' ] ).toBeTruthy();
		expect( progress[ 'actions.0.course_id' ] ).toBeTruthy();
		const coupon = validateRule(
			{
				...draft,
				name: 'Coupon',
				actions: [
					{
						type: 'send_coupon',
						coupon_id: 3,
						subject: 'Gift',
						body: 'No code here',
					},
				],
			},
			options
		);
		expect( coupon[ 'actions.0.body' ] ).toMatch( /coupon_code/ );
		expect(
			validateRule(
				{
					...draft,
					name: 'Too many',
					actions: Array( 6 ).fill( newAction( 'enrol_course' ) ),
				},
				options
			).actions
		).toBeTruthy();
	} );

	it( 'drops conditions the new event does not support and empty lists from the body', () => {
		expect(
			keepConditions( { course_ids: [ 1 ], result: 'fail', rate: 50 }, [
				'course_ids',
				'rate',
			] )
		).toEqual( { course_ids: [ 1 ], rate: 50 } );
		const draft = ruleDraft( parseRule( ruleRow ) );
		expect(
			ruleBody( {
				...draft,
				name: ' Welcome ',
				conditions: { course_ids: [] },
			} )
		).toEqual( {
			name: 'Welcome',
			status: 'active',
			event: 'course_enrolled',
			conditions: {},
			actions: draft.actions,
		} );
	} );

	it( 'starts coupon actions with the code placeholder', () => {
		const action = newAction( 'send_coupon' );
		expect( 'send_coupon' === action.type && action.body ).toMatch(
			/\{coupon_code\}/
		);
	} );
} );
