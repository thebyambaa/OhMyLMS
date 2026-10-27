import { useEffect, useState } from '@wordpress/element';
import { readComparison } from './comparison.mjs';

const cached = new Map();

/**
 * The domains and levels a shared skill of a subject can be placed in, read once per subject and page.
 * @param {string} [subject] Subject; empty reads the default profile.
 * @return {Object|null} domains, grades (levels), levels, profile and ai_available, or null while loading.
 */
export function usePlacementOptions( subject = '' ) {
	const [ options, setOptions ] = useState( cached.get( subject ) || null );
	useEffect( () => {
		if ( cached.has( subject ) ) {
			setOptions( cached.get( subject ) );
			return;
		}
		let current = true;
		readComparison( 'skill-placement', { subject } )
			.then( ( value ) => {
				cached.set( subject, value );
				if ( current ) {
					setOptions( value );
				}
			} )
			.catch( () => current && setOptions( null ) );
		return () => {
			current = false;
		};
	}, [ subject ] );
	return options;
}

/** Forget cached options after subject profiles change. */
export function clearPlacementOptions() {
	cached.clear();
}
