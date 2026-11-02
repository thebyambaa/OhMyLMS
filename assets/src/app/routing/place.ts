import { createContext, useContext } from '@wordpress/element';
import type { AdminPlace } from './address';

const PlaceContext = createContext< AdminPlace | null >( null );

/** Supplies the server's place; set by the query router. */
export const PlaceProvider = PlaceContext.Provider;

/**
 * Where the server placed the document (page, tab and section), or null outside the admin pages (tests
 * render screens in a memory router). When set, the page around the app already shows the tabs and
 * sections, so screens do not repeat them.
 *
 * @return The place.
 */
export function useAdminPlace(): AdminPlace | null {
	return useContext( PlaceContext );
}
