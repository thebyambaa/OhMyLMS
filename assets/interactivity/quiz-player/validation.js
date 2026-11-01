/** @param {Element} root Quiz root. */
const questionBoxes = ( root ) => [
	...root.querySelectorAll( '.ohmylms-quiz-box' ),
];
/**
 * @param {Element}                                                 root        Quiz root.
 * @param {import('./state.js').QuizContext}                        context     Player context.
 * @param {boolean}                                                 currentOnly Only the questions of the current page.
 * @param {(root: Element, type: string|null|undefined) => boolean} isAnswered  SDK `isAnswered()`.
 * @return {boolean} Every required question is answered.
 */
export function validate( root, context, currentOnly = false, isAnswered ) {
	/** @type {Object<string, boolean>} */
	const errors = {};
	questionBoxes( root ).forEach( ( box, index ) => {
		const number = index + 1;
		const page =
			context.layout === 'number_of_questions_per_page'
				? Math.ceil( number / context.perPage )
				: number;
		if ( currentOnly && page !== context.page ) {
			return;
		}
		const required = /** @type {HTMLInputElement|null} */ (
			box.querySelector( '.is-required' )
		);
		if (
			required &&
			! [ '', '0', 'false', 'no' ].includes( required.value ) &&
			! isAnswered( box, required.getAttribute( 'question-type' ) )
		) {
			errors[ number ] = true;
		}
	} );
	context.errors = errors;
	const first = Number( Object.keys( errors )[ 0 ] );
	if ( first ) {
		if ( ! currentOnly ) {
			context.page =
				context.layout === 'number_of_questions_per_page'
					? Math.ceil( first / context.perPage )
					: first;
		}
		const box = questionBoxes( root )[ first - 1 ];
		/** @type {HTMLElement|null} */ (
			box.querySelector(
				'math-field:not([read-only]),input:not([type=hidden]),textarea,select'
			)
		)?.focus();
	}
	return ! first;
}
