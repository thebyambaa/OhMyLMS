/**
 * Let core initialize its own iframe when a browser cannot navigate to its blob.
 * The original core-generated document is loaded through srcdoc instead.
 *
 * @param {HTMLIFrameElement} frame Native editor canvas.
 * @return {Promise<boolean>} Whether the unloaded canvas was recovered.
 */
export async function recoverNativeCanvas( frame ) {
	const source = frame.getAttribute( 'src' );
	if ( ! source?.startsWith( 'blob:' ) || frame.hasAttribute( 'srcdoc' ) ) {
		return false;
	}
	try {
		const doc = frame.contentDocument;
		if ( doc?.head?.childNodes.length || doc?.body?.childNodes.length ) {
			return false;
		}
		const response = await frame.ownerDocument.defaultView.fetch( source );
		if ( ! response.ok ) {
			return false;
		}
		const html = await response.text();
		if ( ! frame.isConnected || frame.getAttribute( 'src' ) !== source ) {
			return false;
		}
		// Core's load listener mounts the editor into this same document.
		frame.setAttribute( 'srcdoc', html );
		return true;
	} catch {
		// Keep core's normal loading behavior if the browser cannot read the blob.
		return false;
	}
}

/** Observe only the native editor canvas; healthy iframe loads are unchanged. */
export function installNativeCanvasRecovery() {
	const pending = new WeakSet();
	const inspect = () => {
		for ( const frame of document.querySelectorAll(
			'iframe[name="editor-canvas"]'
		) ) {
			if ( pending.has( frame ) ) {
				continue;
			}
			pending.add( frame );
			window.setTimeout( () => recoverNativeCanvas( frame ), 2000 );
		}
	};
	const observer = new MutationObserver( inspect );
	observer.observe( document.body, { childList: true, subtree: true } );
	inspect();
	window.addEventListener( 'pagehide', () => observer.disconnect(), {
		once: true,
	} );
}
