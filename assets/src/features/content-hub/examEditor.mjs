/**
 * Where an exam written in OhMyLMS is edited: its backing quiz in the Gutenberg editor, returning to
 * the Exams tab afterwards.
 *
 * @param {Object} exam Exam summary with the quiz's editor URL.
 * @return {string} Editor URL, or an empty string for exams that have none.
 */
export function examEditorHref( exam ) {
	if ( ! exam?.editor_url ) {
		return '';
	}
	const url = new URL( exam.editor_url, window.location.href );
	url.searchParams.set( 'ohmylms_return', '/content-hub/exams' );
	return url.href;
}
