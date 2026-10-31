import { useEffect, useState } from '@wordpress/element';
import { useLocation, useNavigate } from 'react-router-dom';
import { routeFromHref } from '../../routing/links';

/**
 * Route a link points at.
 *
 * @param anchor Clicked link.
 * @return The in-app path, or `null` when the link is external, a download or opens a new tab.
 */
function internalPath( anchor: HTMLAnchorElement ): string | null {
	const href = anchor.getAttribute( 'href' );
	if ( ! href || anchor.target || anchor.hasAttribute( 'download' ) ) {
		return null;
	}
	return routeFromHref( href );
}

/**
 * Guard against losing unsaved edits.
 *
 * The app uses a query-string router without data-router blockers, so the guard covers the two ways a
 * user leaves: closing or reloading the page (the browser's own prompt, which also covers the server's
 * tab links) and activating an in-app link, which is intercepted so the page can show its own
 * confirmation. Switching gamification sections (the server's section links, or this screen's own tab
 * list) is not leaving: every section keeps its draft.
 *
 * @param dirty Whether there are unsaved changes.
 * @return `pendingPath` is the link waiting for a decision (or `null`); call `stay` to cancel it and
 *         `leave` to continue to it.
 */
export function useUnsavedChangesGuard( dirty: boolean ) {
	const navigate = useNavigate();
	const { pathname } = useLocation();
	const [ pendingPath, setPendingPath ] = useState< string | null >( null );
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const beforeUnload = ( event: BeforeUnloadEvent ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		const onClick = ( event: MouseEvent ) => {
			if (
				event.defaultPrevented ||
				0 !== event.button ||
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.altKey ||
				! ( event.target instanceof Element )
			) {
				return;
			}
			const anchor = event.target.closest( 'a' );
			const path = anchor ? internalPath( anchor ) : null;
			if ( path && path.split( '?' )[ 0 ] !== pathname ) {
				event.preventDefault();
				event.stopPropagation();
				setPendingPath( path );
			}
		};
		window.addEventListener( 'beforeunload', beforeUnload );
		document.addEventListener( 'click', onClick, true );
		return () => {
			window.removeEventListener( 'beforeunload', beforeUnload );
			document.removeEventListener( 'click', onClick, true );
		};
	}, [ dirty, pathname ] );
	return {
		pendingPath,
		stay: () => setPendingPath( null ),
		leave: () => {
			const path = pendingPath;
			setPendingPath( null );
			if ( path ) {
				navigate( path );
			}
		},
	};
}
