/** Dashboard apiFetch transport for server-issued course structure read tokens. */
export function createStructureMiddleware() {
	const revisions = new Map();
	const owners = new Map();
	return async ( options, next ) => {
		const url = new URL(
			options.path || options.url || '/',
			'http://localhost'
		);
		const path = url.searchParams.get( 'rest_route' ) || url.pathname;
		const courseMatch = path.match(
			/\/ohmylms\/v1\/(?:courses|content-hub\/catalog|mcp-structure)\/(\d+)(?:\/|$)/
		);
		const unitMatch = path.match(
			/\/ohmylms\/v1\/chapters\/(\d+)(?:\/|$)/
		);
		const relevant =
			courseMatch ||
			unitMatch ||
			/\/ohmylms\/v1\/(?:courses|chapters)\/?$/.test( path );
		if ( ! relevant ) {
			return next( options );
		}
		const course =
			courseMatch?.[ 1 ] ||
			( unitMatch && owners.get( unitMatch[ 1 ] ) ) ||
			String( options.data?.course_id || '' );
		const method = ( options.method || 'GET' ).toUpperCase();
		const headers = new Headers( options.headers || {} );
		if (
			! [ 'GET', 'HEAD' ].includes( method ) &&
			course &&
			revisions.has( course ) &&
			! headers.has( 'X-OhMyLMS-Structure-Revision' ) &&
			! options.data?.revision
		) {
			headers.set(
				'X-OhMyLMS-Structure-Revision',
				revisions.get( course )
			);
		}
		let response;
		try {
			response = await next( {
				...options,
				headers: Object.fromEntries( headers ),
				parse: false,
			} );
		} catch ( error ) {
			if (
				options.parse !== false &&
				typeof error?.json === 'function'
			) {
				throw await error.json();
			}
			throw error;
		}
		if ( ! response?.headers?.get || typeof response.json !== 'function' ) {
			return response;
		}
		if ( ! response.ok ) {
			if ( options.parse === false ) {
				throw response;
			}
			throw await response.json();
		}
		const revision = response.headers.get( 'X-OhMyLMS-Structure-Revision' );
		const owner = response.headers.get( 'X-OhMyLMS-Structure-Course' );
		// Error responses never advance the read token or retry a stale mutation.
		if ( response.ok && revision && owner ) {
			revisions.set( owner, revision );
			if ( unitMatch ) {
				owners.set( unitMatch[ 1 ], owner );
			}
			const data =
				method === 'HEAD' || response.status === 204
					? {}
					: await response.clone().json();
			for ( const rows of [ data.chapters, data.units, data.data ] ) {
				if (
					Array.isArray( rows ) &&
					( rows !== data.data ||
						/\/courses\/\d+\/chapters\/?$/.test( path ) )
				) {
					for ( const unit of rows ) {
						if ( Number( unit.id ) > 0 ) {
							owners.set( String( unit.id ), owner );
						}
					}
				}
			}
			if ( Number( data.chapter?.id ) > 0 ) {
				owners.set( String( data.chapter.id ), owner );
			}
			if (
				/\/chapters\/?$/.test( path ) &&
				! unitMatch &&
				Number( data.id ) > 0
			) {
				owners.set( String( data.id ), owner );
			}
		}
		if ( options.parse === false ) {
			return response;
		}
		return response.status === 204 || method === 'HEAD'
			? null
			: response.json();
	};
}

if ( typeof window !== 'undefined' && window.wp?.apiFetch ) {
	window.wp.apiFetch.use( createStructureMiddleware() );
}
