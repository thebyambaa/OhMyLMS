/**
 * Save through the shared quiz engine, then refresh native router regions.
 * Captured context keeps this asynchronous transport independent of directive scope.
 *
 * @param {Object} context   Player context.
 * @param {Object} form      Submitted form.
 * @param {string} operation Start, submit, exit or timeout.
 * @param {Object} runtime   Request, form encoder and refresh implementations.
 */
export async function sendInline( context, form, operation, runtime ) {
	context.error = '';
	try {
		if ( ! context.saved ) {
			const body = runtime.encode( form );
			body.set( 'operation', operation );
			const response = await runtime.request( context.inlineEndpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': context.restNonce },
				body,
			} );
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
		context.error = error.message || context.submissionError;
		context.submitting = false;
	}
}
