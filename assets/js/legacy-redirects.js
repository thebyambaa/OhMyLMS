/* global module */
/**
 * Lands old admin addresses on the Dashboard (`admin.php?page=ohmylms`), where they arrive:
 * - a retired legacy address (`admin.php?page=ohmylms#/order-edit/12`, or older links with
 *   `&ohmylms_from_legacy=1`): the legacy route is replaced by its app route (`#/orders/12`), or, when no
 *   screen replaces it, removed, with the notice that names it shown;
 * - the retired app page (`admin.php?page=ohmylms-app#/orders/12`), which the server redirects here with
 *   `&ohmylms_from_app=1` while the browser keeps the hash: the hash already is an app route.
 * The marker is removed. The app's router then opens the app route in the hash at its address
 * (`admin.php?page=ohmylms-commerce&tab=orders&id=12`). includes/Admin/LegacyRoutes.php prints the rules
 * as `window.ohmylmsLegacyRoutes` and loads this script in the Dashboard's head. The matching mirrors
 * LegacyRoutes::resolve(). See docs/LEGACY-ROUTES.md.
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
	 * Dashboard address with the app route of the hash it received.
	 *
	 * @param {Object} config   `window.ohmylmsLegacyRoutes`: routes and `landing` (param, appParam, home).
	 * @param {Object} location Object with `pathname`, `search` and `hash`.
	 * @return {{url: string, unavailable: string|null}|null} Relative URL without the markers, with the app
	 * route in the hash (none for the Dashboard itself), and the legacy route when no screen replaces it;
	 * null when there is nothing to land.
	 */
	function landingUrl( config, location ) {
		if ( ! config || ! config.landing || ! location ) {
			return null;
		}
		const params = new URLSearchParams( location.search || '' );
		const fromLegacy = '1' === params.get( config.landing.param );
		const fromApp =
			! fromLegacy &&
			!! config.landing.appParam &&
			'1' === params.get( config.landing.appParam );
		const hash = String( location.hash || '' );
		if ( ! fromLegacy && ! fromApp && ! hash.startsWith( '#/' ) ) {
			return null;
		}
		params.delete( config.landing.param );
		if ( config.landing.appParam ) {
			params.delete( config.landing.appParam );
		}
		const carried = '/' + hash.replace( /^#?\/?/, '' );
		const route = fromApp ? carried : resolve( config.routes, carried );
		const target = null === route ? config.landing.home : route;
		const search = params.toString();
		return {
			url:
				location.pathname +
				( search ? '?' + search : '' ) +
				( target === config.landing.home ? '' : '#' + target ),
			unavailable: null === route ? carried : null,
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
	 * Map the hash before the app's router reads it: once on load, and again whenever a link changes it
	 * without loading the document (a legacy link clicked on the Dashboard). This listener is added before
	 * the app's, so the app sees the mapped route.
	 *
	 * @param {Window} win Browser window.
	 */
	function land( win ) {
		map( win );
		win.addEventListener( 'hashchange', () => map( win ) );
	}

	/**
	 * Replace the current hash with its app route, showing the notice when there is none.
	 *
	 * @param {Window} win Browser window.
	 */
	function map( win ) {
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
	 * Map the hash the Dashboard received.
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
