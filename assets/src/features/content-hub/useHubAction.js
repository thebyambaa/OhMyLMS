import { useEffect, useRef } from '@wordpress/element';
import { hashQuery } from './hubRoutes.mjs';

/**
 * Run a screen's own action when the address asks for it, e.g. `#/content-hub/exams?action=new`.
 * The command palette (Ctrl+K) opens these addresses, so "New exam" lands on the Exams tab with its
 * dialog already open. The parameter is removed once handled so a reload does not repeat the action.
 *
 * @param {Object<string, () => void>} actions Handlers keyed by `action` value.
 */
export function useHubAction( actions ) {
	const latest = useRef( actions );
	latest.current = actions;
	useEffect( () => {
		const run = () => {
			const params = hashQuery( window.location.hash );
			const handler = latest.current[ params.get( 'action' ) ];
			if ( ! handler ) {
				return;
			}
			params.delete( 'action' );
			const [ path ] = window.location.hash.split( '?' );
			const rest = params.toString();
			window.history.replaceState(
				null,
				'',
				`${ window.location.pathname }${ window.location.search }${ path }${
					rest ? `?${ rest }` : ''
				}`
			);
			handler();
		};
		run();
		window.addEventListener( 'hashchange', run );
		return () => window.removeEventListener( 'hashchange', run );
	}, [] );
}
