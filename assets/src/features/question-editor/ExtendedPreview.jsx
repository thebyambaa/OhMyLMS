import { createElement, useEffect, useRef } from '@wordpress/element';
import { extendedPublicView } from './extendedModel.mjs';
/**
 * Render the same public widgets used by frozen learner deliveries.
 * @param root0
 * @param root0.type
 * @param root0.settings
 */
export function ExtendedPreview( { type, settings } ) {
	const holder = useRef( null );
	useEffect( () => {
		const node = holder.current;
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
