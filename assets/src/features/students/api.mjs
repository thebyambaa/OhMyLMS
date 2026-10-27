const request = ( options ) => window.wp.apiFetch( options );
export function studentQuery( {
	page = 1,
	perPage = 5,
	search = '',
	dateFilter = '',
	orderby = 'registration_date',
	order = 'DESC',
} = {} ) {
	const params = new URLSearchParams( {
		page,
		per_page: perPage,
		offset: ( page - 1 ) * perPage,
		search,
		order_by: orderby,
		order,
	} );
	if ( Array.isArray( dateFilter ) ) {
		params.set( 'date_filter', 'custom' );
		params.set( 'start_date', dateFilter[ 0 ] );
		params.set( 'end_date', dateFilter[ 1 ] );
	} else if ( dateFilter ) {
		params.set( 'date_filter', dateFilter );
	}
	return params.toString();
}
export async function listStudents( query, fetch = request ) {
	const response = await fetch( {
		path: '/ohmylms/v1/students?' + studentQuery( query ),
		parse: false,
	} );
	return {
		students: await response.json(),
		total: Number( response.headers.get( 'X-WP-Total' ) || 0 ),
	};
}
export async function changeStudentAccess( ids, blocked, fetch = request ) {
	const result = await fetch( {
		path: '/ohmylms/v1/students' + ( blocked ? '' : '/unban' ),
		method: 'POST',
		data: { ids },
	} );
	if ( result.status !== 'success' ) {
		throw Error( result.message || 'Could not update student access.' );
	}
	return result;
}
