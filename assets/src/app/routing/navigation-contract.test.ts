import { createRequire } from 'node:module';
import navigation from '../../../../tests/fixtures/admin-navigation.json';
import legacy from '../../../../tests/fixtures/legacy-routes.json';
import { TAB_IDS as ACCOUNT_TABS } from '../features/account-hub/types';
import { TAB_IDS as GAMIFICATION_TABS } from '../features/gamification/types';
import { features } from '../features/registry';
import { EXTRA_TAB_IDS, GROUP_IDS } from '../features/settings/types';
import type { FeatureDefinition } from '../features/types';
import {
	formatQuery,
	fromAddress,
	GROUP_PAGES,
	parseQuery,
	toAddress,
} from './address';

/**
 * The TypeScript registry and includes/Admin/AdminNavigation.php describe the same admin pages:
 * tests/fixtures/admin-navigation.json is generated from PHP (tests/php/legacy-routes-integration.php
 * checks it) and compared with the registry here.
 */

interface FixtureTab {
	id: string;
	capability: string;
	flag: string;
	sections: string[];
	serverSections: string[];
	defaultSection: string;
}

const groups = navigation.groups as {
	id: string;
	page: string;
	tabs: FixtureTab[];
}[];

const require = createRequire( import.meta.url );
const { resolve: resolveLegacy } =
	require( '../../../js/legacy-redirects.js' ) as {
		resolve: ( routes: unknown, route: string ) => string | null;
	};

function entry( feature: FeatureDefinition ) {
	const row: Record< string, unknown > = {
		id: feature.id,
		path: feature.path,
		group: feature.group,
	};
	for ( const key of [
		'tab',
		'tabs',
		'section',
		'view',
		'params',
	] as const ) {
		if ( undefined !== feature[ key ] ) {
			row[ key ] = feature[ key ];
		}
	}
	return row;
}

function sample( path: string ) {
	// Extension page ids are slugs, so the sample value starts with a letter.
	return path.replace( /\/:[A-Za-z]+\?$/, '' ).replace( /:[A-Za-z]+/g, 'x7' );
}

describe( 'admin navigation contract', () => {
	it( 'the registry and the PHP route table agree, entry by entry', () => {
		expect( features.map( entry ) ).toEqual( navigation.routes );
	} );

	it( 'the pages are the same in PHP and in the address format', () => {
		expect(
			Object.fromEntries(
				groups.map( ( group ) => [ group.id, group.page ] )
			)
		).toEqual( GROUP_PAGES );
	} );

	it( 'every PHP tab is a screen of the app, with the capability of that screen', () => {
		for ( const group of groups ) {
			for ( const tab of group.tabs ) {
				const own = features.find(
					( feature ) =>
						feature.group === group.id &&
						( feature.tab === tab.id ||
							feature.tabs?.includes( tab.id ) ) &&
						! feature.section &&
						! feature.view &&
						! feature.path
							.split( '/' )
							.some(
								( part ) =>
									part.startsWith( ':' ) &&
									! part.endsWith( '?' )
							)
				);
				expect( own, `${ group.id }/${ tab.id }` ).toBeDefined();
				expect(
					own?.capability ?? 'manage_options',
					`${ group.id }/${ tab.id }`
				).toBe( tab.capability );
			}
		}
	} );

	it( 'every PHP section is a screen of the app or rendered by the server', () => {
		for ( const group of groups ) {
			for ( const tab of group.tabs ) {
				for ( const section of tab.sections.filter( Boolean ) ) {
					if ( tab.serverSections.includes( section ) ) {
						continue;
					}
					const found = fromAddress( features, [
						[ 'page', group.page ],
						[ 'tab', tab.id ],
						[ 'section', section ],
					] );
					expect(
						found?.route,
						`${ group.id }/${ tab.id }/${ section }`
					).toBeDefined();
				}
			}
		}
		const tabsOf = ( group: string, tab: string ) =>
			groups
				.find( ( item ) => item.id === group )
				?.tabs.find( ( item ) => item.id === tab )?.sections;
		expect( tabsOf( 'settings', 'settings' ) ).toEqual( [
			...GROUP_IDS,
			...EXTRA_TAB_IDS,
		] );
		expect( tabsOf( 'engagement', 'gamification' ) ).toEqual( [
			...GAMIFICATION_TABS,
			'certificates',
			'issued-certificates',
		] );
		expect(
			groups
				.find( ( item ) => item.id === 'accounts' )
				?.tabs.map( ( tab ) => tab.id )
		).toEqual( [ 'students', ...ACCOUNT_TABS ] );
	} );

	it( 'every feature route has an address on its page that opens the same feature', () => {
		for ( const feature of features ) {
			const route = sample( feature.path );
			const pairs = toAddress( features, route );
			expect( pairs, route ).not.toBeNull();
			expect( pairs?.[ 0 ], route ).toEqual( [
				'page',
				GROUP_PAGES[ feature.group ],
			] );
			expect(
				fromAddress( features, pairs ?? [] )?.feature.id,
				route
			).toBe( feature.id );
		}
	} );

	it( 'builds and reads every shared case like AdminNavigation::address()', () => {
		for ( const [ route, address, canonical ] of navigation.cases as [
			string,
			string | null,
			string?,
		][] ) {
			const pairs = toAddress( features, route );
			expect( pairs ? formatQuery( pairs ) : null, route ).toBe(
				address
			);
			if ( null !== address ) {
				expect(
					fromAddress( features, parseQuery( address ) )?.route,
					address
				).toBe( canonical ?? route );
			}
		}
	} );

	it( 'lands every legacy address of the shared fixture on its new address', () => {
		for ( const [ route, address ] of legacy.addresses as [
			string,
			string | null,
		][] ) {
			const mapped = resolveLegacy( legacy.routes, route || '/' );
			const pairs =
				null === mapped ? null : toAddress( features, mapped );
			expect( pairs ? formatQuery( pairs ) : null, route ).toBe(
				address
			);
		}
	} );
} );
