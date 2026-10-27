/** @jsx createElement */
import { createElement } from '@wordpress/element';

/**
 * Editor panels of assessment profiles. A profile that names a panel (see Assessment\Profiles) gets it in
 * the quiz editor's sidebar. The registry is a global so an add-on's own script can register a panel for
 * its kind without editing this plugin: `window.ohmylmsProfilePanels[ 'my-kind' ] = Component`.
 */
const registry = () => {
	window.ohmylmsProfilePanels = window.ohmylmsProfilePanels || {};
	return window.ohmylmsProfilePanels;
};

/**
 * Register the panel component for a profile's panel id.
 *
 * @param {string}                                    id        Panel id named by the profile.
 * @param {(props: { data: Object }) => Element|null} component Component receiving the profile's editor data as `data`.
 */
export function registerProfilePanel( id, component ) {
	registry()[ id ] = component;
}

/**
 * The panel of the open quiz's profile, or nothing when its profile has none.
 *
 * @return {Element|null} Panel.
 */
export function ProfilePanel() {
	const profile = window.ohmylmsNativeLesson?.profile;
	const Panel = profile ? registry()[ profile.panel ] : null;
	return Panel ? <Panel data={ profile.data } /> : null;
}
