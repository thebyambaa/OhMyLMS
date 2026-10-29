import { lazy } from '@wordpress/element';
import { features, findRegistryProblems, getVisibleFeatures } from './registry';
import type { FeatureDefinition } from './types';

function feature( overrides: Partial< FeatureDefinition > ): FeatureDefinition {
	return {
		id: 'x',
		title: 'X',
		path: '/x',
		component: lazy( () => Promise.resolve( { default: () => null } ) ),
		...overrides,
	};
}

describe( 'feature registry', () => {
	it( 'registers the home demo feature and is itself valid', () => {
		expect( features.map( ( item ) => item.id ) ).toContain( 'home' );
		expect( findRegistryProblems( features ) ).toEqual( [] );
	} );

	it( 'reports duplicate ids, duplicate paths and bad paths', () => {
		const problems = findRegistryProblems( [
			feature( { id: 'a', path: '/a' } ),
			feature( { id: 'a', path: '/a' } ),
			feature( { id: 'b', path: 'b' } ),
			feature( { id: 'c', path: '/c/*' } ),
		] );

		expect( problems ).toEqual( [
			'Duplicate feature id "a".',
			'Duplicate feature path "/a".',
			'Feature "b" must have an absolute path without wildcards.',
			'Feature "c" must have an absolute path without wildcards.',
		] );
	} );

	it( 'hides features whose capability is missing or false', () => {
		const open = feature( { id: 'open', path: '/open' } );
		const allowed = feature( {
			id: 'allowed',
			path: '/allowed',
			capability: 'manage_options',
		} );
		const denied = feature( {
			id: 'denied',
			path: '/denied',
			capability: 'edit_users',
		} );

		const visible = getVisibleFeatures( [ open, allowed, denied ], {
			manage_options: true,
			edit_users: false,
		} );

		expect( visible.map( ( item ) => item.id ) ).toEqual( [
			'open',
			'allowed',
		] );
	} );
} );
