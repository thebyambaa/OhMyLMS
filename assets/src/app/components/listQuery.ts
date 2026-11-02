import { useContext, useMemo, useState } from '@wordpress/element';
import {
	UNSAFE_LocationContext as LocationContext,
	UNSAFE_NavigationContext as NavigationContext,
} from 'react-router-dom';

/** Route query parameter of the page number; the address calls it `paged` (routing/address.ts). */
const PAGE = 'page';

export type ListQueryPatch< K extends string > = Partial<
	Record< K, string >
> & {
	page?: number;
};

export interface ListQuery< K extends string > {
	/** Current values: the address's, or the defaults where it names none. */
	readonly values: Readonly< Record< K, string > >;
	/** Current page (1 or more). */
	readonly page: number;
	/** Changes whenever the list state does; use it as an effect dependency. */
	readonly key: string;
	/**
	 * Real address of the list with a change applied, for links that can be opened in a new tab.
	 * A change that names any list parameter (even with its current value) goes back to the first page.
	 */
	href: ( patch: ListQueryPatch< K > ) => string;
	/** Move to the list with a change applied (a `pushState` on the admin pages). */
	set: (
		patch: ListQueryPatch< K >,
		options?: { replace?: boolean }
	) => void;
}

function pageOf( value: string | null ): number {
	const page = Number( value );
	return Number.isInteger( page ) && page > 1 ? page : 1;
}

type To = { pathname: string; search: string };

function useRoute() {
	const locationContext = useContext( LocationContext ) as {
		location?: { pathname: string; search: string };
	} | null;
	const navigationContext = useContext( NavigationContext ) as {
		navigator?: {
			createHref: ( to: To ) => string;
			push: ( to: To ) => void;
			replace: ( to: To ) => void;
		};
	} | null;
	const location = locationContext?.location;
	const navigator = navigationContext?.navigator;
	return location && navigator ? { location, navigator } : null;
}

export interface ListState< F > {
	readonly page: number;
	/** Current filters; the same object while they are unchanged (an effect dependency). */
	readonly filters: F;
	setPage: ( page: number, options?: { replace?: boolean } ) => void;
	/** Replace the filters and go back to the first page. */
	setFilters: ( next: F ) => void;
	pageHref: ( page: number ) => string;
	/** Address of the list's first page with other filters. */
	filtersHref: ( next: F ) => string;
}

/**
 * Drop-in for a list's `useState( 1 )` page and `useState( filters )` that keeps both in the address
 * (`useListQuery`). Filter values are strings; the server validates them.
 *
 * @param defaults Filters with their defaults.
 * @param names    Address name of a filter when it differs (for example `{ search: 's' }`).
 * @return Page, filters and the actions and addresses that change them.
 */
export function useListState< F extends { [ Key in keyof F ]: string } >(
	defaults: F,
	names: Partial< Record< keyof F & string, string > > = {}
): ListState< F > {
	const keys = Object.keys( defaults ) as Array< keyof F & string >;
	const nameOf = ( key: keyof F & string ) => names[ key ] ?? key;
	const toAddress = ( filters: F ) =>
		Object.fromEntries(
			keys.map( ( key ) => [ nameOf( key ), filters[ key ] ] )
		) as Record< string, string >;
	const query = useListQuery< string >( toAddress( defaults ) );
	const filters = useMemo(
		() =>
			Object.fromEntries(
				keys.map( ( key ) => [ key, query.values[ nameOf( key ) ] ] )
			) as unknown as F,
		// `keys` and `names` are fixed for a list.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ query.key ]
	);
	// Address names are only known at run time, so the patch cannot be typed by name.
	const patch = ( values: Record< string, string >, page?: number ) =>
		( { ...values, page } ) as unknown as ListQueryPatch< string >;
	return {
		page: query.page,
		filters,
		setPage: ( page, options ) => query.set( patch( {}, page ), options ),
		setFilters: ( next ) => query.set( patch( toAddress( next ), 1 ) ),
		pageHref: ( page ) => query.href( patch( {}, page ) ),
		filtersHref: ( next ) => query.href( patch( toAddress( next ), 1 ) ),
	};
}

type Filters< Q > = Partial< Omit< Q, 'page' > >;

export interface AddressQuery< Q > {
	/** Current query (page and filters); the same object while it is unchanged. */
	readonly query: Q;
	setPage: ( page: number, options?: { replace?: boolean } ) => void;
	/** Change some filters and go back to the first page. */
	setFilters: ( filters: Filters< Q > ) => void;
	pageHref: ( page: number ) => string;
	filtersHref: ( filters: Filters< Q > ) => string;
}

/**
 * `useListState` for lists whose state is one query object with a `page` and string filters (the
 * commerce and membership lists), kept in the address.
 *
 * @param empty The query with its defaults (`page` 1).
 * @param names Address name of a filter when it differs.
 * @return The query and the actions and addresses that change it.
 */
export function useAddressQuery< Q extends { page: number } >(
	empty: Q,
	names: Partial< Record< string, string > > = {}
): AddressQuery< Q > {
	const { page: first, ...defaults } = empty;
	void first;
	const list = useListState(
		defaults as unknown as Record< string, string >,
		names
	);
	const query = useMemo(
		() => ( { ...list.filters, page: list.page } ) as unknown as Q,
		[ list.filters, list.page ]
	);
	const merge = ( filters: Filters< Q > ) =>
		( { ...list.filters, ...filters } ) as Record< string, string >;
	return {
		query,
		setPage: list.setPage,
		setFilters: ( filters ) => list.setFilters( merge( filters ) ),
		pageHref: list.pageHref,
		filtersHref: ( filters ) => list.filtersHref( merge( filters ) ),
	};
}

/**
 * Address of the current route with another query, for screens that keep their list state in the
 * route query themselves (`useSearchParams`).
 *
 * @return Builds the address from a query string (with or without `?`).
 */
export function useSearchHref(): ( search: string ) => string {
	const route = useRoute();
	return ( search ) => {
		const query = search.replace( /^\?/, '' );
		const next = query ? `?${ query }` : '';
		return route
			? route.navigator.createHref( {
					pathname: route.location.pathname,
					search: next,
				} )
			: next || '?';
	};
}

/**
 * List state (view or status, search, sorting, filters and page) kept in the address, so a list can be
 * bookmarked, reloaded and opened in a new tab, and back/forward move through it as on WordPress list
 * screens. Values equal to their default are left out of the address; other query parameters of the
 * route are kept. Outside a router (hook tests) the state is kept in memory instead.
 *
 * @param defaults Every list parameter with its default, as the route names it (`status`, `s`,
 *                 `orderby`…; not `page`, which is the page number).
 * @return Values, page, a change key and the `href`/`set` actions.
 */
export function useListQuery< K extends string >(
	defaults: Readonly< Record< K, string > >
): ListQuery< K > {
	const route = useRoute();
	const [ memory, setMemory ] = useState( '' );
	const pathname = route?.location.pathname ?? '';
	const search = route ? route.location.search : memory;
	const names = Object.keys( defaults ) as K[];
	const defaultsKey = JSON.stringify( defaults );

	const { values, page, key } = useMemo( () => {
		const params = new URLSearchParams( search );
		const read = Object.fromEntries(
			names.map( ( name ) => [
				name,
				params.get( name ) ?? defaults[ name ],
			] )
		) as Record< K, string >;
		const current = pageOf( params.get( PAGE ) );
		return {
			values: read,
			page: current,
			key: JSON.stringify( [
				names.map( ( name ) => read[ name ] ),
				current,
			] ),
		};
		// `names` and `defaults` are covered by `defaultsKey`.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ search, defaultsKey ] );

	function searchOf( patch: ListQueryPatch< K > ): string {
		const params = new URLSearchParams( search );
		// As on WordPress lists, a view, filter or sort link always opens the first page.
		const changesList = names.some(
			( name ) => undefined !== patch[ name ]
		);
		for ( const name of names ) {
			const value = patch[ name ] ?? values[ name ];
			if ( value === defaults[ name ] || '' === value ) {
				params.delete( name );
			} else {
				params.set( name, value );
			}
		}
		const next = patch.page ?? ( changesList ? 1 : page );
		if ( next > 1 ) {
			params.set( PAGE, String( next ) );
		} else {
			params.delete( PAGE );
		}
		const query = params.toString();
		return query ? `?${ query }` : '';
	}

	return {
		values,
		page,
		key,
		href: ( patch ) => {
			const next = searchOf( patch );
			return route
				? route.navigator.createHref( { pathname, search: next } )
				: next || '?';
		},
		set: ( patch, options = {} ) => {
			const next = searchOf( patch );
			if ( ! route ) {
				setMemory( next );
				return;
			}
			if ( next === search ) {
				return;
			}
			const to = { pathname, search: next };
			if ( options.replace ) {
				route.navigator.replace( to );
			} else {
				route.navigator.push( to );
			}
		},
	};
}
