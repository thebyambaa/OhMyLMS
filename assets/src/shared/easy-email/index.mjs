import * as published from 'easy-email-core';
import { createCompatibleCore } from './core.mjs';

export { EASY_EMAIL_CORE_EXPORTS } from './export-names.mjs';

/**
 * Create the exports of the recovered core on top of easy-email-core.
 *
 * Call this when the first consumer asks for the core, not when the script loads: the
 * recovered core read the page globals (MRM_Vars, the html dir attribute) at that moment,
 * and WordPress defines MRM_Vars after the scripts this one is a dependency of.
 *
 * @param {Object}        options
 * @param {() => unknown} options.getMjml Returns the lazily loaded MJML renderer, for MjmlToJson.
 */
export function createEasyEmailCore( options = {} ) {
	return createCompatibleCore( published, undefined, options.getMjml );
}
