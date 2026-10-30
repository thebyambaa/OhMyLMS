import type {
	AddressSlot,
	AdminGroup,
	FeatureDefinition,
} from '../features/types';

/**
 * The address format of the admin: the only module of the app that knows it (docs/ARCHITECTURE.md,
 * "Routing"). Screens keep their internal route paths (`/courses/12/settings`, `useParams`, `Link`); an
 * address is the query string of `admin.php` (`page=ohmylms-content&tab=courses&id=12&view=settings`).
 * includes/Admin/AdminNavigation.php builds the same addresses in PHP; tests/fixtures/admin-navigation.json
 * holds the shared table and cases.
 */

/** Page slug of each group. */
export const GROUP_PAGES: Readonly< Record< AdminGroup, string > > = {
	dashboard: 'ohmylms',
	content: 'ohmylms-content',
	accounts: 'ohmylms-accounts',
	commerce: 'ohmylms-commerce',
	engagement: 'ohmylms-engagement',
	settings: 'ohmylms-settings',
};

/** Route of extension admin pages; their tab is `ext-<id>` on the page of their declared group. */
export const EXTENSION_PAGE_PATH = '/extensions/:id';

/** Group of extension admin pages that declare none. */
export const DEFAULT_EXTENSION_GROUP: AdminGroup = 'settings';

/**
 * Route query parameters stored under another address name, because the address uses the name itself:
 * list pagination `page` is `paged`, as on WordPress list screens.
 */
const RENAMED_QUERY: Readonly< Record< string, string > > = { page: 'paged' };
const RESERVED = [ 'page', 'tab', 'section', 'view' ];
const SLOTS: readonly AddressSlot[] = [ 'tab', 'section', 'view' ];

/** One query parameter, decoded. */
export type QueryPair = readonly [ string, string ];

/** Where the server placed the current document (the mount element's data attributes). */
export interface AdminPlace {
	readonly page: string;
	readonly tab: string;
	readonly section: string;
}

export interface AddressOptions {
	/** Group of an extension admin page, from `registerAdminPage( id, { group } )`. */
	readonly extensionGroup?: ( id: string ) => AdminGroup | undefined;
}

/**
 * Whether a value names one of the six groups.
 *
 * @param value Candidate.
 * @return True for a group id.
 */
export function isAdminGroup( value: unknown ): value is AdminGroup {
	return (
		'string' === typeof value &&
		Object.prototype.hasOwnProperty.call( GROUP_PAGES, value )
	);
}

/**
 * Group of a page slug.
 *
 * @param page Page slug such as `ohmylms-content`.
 * @return Group, or undefined for other pages.
 */
export function groupOfPage( page: string ): AdminGroup | undefined {
	return ( Object.keys( GROUP_PAGES ) as AdminGroup[] ).find(
		( group ) => GROUP_PAGES[ group ] === page
	);
}

function decode( value: string ): string {
	try {
		return decodeURIComponent( value.replace( /\+/g, ' ' ) );
	} catch {
		return value;
	}
}

function decodePath( value: string ): string {
	try {
		return decodeURIComponent( value );
	} catch {
		return value;
	}
}

/**
 * Encode like PHP's rawurlencode(), so both sides print the same address.
 *
 * @param value Raw value.
 * @return Encoded value.
 */
function encode( value: string ): string {
	return encodeURIComponent( value ).replace(
		/[!'()*]/g,
		( char ) => `%${ char.charCodeAt( 0 ).toString( 16 ).toUpperCase() }`
	);
}

/**
 * Split a query string into decoded pairs, keeping their order.
 *
 * @param search Query string, with or without `?`.
 * @return Pairs.
 */
export function parseQuery( search: string ): QueryPair[] {
	return search
		.replace( /^\?/, '' )
		.split( '&' )
		.filter( ( raw ) => '' !== raw )
		.map( ( raw ) => {
			const cut = raw.indexOf( '=' );
			return -1 === cut
				? ( [ decode( raw ), '' ] as const )
				: ( [
						decode( raw.slice( 0, cut ) ),
						decode( raw.slice( cut + 1 ) ),
					] as const );
		} );
}

/**
 * Join pairs into a query string.
 *
 * @param pairs Pairs.
 * @return Query string without `?`.
 */
export function formatQuery( pairs: readonly QueryPair[] ): string {
	return pairs
		.map(
			( [ name, value ] ) => `${ encode( name ) }=${ encode( value ) }`
		)
		.join( '&' );
}

/**
 * Relative link to an address (the admin runs at `wp-admin/admin.php`).
 *
 * @param pairs Address.
 * @return `admin.php?…`.
 */
export function addressHref( pairs: readonly QueryPair[] ): string {
	return `admin.php?${ formatQuery( pairs ) }`;
}

function segments( path: string ): string[] {
	return path.split( '/' ).filter( ( part ) => '' !== part );
}

interface PathParam {
	readonly name: string;
	readonly optional: boolean;
}

function pathParams( pattern: string ): PathParam[] {
	return segments( pattern )
		.filter( ( piece ) => piece.startsWith( ':' ) )
		.map( ( piece ) => ( {
			name: piece.replace( /^:|\?$/g, '' ),
			optional: piece.endsWith( '?' ),
		} ) );
}

function matchPath(
	pattern: string,
	path: string
): { params: Record< string, string >; statics: number } | null {
	const pieces = segments( pattern );
	const parts = segments( path );
	if ( parts.length > pieces.length ) {
		return null;
	}
	const params: Record< string, string > = {};
	let statics = 0;
	for ( let index = 0; index < pieces.length; index++ ) {
		const piece = pieces[ index ] as string;
		const part = parts[ index ];
		if ( piece.startsWith( ':' ) ) {
			if ( undefined === part ) {
				if ( piece.endsWith( '?' ) ) {
					continue;
				}
				return null;
			}
			params[ piece.replace( /^:|\?$/g, '' ) ] = decodePath( part );
		} else if ( part === piece ) {
			statics++;
		} else {
			return null;
		}
	}
	return { params, statics };
}

function fillPath( pattern: string, params: Record< string, string > ) {
	const parts: string[] = [];
	for ( const piece of segments( pattern ) ) {
		if ( ! piece.startsWith( ':' ) ) {
			parts.push( piece );
			continue;
		}
		const value = params[ piece.replace( /^:|\?$/g, '' ) ];
		if ( undefined !== value && '' !== value ) {
			parts.push( encodeURIComponent( value ) );
		}
	}
	return `/${ parts.join( '/' ) }`;
}

function first( pairs: readonly QueryPair[], name: string ) {
	return pairs.find( ( pair ) => pair[ 0 ] === name )?.[ 1 ];
}

function boundFrom( feature: FeatureDefinition, slot: AddressSlot ) {
	return Object.entries( feature.params ?? {} ).find(
		( [ , to ] ) => to === slot
	)?.[ 0 ];
}

/**
 * Placeholder names of a tab template.
 *
 * @param template Template such as `ext-:id`.
 * @return Names such as `[ 'id' ]`.
 */
function templateNames( template: string ): string[] {
	return [ ...template.matchAll( /:([A-Za-z_]+)/g ) ].map(
		( found ) => found[ 1 ] as string
	);
}

function matchTemplate(
	template: string,
	value: string
): Record< string, string > | null {
	const source = template
		.split( /:[A-Za-z_]+/ )
		.map( ( part ) => part.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) )
		.join( '([a-z][a-z0-9_-]*)' );
	const found = new RegExp( `^${ source }$` ).exec( value );
	if ( ! found ) {
		return null;
	}
	return Object.fromEntries(
		templateNames( template ).map( ( name, index ) => [
			name,
			found[ index + 1 ] as string,
		] )
	);
}

/**
 * The feature whose path matches a route path best: most literal segments, then registry order.
 *
 * @param features Registered features.
 * @param path     Route path such as `/courses/12`.
 * @return The feature and its decoded path parameters, or null.
 */
export function matchFeature(
	features: readonly FeatureDefinition[],
	path: string
): { feature: FeatureDefinition; params: Record< string, string > } | null {
	let best: {
		feature: FeatureDefinition;
		params: Record< string, string >;
		statics: number;
	} | null = null;
	for ( const feature of features ) {
		const found = matchPath( feature.path, path );
		if ( found && ( ! best || found.statics > best.statics ) ) {
			best = { feature, ...found };
		}
	}
	return best ? { feature: best.feature, params: best.params } : null;
}

/**
 * Address of an internal route.
 *
 * Order: `page`, `tab`, `section`, `id`, `view`, the other path parameters, then the route's own query
 * (with `page` as `paged`). The bare route `/` is the Dashboard.
 *
 * @param features Registered features.
 * @param route    Route such as `/courses/12/settings` or `/settings?tab=tax`.
 * @param options  Extension page groups.
 * @return Address pairs, or null when no feature serves the route.
 */
export function toAddress(
	features: readonly FeatureDefinition[],
	route: string,
	options: AddressOptions = {}
): QueryPair[] | null {
	const withoutHash = route.split( '#' )[ 0 ] as string;
	const cut = withoutHash.indexOf( '?' );
	const path = -1 === cut ? withoutHash : withoutHash.slice( 0, cut );
	if ( 0 === segments( path ).length ) {
		return [ [ 'page', GROUP_PAGES.dashboard ] ];
	}
	const found = matchFeature( features, path );
	if ( ! found ) {
		return null;
	}
	const query = parseQuery( -1 === cut ? '' : withoutHash.slice( cut + 1 ) );
	const { feature, params } = found;
	const used = new Set< string >();
	const consumed = new Set< string >();
	const slots: Partial< Record< AddressSlot, string > > = {
		tab: feature.tab,
		section: feature.section,
		view: feature.view,
	};
	if ( slots.tab && slots.tab.includes( ':' ) ) {
		slots.tab = slots.tab.replace( /:([A-Za-z_]+)/g, ( token, name ) => {
			used.add( name );
			return params[ name ] ?? token;
		} );
	}
	const named: QueryPair[] = [];
	for ( const [ from, to ] of Object.entries( feature.params ?? {} ) ) {
		let value: string | undefined;
		if ( Object.prototype.hasOwnProperty.call( params, from ) ) {
			used.add( from );
			value = params[ from ];
		} else {
			consumed.add( from );
			value = first( query, from );
		}
		if ( undefined === value || '' === value ) {
			continue;
		}
		if ( ( SLOTS as readonly string[] ).includes( to ) ) {
			slots[ to as AddressSlot ] = value;
		} else {
			named.push( [ to, value ] );
		}
	}
	for ( const { name } of pathParams( feature.path ) ) {
		const value = params[ name ];
		if ( ! used.has( name ) && undefined !== value ) {
			named.push( [ name, value ] );
		}
	}
	let group = feature.group;
	if ( EXTENSION_PAGE_PATH === feature.path && params.id ) {
		group = options.extensionGroup?.( params.id ) ?? group;
	}
	const pairs: QueryPair[] = [ [ 'page', GROUP_PAGES[ group ] ] ];
	for ( const slot of [ 'tab', 'section' ] as const ) {
		const value = slots[ slot ];
		if ( value ) {
			pairs.push( [ slot, value ] );
		}
	}
	const id = named.find( ( [ name ] ) => 'id' === name );
	if ( id ) {
		pairs.push( id );
	}
	if ( slots.view ) {
		pairs.push( [ 'view', slots.view ] );
	}
	pairs.push( ...named.filter( ( pair ) => pair !== id ) );
	for ( const [ name, value ] of query ) {
		if ( ! consumed.has( name ) ) {
			pairs.push( [ RENAMED_QUERY[ name ] ?? name, value ] );
		}
	}
	return pairs;
}

interface Candidate {
	readonly feature: FeatureDefinition;
	readonly score: number;
	readonly route: string;
}

/**
 * How well a feature serves an address, and its route; null when it does not serve it.
 *
 * @param feature Feature.
 * @param group   Group of the address page.
 * @param slots   Address tab, section and view (`''` when absent).
 * @param rest    The other address parameters.
 */
function candidate(
	feature: FeatureDefinition,
	group: AdminGroup,
	slots: Record< AddressSlot, string >,
	rest: readonly QueryPair[]
): Candidate | null {
	const path: Record< string, string > = {};
	const internal: QueryPair[] = [];
	const consumed = new Set< string >();
	let score = 0;

	const template = feature.tab?.includes( ':' ) ? feature.tab : undefined;
	if ( template ) {
		const found = matchTemplate( template, slots.tab );
		if ( ! found ) {
			return null;
		}
		Object.assign( path, found );
		score += 2;
	} else if ( feature.group !== group ) {
		return null;
	} else if ( ( feature.tab ?? '' ) === slots.tab ) {
		score += 2;
	} else if (
		! boundFrom( feature, 'tab' ) ||
		! feature.tabs?.includes( slots.tab )
	) {
		return null;
	}

	const bound: Record< string, string > = {};
	const tabFrom = boundFrom( feature, 'tab' );
	if ( tabFrom && ! template && slots.tab !== feature.tab ) {
		bound[ tabFrom ] = slots.tab;
		score += 1;
	}
	for ( const slot of [ 'section', 'view' ] as const ) {
		const fixed = feature[ slot ];
		const from = boundFrom( feature, slot );
		const actual = slots[ slot ];
		if ( undefined !== fixed ) {
			if ( actual !== fixed ) {
				return null;
			}
			score += 2;
		} else if ( from ) {
			if ( actual ) {
				bound[ from ] = actual;
				score += 1;
			}
		} else if ( actual ) {
			return null;
		}
	}

	for ( const { name, optional } of pathParams( feature.path ) ) {
		if ( Object.prototype.hasOwnProperty.call( path, name ) ) {
			continue;
		}
		const mapped = feature.params?.[ name ];
		const fromSlot = Boolean(
			mapped && ( SLOTS as readonly string[] ).includes( mapped )
		);
		let value: string | undefined;
		if ( fromSlot ) {
			value = bound[ name ];
			delete bound[ name ];
		} else {
			const addressName = mapped ?? name;
			value = first( rest, addressName );
			consumed.add( addressName );
		}
		if ( undefined === value || '' === value ) {
			if ( optional ) {
				continue;
			}
			return null;
		}
		path[ name ] = value;
		// A value from a slot already counted there.
		score += fromSlot ? 0 : 1;
	}

	for ( const [ from, value ] of Object.entries( bound ) ) {
		internal.push( [ from, value ] );
	}
	for ( const [ from, to ] of Object.entries( feature.params ?? {} ) ) {
		if (
			( SLOTS as readonly string[] ).includes( to ) ||
			Object.prototype.hasOwnProperty.call( path, from ) ||
			pathParams( feature.path ).some( ( param ) => param.name === from )
		) {
			continue;
		}
		const value = first( rest, to );
		consumed.add( to );
		if ( undefined !== value ) {
			internal.push( [ from, value ] );
		}
	}
	for ( const [ name, value ] of rest ) {
		if ( consumed.has( name ) ) {
			continue;
		}
		const original = Object.keys( RENAMED_QUERY ).find(
			( key ) => RENAMED_QUERY[ key ] === name
		);
		internal.push( [ original ?? name, value ] );
	}
	const search = formatQuery( internal );
	return {
		feature,
		score,
		route:
			fillPath( feature.path, path ) + ( search ? `?${ search }` : '' ),
	};
}

/**
 * Internal route of an address.
 *
 * When the server placed the document elsewhere than the address says (`place`: an unknown or hidden
 * tab falls back to the group's default, a missing section to the tab's default), the server's place
 * wins. An address no feature serves opens its tab's own screen.
 *
 * @param features Registered features.
 * @param pairs    Address pairs.
 * @param place    Where the server placed the document, when known.
 * @param fallback Whether an address no feature serves opens its tab's own screen; without it, such an
 *                 address (a section the server renders itself) gives null.
 * @return The serving feature and its route, or null for addresses outside the six pages.
 */
export function fromAddress(
	features: readonly FeatureDefinition[],
	pairs: readonly QueryPair[],
	place?: AdminPlace,
	fallback = true
): { feature: FeatureDefinition; route: string } | null {
	const page = first( pairs, 'page' ) ?? '';
	const group = groupOfPage( page );
	if ( ! group ) {
		return null;
	}
	const slots: Record< AddressSlot, string > = {
		tab: first( pairs, 'tab' ) ?? '',
		section: first( pairs, 'section' ) ?? '',
		view: first( pairs, 'view' ) ?? '',
	};
	let rest = pairs.filter( ( [ name ] ) => ! RESERVED.includes( name ) );
	if ( place && place.page === page ) {
		if ( place.tab !== slots.tab ) {
			slots.tab = place.tab;
			slots.section = place.section;
			slots.view = '';
			rest = [];
		} else if ( place.section !== slots.section ) {
			slots.section = place.section;
		}
	}
	let best: Candidate | null = null;
	for ( const feature of features ) {
		const found = candidate( feature, group, slots, rest );
		if ( found && ( ! best || found.score > best.score ) ) {
			best = found;
		}
	}
	if (
		! best &&
		fallback &&
		( slots.section || slots.view || rest.length )
	) {
		return fromAddress( features, [
			[ 'page', page ],
			...( slots.tab ? [ [ 'tab', slots.tab ] as const ] : [] ),
		] );
	}
	return best ? { feature: best.feature, route: best.route } : null;
}

/**
 * Whether an address is on the given page and tab, so moving there stays in the document.
 *
 * @param pairs Address pairs.
 * @param place Current page and tab.
 * @return True when page and tab are the same.
 */
export function isSamePlace(
	pairs: readonly QueryPair[],
	place: Pick< AdminPlace, 'page' | 'tab' >
): boolean {
	return (
		first( pairs, 'page' ) === place.page &&
		( first( pairs, 'tab' ) ?? '' ) === place.tab
	);
}

/**
 * Section of an address, for marking the current `.subsubsub` link.
 *
 * @param pairs Address pairs.
 * @return Section, or `''`.
 */
export function sectionOf( pairs: readonly QueryPair[] ): string {
	return first( pairs, 'section' ) ?? '';
}
