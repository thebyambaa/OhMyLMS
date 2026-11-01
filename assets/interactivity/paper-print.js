/**
 * Print page (OhMyLMS\Exams\PrintPaper): the paper is server-rendered, so this only waits for math,
 * fonts and images to settle before offering the Print button, and opens the print dialog when the
 * link asked for it.
 */
import { hasMath, waitFor } from './paper/model.mjs';

const sheet = document.querySelector( '.ohmylms-print-sheet' );
const toolbar = /** @type {HTMLElement|null} */ (
	document.querySelector( '.ohmylms-print-toolbar' )
);
const button = /** @type {HTMLButtonElement|null} */ (
	document.querySelector( '.ohmylms-print-button' )
);
const status = document.querySelector( '.ohmylms-print-status' );

/** @param {Element} page The print sheet. */
async function settled( page ) {
	if ( window.ohmylmsMath?.enabled && hasMath( page.textContent ) ) {
		// The math loader typesets the sheet as soon as its runtime loads.
		await waitFor( () => window.OhMyLMSMath, { timeout: 10000 } );
	}
	await document.fonts?.ready;
	await Promise.all(
		[ ...page.querySelectorAll( 'img' ) ].map( ( image ) =>
			image.complete
				? null
				: new Promise( ( resolve ) => {
						image.addEventListener( 'load', resolve, {
							once: true,
						} );
						image.addEventListener( 'error', resolve, {
							once: true,
						} );
					} )
		)
	);
	await new Promise( ( resolve ) => window.setTimeout( resolve, 300 ) );
}

if ( sheet && button ) {
	// PrintPaper prints the status region and its labels in the same toolbar as the button.
	const region = /** @type {Element} */ ( status );
	const labels = /** @type {HTMLElement} */ ( toolbar ).dataset;
	button.hidden = false;
	region.textContent = labels.preparing || '';
	button.addEventListener( 'click', () => window.print() );
	settled( sheet ).then( () => {
		document.body.dataset.ready = '1';
		region.textContent = labels.ready || '';
		button.disabled = false;
		if ( 'autoPrint' in document.body.dataset ) {
			window.print();
		}
	} );
}
