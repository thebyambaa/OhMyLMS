import { createElement } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Select } from '../../ui/Select';
import { Card, CardTitle } from '../../ui/Card';
import { Button } from '../../ui/Button';

/**
 * Compatibility entry point for Colors & fonts. All palette edits use the shared Design system.
 *
 * @param {Object} props       Factory dependencies.
 * @param {string} props.store Existing design settings store.
 * @return {() => Object} Settings component.
 */
export function createThemeSettings( { store } ) {
	return function ThemeSettings() {
		const settings = useSelect(
			( select ) => select( store ).getDesignSettings() || {},
			[]
		);
		const { updateDesignSettings } = useDispatch( store );
		const fonts = window.ohmylmsDesign?.fonts || [];
		return createElement(
			Card,
			null,
			createElement(
				CardTitle,
				null,
				__( 'One design system', 'ohmylms' )
			),
			createElement(
				'p',
				null,
				__(
					'Colors, roundness and spacing are shared by admin screens and learner pages. Edit them in Design system.',
					'ohmylms'
				)
			),
			createElement(
				Button,
				{
					href: '#/settings/design-system-settings',
					variant: 'primary',
				},
				__( 'Open design system', 'ohmylms' )
			),
			createElement( Select, {
				label: __( 'Shared font', 'ohmylms' ),
				value: settings.ohmylms_font_family?.value || 'inherit',
				options: [
					{ value: 'inherit', label: __( 'Theme font', 'ohmylms' ) },
					{ value: 'system', label: __( 'System font', 'ohmylms' ) },
					...fonts.map( ( font ) => ( {
						value: font,
						label: font,
					} ) ),
				],
				onChange: ( event ) =>
					updateDesignSettings( {
						ohmylms_font_family: { value: event.target.value },
					} ),
				help: __(
					'Save these settings to apply the font across OhMyLMS.',
					'ohmylms'
				),
			} )
		);
	};
}
