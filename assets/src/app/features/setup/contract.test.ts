import contract from './contract.json';
import { LIMITS } from './model';
import {
	CURRENCY_POSITIONS,
	EXPERIENCES,
	NICHES,
	STATUSES,
	STEP_IDS,
} from './types';

describe( 'setup REST contract', () => {
	it( 'describes every route the client calls', () => {
		expect(
			contract.routes.map(
				( route ) => `${ route.method } ${ route.path }`
			)
		).toEqual( [
			'GET /setup',
			'POST /setup/steps/{step}',
			'POST /setup/steps/{step}/skip',
			'POST /setup/complete',
			'POST /setup/dismiss',
			'POST /setup/reset',
		] );
		expect( contract.capability ).toBe( 'manage_options' );
	} );

	it( 'lists the same steps and statuses as the client types', () => {
		expect( contract.stepIds ).toEqual( [ ...STEP_IDS ] );
		expect( Object.keys( contract.steps ) ).toEqual( [ ...STEP_IDS ] );
		expect( contract.statuses ).toEqual( [ ...STATUSES ] );
	} );

	it( 'matches the enums and limits used by the forms', () => {
		const steps = contract.steps;
		expect( steps.experience.schema.properties.experience.enum ).toEqual( [
			...EXPERIENCES,
		] );
		expect( steps.niche.schema.properties.niche.enum ).toEqual( [
			...NICHES,
		] );
		expect( steps.niche.schema.properties.topics.maxItems ).toBe(
			LIMITS.maxTopics
		);
		expect( steps.niche.schema.properties.topics.items.maxLength ).toBe(
			LIMITS.maxTopicChars
		);
		expect( steps.preferences.schema.properties.currency_pos.enum ).toEqual(
			[ ...CURRENCY_POSITIONS ]
		);
		expect(
			steps.preferences.schema.properties.price_num_decimals.maximum
		).toBe( LIMITS.maxDecimals );
	} );

	it( 'marks only import as unavailable and welcome/experience as required', () => {
		for ( const id of STEP_IDS ) {
			expect( contract.steps[ id ].available ).toBe( 'import' !== id );
			expect( contract.steps[ id ].skippable ).toBe(
				'welcome' !== id && 'experience' !== id
			);
		}
	} );
} );
