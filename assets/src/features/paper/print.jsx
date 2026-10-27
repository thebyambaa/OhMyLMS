/** @jsx createElement */
import { createElement, createRoot } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { loadMath, mathEnabled } from '../math/loader.mjs';
import { AnswerSheet } from './AnswerSheet';
import { Items, annotate } from './paperDocument';
import { paperSettings, paperVersion, versionItems } from './paperModel.mjs';

/**
 * The printed exam: core blocks, paper blocks and questions in document order, with the
 * renderers the editor's paper view uses.
 *
 * @param {Object} props      Properties.
 * @param {Object} props.data Print page data from OhMyLMS\Exams\PrintPaper.
 * @return {Element} Paper.
 */
function PrintedPaper( { data } ) {
	const settings = paperSettings( data.settings );
	const version = paperVersion( data.version, settings );
	// Versions B to D shuffle questions before numbering, so numbers follow the printed order.
	const items = versionItems( data.items, version );
	const sheet = [ 'sheet', 'sheet-key' ].includes( data.variant );
	const context = {
		data,
		settings,
		version,
		showAnswers: [ 'key', 'sheet-key' ].includes( data.variant ),
		derived: annotate( items, settings ),
	};
	const banner = {
		key: __( 'Answer key', 'ohmylms' ),
		'sheet-key': __( 'Marking sheet', 'ohmylms' ),
	}[ data.variant ];
	return (
		<>
			{ banner && (
				<p className="ohmylms-print-key-banner">
					{ settings.versions > 1
						? sprintf(
								/* translators: 1: answer key or marking sheet, 2: version letter. */
								__( '%1$s: version %2$s', 'ohmylms' ),
								banner,
								version
							)
						: banner }
				</p>
			) }
			{ ! data.items.length && (
				<p>{ __( 'This exam has no questions yet.', 'ohmylms' ) }</p>
			) }
			{ sheet ? (
				<AnswerSheet context={ context } />
			) : (
				<Items items={ items } context={ context } />
			) }
		</>
	);
}

/**
 * Resolve once math, fonts and images have settled, so the print matches the screen.
 *
 * @param {Element} root Paper root.
 * @return {Promise<void>} Settled.
 */
async function settled( root ) {
	if ( mathEnabled() ) {
		// Typeset the whole sheet, so paper blocks and other content get math as questions do.
		const runtime = await loadMath().catch( () => null );
		runtime?.mount( root );
	}
	await document.fonts?.ready;
	await Promise.all(
		[ ...root.querySelectorAll( 'img' ) ].map( ( image ) =>
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
	// Let the math runtime typeset what the renderers mounted.
	await new Promise( ( resolve ) => window.setTimeout( resolve, 300 ) );
}

const source = document.getElementById( 'ohmylms-print-data' );
const root = document.getElementById( 'ohmylms-print-root' );
if ( source && root ) {
	const data = JSON.parse( source.textContent );
	// The whole sheet is exam content: LaTeX delimiters apply everywhere on it, not only in
	// questions. Prices such as "$5 and $10" stay text under the delimiter rules.
	root.setAttribute( 'data-ohmylms-latex', '' );
	createRoot( root ).render( <PrintedPaper data={ data } /> );
	const button = document.querySelector( '.ohmylms-print-button' );
	const status = document.querySelector( '.ohmylms-print-status' );
	button?.addEventListener( 'click', () => window.print() );
	settled( root ).then( () => {
		document.body.dataset.ready = '1';
		if ( status ) {
			status.textContent = __( 'Ready to print.', 'ohmylms' );
		}
		if ( button ) {
			button.disabled = false;
		}
		if ( data.autoPrint ) {
			window.print();
		}
	} );
}
