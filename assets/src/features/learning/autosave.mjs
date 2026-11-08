// @ts-check
/** Post types of the native editors that save as the author edits. */
export const AUTOSAVE_TYPES = [
	'ohmylms-lesson',
	'ohmylms-quiz',
	'ohmylms-question',
];

/** Quiet time after an edit before it is saved, in milliseconds. */
export const AUTOSAVE_DELAY = 1500;

/**
 * @typedef {Object} EditorState
 * @property {string}  type            Post type.
 * @property {string}  status          Post status.
 * @property {string}  title           Edited title.
 * @property {boolean} dirty           Edits exist that are not saved.
 * @property {boolean} saving          A save is running.
 * @property {boolean} locked          Something blocks saving, such as unsaved settings.
 * @property {string}  signature       Fingerprint of the unsaved edits.
 * @property {string}  failedSignature Fingerprint of the edits whose last save failed.
 */

/**
 * Whether the editor should save by itself now. A failed save is not repeated until the edits change, and
 * a post that was never saved needs a title first, so opening a new item and leaving makes no empty draft.
 *
 * @param {EditorState} state Editor state.
 * @return {boolean} True when a save should start.
 */
export function shouldAutosave( state ) {
	if ( ! AUTOSAVE_TYPES.includes( state.type ) ) {
		return false;
	}
	if ( ! state.dirty || state.saving || state.locked ) {
		return false;
	}
	if ( 'auto-draft' === state.status && '' === state.title.trim() ) {
		return false;
	}
	return state.signature !== state.failedSignature;
}

/**
 * Whether a click on a link leaves the page and so has to wait for a save.
 *
 * @param {{href: string, target: string, download: boolean, modified: boolean}} link Link clicked.
 * @param {string}                                                               here Current page address.
 * @return {boolean} True for a plain same-tab navigation to another page.
 */
export function leavesPage( link, here ) {
	if ( link.modified || link.download ) {
		return false;
	}
	if ( link.target && '_self' !== link.target ) {
		return false;
	}
	try {
		const next = new URL( link.href, here );
		const current = new URL( here );
		if ( ! [ 'http:', 'https:' ].includes( next.protocol ) ) {
			return false;
		}
		next.hash = '';
		current.hash = '';
		return next.href !== current.href;
	} catch {
		return false;
	}
}
