import {
	useEffect,
	useLayoutEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import type { ReactNode } from 'react';
import {
	createPath,
	NavigationType,
	parsePath,
	Router,
	type Location,
	type Navigator,
	type To,
} from 'react-router-dom';
import type { FeatureDefinition } from '../features/types';
import {
	addressHref,
	fromAddress,
	isSamePlace,
	parseQuery,
	sectionOf,
	toAddress,
	type AddressOptions,
	type AdminPlace,
} from './address';
import { routeFromHref, setLinkResolver } from './links';
import { PlaceProvider } from './place';

/** Id of the `.subsubsub` list includes/Admin/AdminNavigation.php prints under the tabs. */
export const SECTIONS_ID = 'ohmylms-admin-sections';

function routeOfLocation(
	features: readonly FeatureDefinition[],
	search: string,
	place: AdminPlace | undefined
): string {
	return (
		fromAddress( features, parseQuery( search ), place )?.route ??
		'/not-found'
	);
}

function defaultLoad( href: string, replace: boolean ) {
	if ( replace ) {
		window.location.replace( href );
	} else {
		window.location.assign( href );
	}
}

function createKey() {
	return Math.random().toString( 36 ).slice( 2, 10 );
}

function toLocation( route: string, state: unknown, key: string ): Location {
	const path = parsePath( route );
	return {
		pathname: path.pathname || '/',
		search: path.search ?? '',
		hash: '',
		state: state ?? null,
		key,
	};
}

/**
 * Mark the current `.subsubsub` link after a move inside the tab. The list names the section an
 * address without `&section=` shows (`data-default-section`).
 *
 * @param doc     Document.
 * @param section Section of the address (`''` when it names none).
 */
function markSection( doc: Document, section: string ) {
	const list = doc.getElementById( SECTIONS_ID );
	if ( ! list ) {
		return;
	}
	const current = section || ( list.dataset.defaultSection ?? '' );
	list.querySelectorAll< HTMLAnchorElement >( 'a[data-section]' ).forEach(
		( link ) => {
			const active = link.dataset.section === current;
			link.classList.toggle( 'current', active );
			if ( active ) {
				link.setAttribute( 'aria-current', 'page' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		}
	);
}

/**
 * Address of a hash route (`#/orders/12`) when it opens another page or tab, so the caller can load
 * it; null when the hash is no route or stays in this document.
 *
 * @param win      Window.
 * @param features Registered features.
 * @param place    Server place.
 * @param options  Extension page groups.
 * @return Address URL to load, or null.
 */
export function hashTarget(
	win: Window,
	features: readonly FeatureDefinition[],
	place: AdminPlace,
	options: AddressOptions = {}
): string | null {
	const hash = win.location.hash;
	if ( ! hash.startsWith( '#/' ) ) {
		return null;
	}
	const pairs = toAddress( features, hash.slice( 1 ), options );
	if ( ! pairs ) {
		return null;
	}
	return isSamePlace( pairs, place ) ? null : addressHref( pairs );
}

/**
 * Router keyed on the query string. Feature code keeps route paths (`useParams`, `Link to="/courses/12"`);
 * routing/address.ts maps them to addresses and back. Moving inside the current page and tab uses
 * `pushState`/`replaceState`; any other address is a full page load, as the PHP tabs are. Old `#/route`
 * hashes (bookmarks, extensions that set the hash) open their route. Same-tab links outside React (the
 * `.subsubsub` sections, plain `<a href>`) are taken over too.
 *
 * @param props          Component props.
 * @param props.features Registered features (all of them: addresses do not depend on capabilities).
 * @param props.place    Where the server placed the document.
 * @param props.options  Extension page groups.
 * @param props.load     Loads another document; `location.assign`/`replace` by default.
 * @param props.children Routes.
 */
export function QueryRouter( {
	features,
	place,
	options = {},
	load = defaultLoad,
	children,
}: {
	features: readonly FeatureDefinition[];
	place: AdminPlace;
	options?: AddressOptions;
	load?: ( href: string, replace: boolean ) => void;
	children: ReactNode;
} ) {
	const win = window;
	const [ state, setState ] = useState( () => ( {
		action: NavigationType.Pop,
		location: toLocation(
			win.location.hash.startsWith( '#/' )
				? win.location.hash.slice( 1 )
				: routeOfLocation( features, win.location.search, place ),
			null,
			'default'
		),
	} ) );

	const navigator = useMemo< Navigator >( () => {
		const move = ( to: To, routeState: unknown, replace: boolean ) => {
			const route = 'string' === typeof to ? to : createPath( to );
			const pairs = toAddress( features, route, options );
			if ( ! pairs ) {
				setState( {
					action: NavigationType.Replace,
					location: toLocation( '/not-found', null, createKey() ),
				} );
				return;
			}
			const href = addressHref( pairs );
			if ( ! isSamePlace( pairs, place ) ) {
				load( href, replace );
				return;
			}
			const key = createKey();
			const entry = { usr: routeState ?? null, key, route };
			if ( replace ) {
				win.history.replaceState( entry, '', href );
			} else {
				win.history.pushState( entry, '', href );
			}
			setState( {
				action: replace ? NavigationType.Replace : NavigationType.Push,
				location: toLocation( route, routeState, key ),
			} );
		};
		return {
			createHref: ( to: To ) => {
				const route = 'string' === typeof to ? to : createPath( to );
				const pairs = toAddress( features, route, options );
				return pairs ? addressHref( pairs ) : `#${ route }`;
			},
			push: ( to, routeState ) => move( to, routeState, false ),
			replace: ( to, routeState ) => move( to, routeState, true ),
			go: ( delta ) => win.history.go( delta ),
		};
	}, [ features, load, options, place, win ] );

	useLayoutEffect( () => {
		const onPop = () => {
			const entry = win.history.state as {
				usr?: unknown;
				key?: string;
				route?: string;
			} | null;
			const route =
				entry?.route ??
				routeOfLocation( features, win.location.search, place );
			setState( {
				action: NavigationType.Pop,
				location: toLocation(
					route,
					entry?.usr,
					entry?.key ?? 'default'
				),
			} );
		};
		const onHash = () => {
			if ( win.location.hash.startsWith( '#/' ) ) {
				navigator.replace( win.location.hash.slice( 1 ) );
			}
		};
		const onClick = ( event: MouseEvent ) => {
			if (
				event.defaultPrevented ||
				0 !== event.button ||
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey ||
				! ( event.target instanceof Element )
			) {
				return;
			}
			const anchor = event.target.closest( 'a[href]' );
			if (
				! ( anchor instanceof HTMLAnchorElement ) ||
				( anchor.target && '_self' !== anchor.target ) ||
				anchor.hasAttribute( 'download' )
			) {
				return;
			}
			const href = anchor.getAttribute( 'href' ) ?? '';
			if ( href.startsWith( '#' ) && ! href.startsWith( '#/' ) ) {
				return;
			}
			const route = routeFromHref( href );
			if ( route ) {
				event.preventDefault();
				navigator.push( route );
			}
		};
		setLinkResolver( ( href ) => {
			let url: URL;
			try {
				url = new URL( href, win.location.href );
			} catch {
				return null;
			}
			// A `#/route` on the address is an old hash route: the browser changes the hash and the
			// hash handlers (the Dashboard's legacy landing, then this router) open it.
			if (
				url.origin !== win.location.origin ||
				url.pathname !== win.location.pathname ||
				url.hash.startsWith( '#/' )
			) {
				return null;
			}
			const pairs = parseQuery( url.search );
			if ( ! isSamePlace( pairs, place ) ) {
				return null;
			}
			return (
				fromAddress( features, pairs, undefined, false )?.route ?? null
			);
		} );
		if ( win.location.hash.startsWith( '#/' ) ) {
			navigator.replace( win.location.hash.slice( 1 ) );
		}
		win.addEventListener( 'popstate', onPop );
		win.addEventListener( 'hashchange', onHash );
		win.document.addEventListener( 'click', onClick );
		return () => {
			setLinkResolver( null );
			win.removeEventListener( 'popstate', onPop );
			win.removeEventListener( 'hashchange', onHash );
			win.document.removeEventListener( 'click', onClick );
		};
	}, [ features, navigator, place, win ] );

	useEffect( () => {
		markSection(
			win.document,
			sectionOf( parseQuery( win.location.search ) )
		);
	}, [ state.location, win ] );

	return (
		<PlaceProvider value={ place }>
			<Router
				location={ state.location }
				navigationType={ state.action }
				navigator={ navigator }
			>
				{ children }
			</Router>
		</PlaceProvider>
	);
}
