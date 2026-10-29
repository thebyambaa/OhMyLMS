import { createElement, memo } from '@wordpress/element';
import AsyncSelect from 'react-select/async';
import { getSearchSelectProps } from './select-model.mjs';

/**
 * Search select: react-select v5 `Async` with the plugin class name. Props go to react-select
 * unchanged: `loadOptions` (async search, called with the typed text), `defaultOptions` (array,
 * or `true` to load on mount), `cacheOptions`, `value`, `onChange`, `isMulti`, `isClearable`,
 * `isSearchable`, `placeholder`. `customClass` is appended to the class name.
 *
 * @param {Object} props               react-select Async props.
 * @param {string} [props.customClass] Extra class name.
 * @return {import('react').ReactElement} Async select.
 */
function SearchSelect( props ) {
	return createElement( AsyncSelect, getSearchSelectProps( props ) );
}

export const SearchSelectWP = memo( SearchSelect );
