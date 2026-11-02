import { createElement, useEffect, useRef } from '@wordpress/element';
import { extendedPublicView } from './extendedModel.mjs';

/** @typedef {import('./editorTypes').Data} Data */

/**
 * Render the same public widgets used by frozen learner deliveries.
 * @param {Object} root0
 * @param {string} root0.type
 * @param {Data}   root0.settings
 */
export function ExtendedPreview( { type, settings } ) {
	const holder = useRef( /** @type {HTMLDivElement|null} */ ( null ) );
	useEffect( () => {
		const node = /** @type {HTMLDivElement} */ ( holder.current );
		node.textContent = '';
		window.OhMyLMSInteractive?.render(
			node,
			type,
			extendedPublicView( type, settings )
		);
		return () => {
			node.textContent = '';
		};
	}, [ type, settings ] );
	return createElement( 'div', {
		ref: holder,
		className: 'ohmylms-extended-preview',
	} );
}
