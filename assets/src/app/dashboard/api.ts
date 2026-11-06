export interface DashboardBoot {
	rest: string;
	nonce: string;
	adminUrl: string;
	user: { id: number; name: string };
	owner: boolean;
	canManage: boolean;
	schools: Array< { id: number; name: string; role: string } >;
	nav: string[];
	links: Record< string, string >;
}

export function boot(): DashboardBoot {
	const value = ( window as unknown as { ohmylmsDashboard?: DashboardBoot } )
		.ohmylmsDashboard;
	if ( ! value ) {
		throw new Error( 'Dashboard bootstrap is missing.' );
	}
	return value;
}

/**
 * Join a path onto the REST root. Plain permalinks put the route in `rest_route`, so extra query args use `&`.
 *
 * @param path Path beginning with `/`, optionally with a query string.
 */
function endpoint( path: string ) {
	const root = boot().rest;
	const queryAt = path.indexOf( '?' );
	const extraPath = queryAt === -1 ? path : path.slice( 0, queryAt );
	const extraQuery = queryAt === -1 ? '' : path.slice( queryAt + 1 );
	if ( root.includes( 'rest_route=' ) ) {
		const url = new URL( root );
		const route = url.searchParams.get( 'rest_route' ) || '';
		url.searchParams.set( 'rest_route', `${ route }${ extraPath }` );
		new URLSearchParams( extraQuery ).forEach( ( value, key ) => {
			url.searchParams.append( key, value );
		} );
		return url.toString();
	}
	return `${ root }${ path }`;
}

export async function api< T >(
	path: string,
	options: RequestInit = {}
): Promise< T > {
	const root = boot();
	const response = await fetch( endpoint( path ), {
		...options,
		headers: {
			'X-WP-Nonce': root.nonce,
			...( options.body instanceof FormData
				? {}
				: { 'Content-Type': 'application/json' } ),
			...( options.headers || {} ),
		},
	} );
	if ( ! response.ok ) {
		let message = response.statusText;
		try {
			const body = ( await response.json() ) as { message?: string };
			if ( body.message ) {
				message = body.message;
			}
		} catch {
			message = response.statusText;
		}
		throw new Error( message );
	}
	const type = response.headers.get( 'content-type' ) || '';
	if ( type.includes( 'text/csv' ) ) {
		return ( await response.text() ) as T;
	}
	return ( await response.json() ) as T;
}
