import { questionPrompt } from '../question-editor/questionPrompt.mjs';

/**
 * Record the prompt actually accepted by the server for each question.
 * @param {Array} questions Question records.
 * @return {Map} Prompts indexed by question ID.
 */
export const promptSnapshots = ( questions ) =>
	new Map(
		questions.map( ( question ) => [
			question.id,
			questionPrompt( question ),
		] )
	);

/**
 * An unrelated question's save cannot change an already saved prompt's status.
 * @param {Object}      question  Question being edited.
 * @param {Map}         saved     Last accepted prompts.
 * @param {Map}         submitted Prompts in the current request.
 * @param {boolean}     saving    Whether a request is in flight.
 * @param {string|null} error     Latest save error.
 * @return {string} Saved, saving, pending or error.
 */
export function quickEditStatus( question, saved, submitted, saving, error ) {
	const prompt = questionPrompt( question );
	if ( saved.has( question.id ) && saved.get( question.id ) === prompt ) {
		return 'saved';
	}
	if ( saving && submitted.get( question.id ) === prompt ) {
		return 'saving';
	}
	return error ? 'error' : 'pending';
}
