/**
 * @typedef {Object} InlineRuntime
 * @property {(url: string, init: Parameters<typeof fetch>[1]) => Promise<Response>} request `fetch`.
 * @property {(form: HTMLFormElement) => URLSearchParams}                            encode  Form body.
 * @property {() => Promise<void>}                                                   refresh Refresh the router regions.
 */

/**
 * Save through the shared quiz engine, then refresh native router regions.
 * Captured context keeps this asynchronous transport independent of directive scope.
 *
 * @param {import('./state.js').QuizContext} context   Player context.
 * @param {HTMLFormElement}                  form      Submitted form.
 * @param {string}                           operation Start, submit, exit or timeout.
 * @param {InlineRuntime}                    runtime   Request, form encoder and refresh implementations.
 */
export async function sendInline( context, form, operation, runtime ) {
	context.error = '';
	try {
		if ( ! context.saved ) {
			const body = runtime.encode( form );
			body.set( 'operation', operation );
			// Only players with the inline transport (both fields) send inline.
			const response = await runtime.request(
				/** @type {string} */ ( context.inlineEndpoint ),
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce': /** @type {string} */ (
							context.restNonce
						),
					},
					body,
				}
			);
			const result = await response.json();
			if ( ! response.ok || ! result.saved ) {
				throw new Error( result.message || context.submissionError );
			}
			// A refresh failure can be retried without starting or grading twice.
			context.saved = true;
		}
		await runtime.refresh();
		context.saved = false;
		context.submitting = false;
	} catch ( error ) {
		context.error =
			/** @type {Error} */ ( error ).message || context.submissionError;
		context.submitting = false;
	}
}
