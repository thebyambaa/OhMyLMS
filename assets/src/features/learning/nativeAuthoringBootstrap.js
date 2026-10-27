import * as api from '../../extensions/api.mjs';
import { createRegistry } from '../../extensions/registry.mjs';

// Native screens expose the same registration contract without loading the LMS app.
if ( typeof window !== 'undefined' && ! window.ohmylms?.extensions ) {
	const registry = Object.freeze( { ...createRegistry(), api } );
	window.ohmylms = { ...window.ohmylms, extensions: registry };
	window.dispatchEvent(
		new CustomEvent( 'ohmylms:extensions-ready', { detail: registry } )
	);
}
