/**
 * Notice list rules shared by every admin screen.
 *
 * Kept free of React so the behaviour can be tested directly: at most three notices are
 * shown, the oldest is dropped first, and a notice may remove itself after a delay.
 */
export const MAX_NOTICES = 3;
export const DEFAULT_DELAY = 3000;

/**
 * Append a notice, dropping the oldest beyond the limit.
 *
 * @param {Array<{id: number, status: string, message: unknown}>} notices Current notices.
 * @param {{id: number, status: string, message: unknown}}        notice  Notice to add.
 * @return {Array} New list; the input is not modified.
 */
export function addNotice( notices, notice ) {
	return [ ...notices, notice ].slice( -MAX_NOTICES );
}

/**
 * Remove a notice by id.
 *
 * @param {Array}  notices Current notices.
 * @param {number} id      Notice id.
 * @return {Array} New list without the notice.
 */
export function removeNotice( notices, id ) {
	return notices.filter( ( notice ) => notice.id !== id );
}

/**
 * Build a notice from the positional arguments `openNotificationWithIcon` accepts.
 *
 * @param {string}  status  WordPress notice status: info (default), success, warning or error.
 * @param {unknown} message Notice content.
 * @param {number}  id      Unique id, normally `Date.now()`.
 * @return {{id: number, status: string, message: unknown}} Notice.
 */
export function createNotice( status = 'info', message = '', id = Date.now() ) {
	return { id, status, message };
}
