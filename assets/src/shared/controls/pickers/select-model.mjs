/**
 * Pure helpers behind `AdvancedSelectWP` and `SearchSelectWP`.
 *
 * The recovered controls are thin wrappers over react-select v5 (`Select` with the state
 * manager, and its `Async` variant): no custom styles, theme, messages or debouncing are set,
 * so all of those stay react-select defaults. The only own behaviour is the class names and
 * how props reach react-select. `onSearch` (used by the course pickers) is forwarded like any
 * other prop and react-select ignores it, exactly as in the recovered build.
 */

export const SEARCH_SELECT_CLASS = 'ohmylms-search-select';
export const ADVANCED_SELECT_CLASS =
	'ohmylms-advanced-select ohmylms-search-select auto-height';

/**
 * Props for the react-select `Select` behind `AdvancedSelectWP`.
 *
 * `className` is lifted out of the incoming props and appended after the fixed classes
 * (a trailing space remains when it is empty, as in the recovered markup).
 *
 * @param {Object} props             Incoming props.
 * @param {string} [props.className] Extra class names; defaults to an empty string.
 * @return {Object} Props for react-select.
 */
export function getAdvancedSelectProps( props ) {
	const { className = '', ...rest } = props;
	return { className: `${ ADVANCED_SELECT_CLASS } ${ className }`, ...rest };
}

/**
 * Props for the react-select `Async` behind `SearchSelectWP`.
 *
 * The recovered build builds the class from `customClass` (the literal text `undefined` when it
 * is missing) and then spreads every incoming prop over it, so an explicit `className` replaces
 * the computed one and `customClass` is still forwarded.
 *
 * @param {Object} props Incoming props.
 * @return {Object} Props for react-select Async.
 */
export function getSearchSelectProps( props ) {
	return {
		className: `${ SEARCH_SELECT_CLASS } ${ props.customClass }`,
		...props,
	};
}

/**
 * Whether an async search can run: react-select's Async only searches when `loadOptions` is a
 * function; otherwise `defaultOptions` (an array) is the whole option list.
 *
 * @param {Object} props Incoming props.
 * @return {boolean} True when `loadOptions` is callable.
 */
export function hasAsyncSearch( props ) {
	return 'function' === typeof props?.loadOptions;
}

/**
 * Normalises react-select's `onChange` payload to an array of option values: a single option,
 * an array (`isMulti`), or `null` (cleared).
 *
 * @param {Object|Array|null} selection Option(s) passed to `onChange`.
 * @return {Array} Option values.
 */
export function getSelectedValues( selection ) {
	if ( null === selection || undefined === selection ) {
		return [];
	}
	return ( Array.isArray( selection ) ? selection : [ selection ] )
		.filter( Boolean )
		.map( ( option ) => option.value );
}
