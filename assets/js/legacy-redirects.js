/* global module */
/**
 * Sends the legacy admin screen (`admin.php?page=ohmylms#/...`) to the new admin app
 * (`admin.php?page=ohmylms-app#/...`) for the screens listed in includes/Admin/LegacyRoutes.php, which
 * prints the rules as `window.ohmylmsLegacyRoutes`. The matching mirrors LegacyRoutes::resolve().
 * `&ohmylms_legacy=1` keeps the legacy screen. See docs/LEGACY-ROUTES.md.
 *
 * In Node the matcher is exported (`require( 'assets/js/legacy-redirects.js' )`) instead of running.
 */
( function ( root, factory ) {
	'use strict';
	const api = factory();
	if ( 'object' === typeof module && module.exports ) {
		module.exports = api;
		return;
	}
	api.start( root );
} )( 'undefined' === typeof window ? null : window, function () {
	'use strict';

	function segments( path ) {
		return String( path )
			.split( '/' )
			.filter( ( part ) => '' !== part );
	}

	function decode( value ) {
		try {
			return decodeURIComponent( value.replace( /\+/g, ' ' ) );
		} catch ( error ) {
			return value;
		}
	}

	function queryPairs( search ) {
		return String( search )
			.split( '&' )
			.filter( ( raw ) => '' !== raw )
			.map( ( raw ) => {
				const cut = raw.indexOf( '=' );
				const name = -1 === cut ? raw : raw.slice( 0, cut );
				const value = -1 === cut ? '' : raw.slice( cut + 1 );
				return [ decode( name ), decode( value ), raw ];
			} );
	}

	function queryValue( pairs, name, fallback ) {
		const pair = pairs.find( ( entry ) => entry[ 0 ] === name );
		return pair ? pair[ 1 ] : fallback;
	}

	function matchPath( pattern, parts ) {
		const params = {};
		const pieces = segments( pattern );
		for ( let index = 0; index < pieces.length; index++ ) {
			const piece = pieces[ index ];
			if ( '*' === piece ) {
				return params;
			}
			const part = parts[ index ];
			if ( ':' === piece[ 0 ] ) {
				if ( undefined === part ) {
					if ( '?' === piece[ piece.length - 1 ] ) {
						continue;
					}
					return null;
				}
				params[ piece.replace( /^:|\?$/g, '' ) ] = decode( part );
			} else if ( part !== piece ) {
				return null;
			}
		}
		return parts.length > pieces.length ? null : params;
	}

	function build( template, params, pairs, consumed ) {
		const route = template.replace( /:([A-Za-z_]+)/g, ( token, name ) =>
			Object.prototype.hasOwnProperty.call( params, name )
				? encodeURIComponent( params[ name ] )
				: token
		);
		const cut = route.indexOf( '?' );
		const own = queryPairs( -1 === cut ? '' : route.slice( cut + 1 ) ).map(
			( pair ) => pair[ 0 ]
		);
		const extra = pairs
			.filter(
				( pair ) =>
					! consumed.includes( pair[ 0 ] ) &&
					! own.includes( pair[ 0 ] )
			)
			.map( ( pair ) => pair[ 2 ] );
		if ( ! extra.length ) {
			return route;
		}
		return route + ( -1 === cut ? '?' : '&' ) + extra.join( '&' );
	}

	/**
	 * New-app route for a legacy hash route.
	 *
	 * @param {Array<Object>} routes Rules from LegacyRoutes::routes().
	 * @param {string}        legacy Hash route such as `/order-edit/12` or `#/accounthub?tab=classes`.
	 * @return {string|null} New route, or null when the legacy screen stays.
	 */
	function resolve( routes, legacy ) {
		const value = String( legacy || '' ).replace( /^#/, '' );
		const cut = value.indexOf( '?' );
		const parts = segments( -1 === cut ? value : value.slice( 0, cut ) );
		const pairs = queryPairs( -1 === cut ? '' : value.slice( cut + 1 ) );
		for ( const rule of routes || [] ) {
			const params = matchPath( rule.from, parts );
			if ( ! params ) {
				continue;
			}
			const consumed = [];
			const conditions = rule.query || {};
			const allowed = Object.keys( conditions ).every( ( name ) => {
				const current = queryValue( pairs, name, '' );
				if ( ! [].concat( conditions[ name ] ).includes( current ) ) {
					return false;
				}
				params[ name ] = current;
				consumed.push( name );
				return true;
			} );
			const skipped = ( rule.skip || [] ).some(
				( name ) => null !== queryValue( pairs, name, null )
			);
			if ( allowed && ! skipped ) {
				return build( rule.to, params, pairs, consumed );
			}
		}
		return null;
	}

	/**
	 * Address to open instead of the current legacy one.
	 *
	 * @param {Object} config   `window.ohmylmsLegacyRoutes`: appPage, optOut and routes.
	 * @param {Object} location Object with `pathname`, `search` and `hash`.
	 * @return {string|null} Relative URL of the new screen, or null to stay.
	 */
	function redirectUrl( config, location ) {
		if ( ! config || ! location ) {
			return null;
		}
		const params = new URLSearchParams( location.search || '' );
		if ( '1' === params.get( config.optOut ) ) {
			return null;
		}
		const route = resolve( config.routes, location.hash || '' );
		if ( null === route ) {
			return null;
		}
		params.set( 'page', config.appPage );
		return location.pathname + '?' + params.toString() + '#' + route;
	}

	/**
	 * Redirect now and whenever the legacy app changes its route.
	 *
	 * @param {Window|null} win Browser window.
	 */
	function start( win ) {
		if ( ! win || ! win.ohmylmsLegacyRoutes ) {
			return;
		}
		const check = () => {
			const url = redirectUrl( win.ohmylmsLegacyRoutes, win.location );
			if ( url ) {
				win.location.replace( url );
			}
		};
		// The legacy router changes the hash with the History API, which fires no hashchange event.
		[ 'pushState', 'replaceState' ].forEach( ( method ) => {
			const original = win.history[ method ];
			win.history[ method ] = function ( ...args ) {
				const result = original.apply( this, args );
				check();
				return result;
			};
		} );
		win.addEventListener( 'hashchange', check );
		win.addEventListener( 'popstate', check );
		check();
	}

	return { resolve, redirectUrl, start };
} );
