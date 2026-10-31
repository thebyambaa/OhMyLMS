/**
 * Print page (OhMyLMS\Exams\PrintPaper): the paper is server-rendered, so this only waits for math,
 * fonts and images to settle before offering the Print button, and opens the print dialog when the
 * link asked for it.
 */
import { hasMath, waitFor } from './paper/model.mjs';

const sheet = document.querySelector( '.ohmylms-print-sheet' );
const toolbar = document.querySelector( '.ohmylms-print-toolbar' );
const button = document.querySelector( '.ohmylms-print-button' );
const status = document.querySelector( '.ohmylms-print-status' );

async function settled() {
	if ( window.ohmylmsMath?.enabled && hasMath( sheet.textContent ) ) {
		// The math loader typesets the sheet as soon as its runtime loads.
		await waitFor( () => window.OhMyLMSMath, { timeout: 10000 } );
	}
	await document.fonts?.ready;
	await Promise.all(
		[ ...sheet.querySelectorAll( 'img' ) ].map( ( image ) =>
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
	button.hidden = false;
	status.textContent = toolbar.dataset.preparing || '';
	button.addEventListener( 'click', () => window.print() );
	settled().then( () => {
		document.body.dataset.ready = '1';
		status.textContent = toolbar.dataset.ready || '';
		button.disabled = false;
		if ( 'autoPrint' in document.body.dataset ) {
			window.print();
		}
	} );
}
