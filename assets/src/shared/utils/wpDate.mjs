/**
 * WordPress date helpers.
 *
 * The recovered application read `wp.date` once at load; the admin build resolves
 * `@wordpress/date` to that same global, so the functions are the ones the site configured
 * (timezone, date and time formats, locale).
 */
export { dateI18n, format } from '@wordpress/date';
