import { createElement, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useMenuHighlight } from '../menuHighlight';
import { HubContext } from './context';
import { HUB_TABS } from './hubRoutes.mjs';
import { MovableTabs } from '../navigation/MovableTabs';
const tabLabels = () => ( {
	courses: __( 'Courses', 'ohmylms' ),
	lessons: __( 'Lessons', 'ohmylms' ),
	quizzes: __( 'Quizzes', 'ohmylms' ),
	exams: __( 'Exams', 'ohmylms' ),
	'question-bank': __( 'Question Bank', 'ohmylms' ),
	assignments: __( 'Assignments', 'ohmylms' ),
	skills: __( 'Skills', 'ohmylms' ),
	curriculum: __( 'Curriculum', 'ohmylms' ),
	bundles: __( 'Course Bundles', 'ohmylms' ),
} );

/** Keep the Content Hub submenu entry highlighted while a hub screen or an editor opened from it is shown. */
export function useContentHubMenu() {
	useMenuHighlight( 'a[href$="#/content-hub"]' );
}

/**
 * Frame shared by every hub tab: title and section tabs.
 * @param root0
 * @param root0.active
 * @param root0.children
 */
export function ContentHubFrame( { active, children } ) {
	useContentHubMenu();
	const labels = tabLabels();
	return (
		<section className="ohmylms-content-hub">
			<header className="ohmylms-content-hub-header">
				<h1>{ __( 'Content Hub', 'ohmylms' ) }</h1>
			</header>
			<MovableTabs
				scope="content-hub"
				tabs={ HUB_TABS }
				labels={ labels }
				active={ active }
				label={ __( 'Content Hub sections', 'ohmylms' ) }
			/>
			<HubContext.Provider value={ { active } }>
				{ children }
			</HubContext.Provider>
		</section>
	);
}

/**
 * Wrap an existing screen as a hub tab.
 * @param Component
 * @param active
 */
export function contentHubScreen( Component, active ) {
	function ContentHubScreen( props ) {
		return (
			<ContentHubFrame active={ active }>
				<Component { ...props } />
			</ContentHubFrame>
		);
	}
	ContentHubScreen.displayName = `ContentHub(${ active })`;
	return ContentHubScreen;
}

/**
 * A screen that sends an address which no longer has a page on to `resolve(hash)`, replacing it in the history.
 * @param resolve
 */
export function redirectScreen( resolve ) {
	function RedirectScreen() {
		useEffect( () => {
			const hash = window.location.hash;
			window.location.replace(
				`${ window.location.pathname }${ window.location.search }#${ resolve( hash ) }`
			);
		}, [] );
		return null;
	}
	RedirectScreen.displayName = 'ContentHubRedirect';
	return RedirectScreen;
}

/**
 * Wrap a core screen that is opened from the hub (course editor, lesson editor, reports).
 * @param Component
 */
export function withContentHubMenu( Component ) {
	function WithContentHubMenu( props ) {
		useContentHubMenu();
		return <Component { ...props } />;
	}
	WithContentHubMenu.displayName = `WithContentHubMenu(${ Component.displayName || Component.name || 'Screen' })`;
	return WithContentHubMenu;
}
