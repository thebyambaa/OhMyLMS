/**
 * Stand-in for `@wordpress/date`, which exists only in the WordPress admin.
 *
 * @param {string} date    Date.
 * @param {string} pattern Format.
 * @returns {string} Echo of the arguments.
 */
export function format( date, pattern ) {
	return 'format:' + date + ':' + pattern;
}

/**
 * Stand-in for `dateI18n`.
 *
 * @param {string} date    Date.
 * @param {string} pattern Format.
 * @returns {string} Echo of the arguments.
 */
export function dateI18n( date, pattern ) {
	return 'dateI18n:' + date + ':' + pattern;
}
