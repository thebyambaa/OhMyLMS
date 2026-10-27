/**
 * Offer text as a file download. A byte order mark is added so Excel opens UTF-8 text (Mongolian and
 * other scripts) correctly instead of guessing a legacy encoding.
 * @param text
 * @param name
 * @param type
 */
export function download( text, name, type = 'text/csv;charset=utf-8' ) {
	const blob = new Blob( [ '﻿', text ], { type } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = name;
	link.style.display = 'none';
	document.body.appendChild( link );
	link.click();
	link.remove();
	setTimeout( () => URL.revokeObjectURL( url ), 1000 );
}
