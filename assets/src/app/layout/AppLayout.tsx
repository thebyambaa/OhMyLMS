import { useEffect, useRef } from '@wordpress/element';
import { Outlet, useLocation } from 'react-router-dom';
import { ExtensionTabs } from './ExtensionTabs';
import { NoticesRegion } from './NoticesRegion';

/**
 * The main landmark that routes render into. The admin page around it (heading, tabs and sections) is
 * printed by includes/Admin/AdminNavigation.php.
 *
 * After a move inside the tab, focus moves to `main` so keyboard and screen reader users land on the
 * new screen.
 */
export function AppLayout() {
	const mainRef = useRef< HTMLElement >( null );
	const { pathname } = useLocation();
	const isFirstRender = useRef( true );
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
		if ( isFirstRender.current ) {
			isFirstRender.current = false;
			return;
		}
		mainRef.current?.focus();
	}, [ pathname ] );

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
