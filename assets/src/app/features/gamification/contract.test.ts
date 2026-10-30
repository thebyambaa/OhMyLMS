import contract from './contract.json';
import { DEFAULT_LIMITS, IMAGE_MIME_TYPES } from './model';
import { LEADERBOARD_MODES, METRICS, OPERATORS, SECTION_IDS } from './types';

describe( 'gamification contract', () => {
	it( 'matches the limits and allow-lists used by the client', () => {
		expect( contract.limits ).toEqual( DEFAULT_LIMITS );
		expect( contract.enums.sections ).toEqual( [ ...SECTION_IDS ] );
		expect( contract.enums.leaderboard_modes ).toEqual( [
			...LEADERBOARD_MODES,
		] );
		expect( contract.enums.metrics ).toEqual( [ ...METRICS ] );
		expect( contract.enums.operators ).toEqual( [ ...OPERATORS ] );
		expect( contract.enums.image_mime_types ).toEqual( [
			...IMAGE_MIME_TYPES,
		] );
	} );

	it( 'requires the administrator capability and names every route once', () => {
		expect( contract.capability ).toBe( 'manage_options' );
		const names = contract.routes.map( ( route ) => route.name );
		expect( new Set( names ).size ).toBe( names.length );
		contract.routes.forEach( ( route ) =>
			expect( route.path ).toMatch( /^\/gamification\// )
		);
	} );
} );
