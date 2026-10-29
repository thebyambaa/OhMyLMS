import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
} from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Segmented icon toggle: a `ToggleGroupControl` with one icon option per
 * entry of `options` (`{ value, label, icon }`, keyed by position, none by
 * default). The remaining props (`value`, `onChange( value )`, ...) are
 * forwarded.
 *
 * @param {Object} props           Component props.
 * @param {Array}  [props.options] Toggle options.
 * @return {import('react').ReactElement} Toggle group.
 */
export function RadioGroupIconWP( { options = [], ...rest } ) {
	return createElement(
		ToggleGroupControl,
		{
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			...rest,
		},
		options?.map( ( option, index ) =>
			createElement( ToggleGroupControlOptionIcon, {
				key: index,
				icon: option?.icon,
				value: option?.value,
				label: option?.label,
			} )
		)
	);
}
