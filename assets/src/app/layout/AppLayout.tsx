import { useEffect, useRef } from '@wordpress/element';
import { Outlet, useLocation } from 'react-router-dom';
import { ExtensionTabs } from './ExtensionTabs';
import { NoticesRegion } from './NoticesRegion';

/** How long a list may take to show again after its address changed before focus falls back to `main`. */
const RESTORE_WAIT_MS = 3000;
const RESTORE_STEP_MS = 100;

interface FocusMark {
	/** `href` attribute of the focused link. */
	href: string | null;
	/** Selector for an equivalent control, such as `.tablenav.top .next-page`. */
	like: string | null;
}

function markOf( element: Element ): FocusMark {
	const tablenav = element.closest( '.tablenav' );
	const position = tablenav?.classList.contains( 'bottom' )
		? 'bottom'
		: 'top';
	const kind = Array.from( element.classList ).find( ( name ) =>
		/^(first|prev|next|last)-page$/.test( name )
	);
	return {
		href: element.getAttribute( 'href' ),
		like: tablenav && kind ? `.tablenav.${ position } .${ kind }` : null,
	};
}

function targetOf( main: HTMLElement, mark: FocusMark ): HTMLElement | null {
	if ( mark.href ) {
		const same = Array.from(
			main.querySelectorAll< HTMLElement >( 'a[href]' )
		).find( ( link ) => link.getAttribute( 'href' ) === mark.href );
		if ( same ) {
			return same;
		}
	}
	return mark.like ? main.querySelector< HTMLElement >( mark.like ) : null;
}

/**
 * The main landmark that routes render into. The admin page around it (heading, tabs and sections) is
 * printed by includes/Admin/AdminNavigation.php.
 *
 * After a move to another screen, focus moves to `main` so keyboard and screen reader users land on
 * the new screen. After a list changes its address (a view, sorting or page link), the list reloads;
 * when the focused link went away with it, focus returns to the same link (or the same page link) once
 * the list is back, or to `main`.
 */
export function AppLayout() {
	const mainRef = useRef< HTMLElement >( null );
	const { pathname, search } = useLocation();
	const isFirstRender = useRef( true );
	const lastFocus = useRef< FocusMark | null >( null );
	useEffect( () => {
		// WordPress already assigns role=main to its page wrapper. The app supplies
		// the actual content landmark, so avoid nesting two main landmarks.
		const host = mainRef.current?.closest( '#wpbody[role="main"]' );
		if ( ! host ) {
			return;
		}
		host.removeAttribute( 'role' );
		return () => host.setAttribute( 'role', 'main' );
	}, [] );

	useEffect( () => {
		const main = mainRef.current;
		if ( ! main ) {
			return undefined;
		}
		const onFocus = ( event: FocusEvent ) => {
			lastFocus.current =
				event.target instanceof Element && event.target !== main
					? markOf( event.target )
					: null;
		};
		main.addEventListener( 'focusin', onFocus );
		return () => main.removeEventListener( 'focusin', onFocus );
	}, [] );

	useEffect( () => {
		if ( isFirstRender.current ) {
			isFirstRender.current = false;
			return;
		}
		mainRef.current?.focus();
	}, [ pathname ] );

	useEffect( () => {
		const main = mainRef.current;
		const mark = lastFocus.current;
		if ( ! main || ! mark ) {
			return undefined;
		}
		let waited = 0;
		const timer = window.setInterval( () => {
			waited += RESTORE_STEP_MS;
			const focused = main.ownerDocument.activeElement;
			const lost =
				! focused ||
				focused === main.ownerDocument.body ||
				! focused.isConnected;
			if ( ! lost ) {
				if ( waited >= RESTORE_WAIT_MS ) {
					window.clearInterval( timer );
				}
				return;
			}
			const target = targetOf( main, mark );
			if ( target ) {
				target.focus();
				window.clearInterval( timer );
			} else if ( waited >= RESTORE_WAIT_MS ) {
				main.focus();
				window.clearInterval( timer );
			}
		}, RESTORE_STEP_MS );
		return () => window.clearInterval( timer );
		// Only a change of the list address restores focus; a new screen focuses `main` above.
	}, [ search ] );

	return (
		<main
			id="ohmylms-app-main"
			ref={ mainRef }
			tabIndex={ -1 }
			className="ohmylms-app-main"
		>
			<ExtensionTabs />
			<NoticesRegion />
			<Outlet />
		</main>
	);
}
