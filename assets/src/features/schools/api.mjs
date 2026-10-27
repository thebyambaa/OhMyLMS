export async function request( config, path, options = {} ) {
	const response = await fetch( config.api + path, {
		credentials: 'same-origin',
		...options,
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': config.nonce,
			...options.headers,
		},
	} );
	const data = await response.json();
	if ( ! response.ok ) {
		throw new Error( data.message || 'The request failed.' );
	}
	return data;
}

export function csvCell( value ) {
	let text = String( value ?? '' );
	// Spreadsheet exports must not turn student-controlled names into formulas.
	if ( /^[\s]*[=+@-]/.test( text ) ) {
		text = "'" + text;
	}
	return '"' + text.replaceAll( '"', '""' ) + '"';
}

export function exportCsv( rows ) {
	const keys = Object.keys( rows[ 0 ] || {} );
	return [ keys, ...rows.map( ( row ) => keys.map( ( key ) => row[ key ] ) ) ]
		.map( ( row ) => row.map( csvCell ).join( ',' ) )
		.join( '\r\n' );
}
