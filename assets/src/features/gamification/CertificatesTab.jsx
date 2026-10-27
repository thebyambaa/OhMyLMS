import { createElement, useEffect } from '@wordpress/element';
import { useMenuHighlight } from '../menuHighlight';
import { CERTIFICATES_TAB } from './model.mjs';

/** The Gamification entry in the admin menu. */
const GAMIFICATION_LINK = 'a[href*="#/gamification/"]';

export function useGamificationMenu() {
	useMenuHighlight( GAMIFICATION_LINK );
}

/**
 * The application's Certificates screen as a Gamification tab. The screen marks its own (now removed)
 * menu entry on every render, so the Gamification entry is highlighted again after it. `panels` renders
 * what extensions added to the old Certificates screen (the Gamification screen already carries the
 * generic screen slots).
 * @param Screen
 * @param panels
 */
export function certificatesTab( Screen, panels = () => null ) {
	function CertificatesTab() {
		useGamificationMenu();
		return (
			<>
				<Screen />
				{ panels() }
			</>
		);
	}
	CertificatesTab.displayName = 'GamificationCertificates';
	return CertificatesTab;
}

/**
 * A screen opened from the Certificates tab (the template editor) keeps the Gamification entry highlighted.
 * @param Screen
 */
export function withGamificationMenu( Screen ) {
	function WithGamificationMenu( props ) {
		useGamificationMenu();
		return <Screen { ...props } />;
	}
	WithGamificationMenu.displayName = `WithGamificationMenu(${ Screen.displayName || Screen.name || 'Screen' })`;
	return WithGamificationMenu;
}

/** The old `#/certificates` address opens the tab, so links and bookmarks keep working. */
export function CertificatesMoved() {
	useEffect( () => {
		window.location.replace( `#/gamification/${ CERTIFICATES_TAB }` );
	}, [] );
	return null;
}
