/**
 * Pure helpers of the colour picker.
 */

/**
 * Class name of the toggle button.
 *
 * @param {boolean} isOpen Whether the picker popover is open.
 * @return {string} Class names; keeps the trailing space of the recovered control.
 */
export function getIndicatorClassName( isOpen ) {
	return `ohmylms-color-indicator-button ${ isOpen ? 'ohmylms-active' : '' }`;
}

/**
 * Colour held after a change from the picker or the reset button.
 *
 * @param {string}  current  Colour currently shown.
 * @param {?Object} change   Change object with a `hex` field.
 * @param {boolean} disabled Whether the control is disabled.
 * @return {string|undefined} The new colour; the current one when disabled.
 */
export function getNextColor( current, change, disabled ) {
	return disabled ? current : change?.hex;
}

/**
 * @param {?Element} container Element holding the picker.
 * @param {?Node}    target    Target of a mouse event.
 * @return {boolean} Whether the event happened outside the container.
 */
export function isOutside( container, target ) {
	return !! container && ! container.contains( target );
}
