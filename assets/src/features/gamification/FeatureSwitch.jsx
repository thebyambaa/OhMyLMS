import { createElement } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
export function FeatureSwitch( {
	settings,
	setSettings,
	title,
	description,
	label,
} ) {
	return (
		<section className="ohmylms-feature-settings-header">
			<div className="ohmylms-feature-settings-header__description">
				<h2>{ title }</h2>
				<p>{ description }</p>
			</div>
			<ToggleControl
				label={ label || __( 'Enable this feature', 'ohmylms' ) }
				checked={
					settings.feature_enabled === undefined ||
					Boolean( settings.feature_enabled )
				}
				onChange={ ( _featureEnabled ) =>
					setSettings( {
						...settings,
						feature_enabled: _featureEnabled,
					} )
				}
			/>
		</section>
	);
}
