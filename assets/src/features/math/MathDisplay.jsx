/** @jsx createElement */
import { createElement, useEffect, useRef } from '@wordpress/element';
import { loadMath, mathEnabled } from './loader.mjs';

/**
 * Readonly expression display, including source-preserving fallback.
 * @param {Object} props        Properties.
 * @param {string} props.source Original expression.
 * @return {Object} Display.
 */
export function MathDisplay( { source = '' } ) {
	const host = useRef( null );
	useEffect( () => {
		let disposed = false;
		let cleanup;
		if ( mathEnabled() && source ) {
			loadMath()
				.then( ( runtime ) => {
					if ( ! disposed ) {
						cleanup = runtime.display(
							host.current,
							String( source )
						);
					}
				} )
				.catch( () => {} );
		}
		return () => {
			disposed = true;
			cleanup?.();
		};
	}, [ source ] );
	return (
		<span ref={ host } data-ohmylms-react-math>
			<span data-math-source>{ source }</span>
		</span>
	);
}
