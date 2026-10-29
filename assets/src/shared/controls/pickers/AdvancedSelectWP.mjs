import { createElement, memo } from '@wordpress/element';
import Select from 'react-select';
import { getAdvancedSelectProps } from './select-model.mjs';

/**
 * Advanced select: react-select v5 `Select` with the plugin class names. Every prop except
 * `className` goes to react-select unchanged (`options`, `value`, `onChange`, `isMulti`,
 * `isClearable`, `isDisabled`, `closeMenuOnSelect`, `placeholder`, ...); `onChange` receives
 * react-select's option object, array (`isMulti`) or `null`. `onSearch` is accepted but
 * ignored, as in the recovered control.
 *
 * @param {Object} props             react-select props.
 * @param {string} [props.className] Extra class names.
 * @return {import('react').ReactElement} Select.
 */
function AdvancedSelect( props ) {
	return createElement( Select, getAdvancedSelectProps( props ) );
}

export const AdvancedSelectWP = memo( AdvancedSelect );
