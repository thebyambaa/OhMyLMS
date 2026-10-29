import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { createElement } from '@wordpress/element';

/**
 * Segmented text toggle: a `ToggleGroupControl` with one text option per
 * entry of `options` (`{ value, label, disabled }`, keyed by position). The
 * remaining props (`value`, `onChange( value )`, `label`, ...) are forwarded.
 * As in the recovered wrapper, `options` is required.
 *
 * @param {Object} props         Component props.
 * @param {Array}  props.options Toggle options.
 * @return {import('react').ReactElement} Toggle group.
 */
export function RadioGroupWP( { options, ...rest } ) {
	return createElement(
		ToggleGroupControl,
		{
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
			...rest,
		},
		options.map( ( option, index ) =>
			createElement( ToggleGroupControlOption, {
				key: index,
				value: option?.value,
				label: option?.label,
				disabled: option?.disabled,
			} )
		)
	);
}
