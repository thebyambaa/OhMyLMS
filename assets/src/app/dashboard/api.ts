export interface DashboardBoot {
	rest: string;
	nonce: string;
	adminUrl: string;
	user: { id: number; name: string };
	owner: boolean;
	canManage: boolean;
	schools: Array< { id: number; name: string; role: string } >;
	nav: string[];
	section?: string;
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
 * @param root REST root to join onto.
 */
function endpoint( path: string, root: string ) {
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

async function request< T >(
	base: string,
	path: string,
	options: RequestInit
): Promise< T > {
	const root = boot();
	const response = await fetch( endpoint( path, base ), {
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

/**
 * Call a Studio route (`ohmylms/v1/front-dashboard`).
 *
 * @param path    Path beginning with `/`.
 * @param options Fetch options.
 */
export function api< T >( path: string, options: RequestInit = {} ) {
	return request< T >( boot().rest, path, options );
}

/**
 * Call the plugin's main REST API (`ohmylms/v1`), the endpoints wp-admin uses. They carry the existing
 * safeguards for permissions, enrolments, quiz attempts and published courses.
 *
 * @param path    Path under ohmylms/v1, beginning with `/`.
 * @param options Fetch options.
 */
export function coreApi< T >( path: string, options: RequestInit = {} ) {
	return request< T >(
		boot().rest.replace( '/front-dashboard', '' ),
		path,
		options
	);
}
