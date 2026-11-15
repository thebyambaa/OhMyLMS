/** Question placements supported by the native quiz document. */
export const QUIZ_QUESTION_BLOCKS = [
	'ohmylms/shared-multiple-choice',
	'ohmylms/quiz-question-reference',
];

/**
 * Collect question placements in document order without flattening saved layouts.
 *
 * @param {import('@wordpress/blocks').BlockInstance[]} blocks Document blocks.
 * @return {import('@wordpress/blocks').BlockInstance[]} Questions.
 */
export function quizQuestions( blocks ) {
	return blocks.flatMap( ( block ) => [
		...( QUIZ_QUESTION_BLOCKS.includes( block.name ) ? [ block ] : [] ),
		...quizQuestions( block.innerBlocks || [] ),
	] );
}

/**
 * Hide other canvas blocks without unmounting their draft editors or save guards.
 * Ancestors and descendants of a focused question remain visible. The stylesheet
 * belongs to the editor document only; no saved block attributes are changed.
 *
 * @param {Document} doc      Canvas document (iframe or main document).
 * @param {string}   mode     Question, introduction, or document view.
 * @param {string}   activeId Focused block ID.
 * @return {() => void} Cleanup.
 */
export function focusQuizCanvas( doc, mode, activeId ) {
	const view = doc.defaultView;
	if ( ! view ) {
		return () => {};
	}
	const style = doc.createElement( 'style' );
	style.dataset.ohmylmsQuizFocus = 'true';
	doc.head.appendChild( style );
	const update = () => {
		const blocks = Array.from( doc.querySelectorAll( '[data-block]' ) );
		const active = blocks.find(
			( block ) => block.getAttribute( 'data-block' ) === activeId
		);
		const hidden = blocks.filter( ( block ) => {
			if ( 'introduction' === mode ) {
				return QUIZ_QUESTION_BLOCKS.includes(
					block.getAttribute( 'data-type' ) || ''
				);
			}
			return (
				'question' === mode &&
				active &&
				block !== active &&
				! block.contains( active ) &&
				! active.contains( block )
			);
		} );
		// Client IDs come from Gutenberg, but escape them before using CSS selectors.
		const rules = hidden
			.map(
				( block ) =>
					`[data-block="${ view.CSS.escape( block.getAttribute( 'data-block' ) || '' ) }"] { display: none !important; }`
			)
			.join( '\n' );
		const title =
			'question' === mode && active
				? '.editor-post-title, .editor-post-title__block, .block-list-appender { display: none !important; }'
				: '';
		const next = rules + title;
		if ( style.textContent !== next ) {
			style.textContent = next;
		}
	};
	const observer = new view.MutationObserver( update );
	observer.observe( doc.body, { childList: true, subtree: true } );
	update();
	return () => {
		observer.disconnect();
		style.remove();
	};
}
