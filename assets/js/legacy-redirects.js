/* global module */
/**
 * Maps a retired legacy admin address (`admin.php?page=ohmylms#/...`) to the new admin app
 * (`admin.php?page=ohmylms-app#/...`). The server redirects the legacy page to the new app with
 * `&ohmylms_from_legacy=1` and the browser carries the legacy `#/route` over; LegacyRoutes::url() builds
 * the same address for unmapped routes. includes/Admin/LegacyRoutes.php prints the rules as
 * `window.ohmylmsLegacyRoutes` and loads this script in the app's head, so before the app reads the hash
 * a mapped route is replaced by its new route and an unmapped one by the app home, with the notice that
 * names the screen shown. The matching mirrors LegacyRoutes::resolve(). See docs/LEGACY-ROUTES.md.
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
	 * @return {string|null} New route, or null when the new app has no such screen.
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
	 * Address of the new app for the legacy hash carried over by the redirect.
	 *
	 * @param {Object} config   `window.ohmylmsLegacyRoutes`: routes and `landing` (param, home).
	 * @param {Object} location Object with `pathname`, `search` and `hash`.
	 * @return {{url: string, unavailable: string|null}|null} Relative URL without the marker, and the
	 * legacy route when the new app cannot open it; null when the page was not opened by the redirect.
	 */
	function landingUrl( config, location ) {
		if ( ! config || ! config.landing || ! location ) {
			return null;
		}
		const params = new URLSearchParams( location.search || '' );
		if ( '1' !== params.get( config.landing.param ) ) {
			return null;
		}
		params.delete( config.landing.param );
		const legacy =
			'/' + String( location.hash || '' ).replace( /^#?\/?/, '' );
		const route = resolve( config.routes, legacy );
		return {
			url:
				location.pathname +
				'?' +
				params.toString() +
				'#' +
				( null === route ? config.landing.home : route ),
			unavailable: null === route ? legacy : null,
		};
	}

	/**
	 * Show the notice that names a legacy screen the new app cannot open.
	 *
	 * @param {Document} doc      Document.
	 * @param {string}   noticeId Id of the notice printed by AppPage.
	 * @param {string}   legacy   Legacy route.
	 */
	function showUnavailable( doc, noticeId, legacy ) {
		const notice = doc.getElementById( noticeId );
		if ( ! notice ) {
			return;
		}
		const code = notice.querySelector( '[data-ohmylms-legacy-route]' );
		if ( code ) {
			code.textContent = legacy;
		}
		notice.hidden = false;
	}

	/**
	 * Map the carried-over legacy hash once, before the app's router starts.
	 *
	 * @param {Window} win Browser window.
	 */
	function land( win ) {
		const config = win.ohmylmsLegacyRoutes;
		const target = landingUrl( config, win.location );
		if ( ! target ) {
			return;
		}
		win.history.replaceState( win.history.state, '', target.url );
		if ( null === target.unavailable ) {
			return;
		}
		const show = () =>
			showUnavailable(
				win.document,
				config.landing.noticeId,
				target.unavailable
			);
		if ( 'loading' === win.document.readyState ) {
			win.document.addEventListener( 'DOMContentLoaded', show, {
				once: true,
			} );
		} else {
			show();
		}
	}

	/**
	 * Map the carried-over legacy hash on the new app.
	 *
	 * @param {Window|null} win Browser window.
	 */
	function start( win ) {
		if ( win && win.ohmylmsLegacyRoutes ) {
			land( win );
		}
	}

	return { resolve, landingUrl, showUnavailable, start };
} );
