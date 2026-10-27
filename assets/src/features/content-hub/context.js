import { createContext } from '@wordpress/element';

/** Set by the hub frame, so a tab body (e.g. the Skills library) can drop its own page header. */
export const HubContext = createContext( null );
