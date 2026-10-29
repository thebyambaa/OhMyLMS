import { useEffect, useRef } from '@wordpress/element';
import { Outlet, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import type { MouseEvent } from 'react';
import type { FeatureDefinition } from '../features/types';
import { Navigation } from './Navigation';
import { NoticesRegion } from './NoticesRegion';

/**
 * Page chrome: skip link, banner, navigation and the main landmark that routes render into.
 *
 * The skip link is a handler, not a `#fragment` jump, because the hash is owned by the router.
 * After a navigation, focus moves to `main` so keyboard and screen reader users land on the new page.
 *
 * @param props          Component props.
 * @param props.features Features to show in the navigation.
 */
export function AppLayout( {
	features,
}: {
	features: readonly FeatureDefinition[];
} ) {
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

	const skipToContent = ( event: MouseEvent< HTMLAnchorElement > ) => {
		event.preventDefault();
		mainRef.current?.focus();
	};

	return (
		<div className="ohmylms-app-layout">
			<a
				className="ohmylms-app-skip-link"
				href="#ohmylms-app-main"
				onClick={ skipToContent }
			>
				{ __( 'Skip to content', 'ohmylms' ) }
			</a>
			<header className="ohmylms-app-header">
				<h1>{ __( 'OhMyLMS', 'ohmylms' ) }</h1>
				<Navigation features={ features } />
			</header>
			<main
				id="ohmylms-app-main"
				ref={ mainRef }
				tabIndex={ -1 }
				className="ohmylms-app-main"
			>
				<NoticesRegion />
				<Outlet />
			</main>
		</div>
	);
}
