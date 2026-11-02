import { createPlayerState } from './state.js';
import { validate } from './validation.js';
import { loadMath, mathEnabled } from '../math-api.js';
/** @typedef {import('./state.js').QuizContext} QuizContext */

/**
 * Construct the player against a runtime; registration belongs to index.js.
 * @param {Object}                                                                              root0               Runtime.
 * @param {() => QuizContext}                                                                   root0.getContext    `@wordpress/interactivity` getContext.
 * @param {() => {ref: HTMLElement|null}}                                                       root0.getElement    `@wordpress/interactivity` getElement.
 * @param {(callback: (event: Event) => void) => (event: Event) => void}                        root0.withSyncEvent `@wordpress/interactivity` withSyncEvent.
 * @param {(callback: () => void) => () => void}                                                root0.withScope     `@wordpress/interactivity` withScope.
 * @param {(root: Element, type: string|null|undefined) => boolean}                             root0.isAnswered    SDK `isAnswered()`.
 * @param {(root: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean} root0.emit          SDK `emit()`.
 * @param {() => unknown}                                                                       root0.expire        Store `expire` action.
 * @param {(form: HTMLFormElement, operation: string) => unknown}                               root0.inlineSubmit  Store `inlineSubmit` action.
 */
export function createQuizPlayer( {
	getContext,
	getElement,
	withSyncEvent,
	withScope,
	isAnswered,
	emit,
	expire,
	inlineSubmit,
} ) {
	/** @type {WeakMap<Element, boolean>} */
	const roots = new WeakMap();
	// Which questions hold an answer, so the step button can switch between Skip and Next. Only the
	// one-question-per-page layout shows that button, so no other layout tracks it.
	/** @param {Element} box Question box. */
	const holdsAnswer = ( box ) => {
		const type = box
			.querySelector( '.is-required' )
			?.getAttribute( 'question-type' );
		return type ? isAnswered( box, type ) : false;
	};
	/** @param {QuizContext} context Player context. */
	const tracksAnswers = ( context ) =>
		context.layout === 'one_question_per_page' || context.paperPresentation;
	// Every question once, when the quiz mounts.
	/**
	 * @param {Element}     root    Quiz root.
	 * @param {QuizContext} context Player context.
	 */
	const scanAnswered = ( root, context ) => {
		if ( ! tracksAnswers( context ) ) {
			return;
		}
		/** @type {Object<string, boolean>} */
		const answered = {};
		root.querySelectorAll( '.ohmylms-quiz-box' ).forEach(
			( box, index ) => {
				answered[ index + 1 ] = holdsAnswer( box );
			}
		);
		context.answered = answered;
	};
	// Only the question that changed.
	/**
	 * @param {Element}     root    Quiz root.
	 * @param {QuizContext} context Player context.
	 * @param {Element}     input   Changed field.
	 */
	const markAnswered = ( root, context, input ) => {
		const number = Number( context.questionNumber );
		if ( ! tracksAnswers( context ) || ! number ) {
			return;
		}
		const box =
			input?.closest?.( '.ohmylms-quiz-box' ) ||
			root.querySelectorAll( '.ohmylms-quiz-box' )[ number - 1 ];
		if ( box ) {
			context.answered = {
				...context.answered,
				[ number ]: holdsAnswer( box ),
			};
		}
	};
	// Exam palette bookkeeping: which questions the learner has opened.
	/** @param {QuizContext} context Player context. */
	const visit = ( context ) => {
		context.visited = {
			...context.visited,
			[ context.page ]: true,
		};
	};
	/** @return {HTMLElement} Quiz root of the directive's element (actions run inside it). */
	const rootOf = () =>
		/** @type {HTMLElement} */ (
			/** @type {HTMLElement} */ ( getElement().ref ).closest(
				'[data-wp-interactive="ohmylms/quiz"]'
			)
		);
	return {
		state: createPlayerState( getContext ),
		actions: {
			reviewPaper: withSyncEvent( ( event ) => {
				event.preventDefault();
				const root = rootOf();
				const c = getContext();
				scanAnswered( root, c );
				const boxes = Array.from(
					root.querySelectorAll( '.ohmylms-quiz-box' )
				);
				const box = boxes.find(
					( item, index ) => ! c.answered[ index + 1 ]
				);
				const target = /** @type {HTMLElement|null} */ (
					box || root.querySelector( '.quiz-submit' )
				);
				if ( target ) {
					target.setAttribute( 'tabindex', '-1' );
					target.focus();
					target.scrollIntoView( {
						block: 'center',
						behavior: 'smooth',
					} );
				}
			} ),
			next: withSyncEvent( ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				const c = getContext();
				const root = rootOf();
				if (
					c.submitting ||
					c.page >= c.totalPages ||
					! validate( root, c, true, isAnswered )
				) {
					return;
				}
				if (
					! emit(
						root,
						'quiz-before-navigate',
						{
							quizId: c.quizId,
							page: c.page + 1,
						},
						true
					)
				) {
					return;
				}
				c.page++;
				visit( c );
				emit( root, 'quiz-navigated', {
					quizId: c.quizId,
					page: c.page,
				} );
			} ),
			// Exam palette: jump to any question without validating the one being left.
			goTo: withSyncEvent( ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				const c = getContext();
				const target = Number( c.questionNumber );
				const root = rootOf();
				if (
					c.submitting ||
					! target ||
					target === c.page ||
					target > c.totalPages ||
					! emit(
						root,
						'quiz-before-navigate',
						{
							quizId: c.quizId,
							page: target,
						},
						true
					)
				) {
					return;
				}
				c.page = target;
				visit( c );
				emit( root, 'quiz-navigated', {
					quizId: c.quizId,
					page: c.page,
				} );
			} ),
			toggleMarkCurrent: withSyncEvent( ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				const c = getContext();
				c.marked = {
					...c.marked,
					[ c.page ]: ! c.marked?.[ c.page ],
				};
			} ),
			// Empty the current question through the same input events a learner would fire,
			// so autosave and answered tracking stay correct.
			clearCurrent: withSyncEvent( ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				const c = getContext();
				const box =
					rootOf().querySelectorAll( '.ohmylms-quiz-box' )[
						c.page - 1
					];
				if ( ! box ) {
					return;
				}
				/** @type {Array<HTMLInputElement|HTMLTextAreaElement|HTMLSelectElement>} */ ( [
					...box.querySelectorAll(
						'input:not([type=hidden]),textarea,select'
					),
				] ).forEach( ( field ) => {
					if ( field.matches( '[type=checkbox],[type=radio]' ) ) {
						/** @type {HTMLInputElement} */ ( field ).checked =
							false;
					} else if ( field.matches( 'select' ) ) {
						/** @type {HTMLSelectElement} */ (
							field
						).selectedIndex = 0;
					} else {
						field.value = '';
					}
					field.dispatchEvent(
						new Event( 'input', {
							bubbles: true,
						} )
					);
					field.dispatchEvent(
						new Event( 'change', {
							bubbles: true,
						} )
					);
				} );
				/** @type {Array<Element & {value: string}>} */ ( [
					...box.querySelectorAll( 'math-field' ),
				] ).forEach( ( field ) => {
					field.value = '';
				} );
				c.answered = {
					...c.answered,
					[ c.page ]: false,
				};
			} ),
			// "Review and Submit": confirm when questions are blank, then submit the form normally.
			reviewSubmit: withSyncEvent( ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				const c = getContext();
				if ( c.submitting ) {
					return;
				}
				const answered = Object.values( c.answered || {} ).filter(
					Boolean
				).length;
				const blank = Math.max( 0, c.totalPages - answered );
				if (
					blank &&
					c.confirmSubmit &&
					// A native dialog blocks until answered, which is what a final submit needs.
					// eslint-disable-next-line no-alert
					! window.confirm(
						c.confirmSubmit.replace( '%d', String( blank ) )
					)
				) {
					return;
				}
				rootOf()
					.querySelector( '.ohmylms-quiz-form' )
					?.closest( 'form' )
					?.requestSubmit();
			} ),
			previous: withSyncEvent( ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				const c = getContext();
				const root = rootOf();
				if (
					c.submitting ||
					c.page <= 1 ||
					! emit(
						root,
						'quiz-before-navigate',
						{
							quizId: c.quizId,
							page: c.page - 1,
						},
						true
					)
				) {
					return;
				}
				c.page--;
				visit( c );
				emit( root, 'quiz-navigated', {
					quizId: c.quizId,
					page: c.page,
				} );
			} ),
			submitClick: withSyncEvent( ( event ) => {
				event.stopPropagation();
			} ),
			submit: withSyncEvent( ( event ) => {
				event.stopPropagation();
				const form = /** @type {HTMLFormElement} */ ( event.target );
				const exit = /** @type {Array<string|undefined>} */ ( [
					'ohmylms-quiz-exit-submission',
					'ohmylms-quiz-preview-exit',
				] ).includes(
					/** @type {HTMLInputElement|null} */ (
						form.querySelector( '[name=action]' )
					)?.value
				);
				const c = getContext();
				const root = rootOf();
				if (
					c.submitting ||
					( ! exit && ! validate( root, c, false, isAnswered ) ) ||
					! emit(
						root,
						'quiz-before-submit',
						{
							quizId: c.quizId,
							exit,
						},
						true
					)
				) {
					event.preventDefault();
					return;
				}
				if ( ! exit && c.paperPresentation ) {
					scanAnswered( root, c );
					const blank = Math.max(
						0,
						c.questionCount -
							Object.values( c.answered ).filter( Boolean ).length
					);
					if (
						blank &&
						// eslint-disable-next-line no-alert -- Confirm final submission with unanswered questions.
						! window.confirm(
							c.confirmSubmit.replace( '%d', String( blank ) )
						)
					) {
						event.preventDefault();
						return;
					}
				}
				c.submitting = true;
				if ( c.inlineEndpoint ) {
					event.preventDefault();
					inlineSubmit( form, exit ? 'exit' : 'submit' );
				}
			} ),
			answerChanged: withSyncEvent( ( event ) => {
				event.stopPropagation();
				const input =
					/** @type {HTMLInputElement|HTMLTextAreaElement} */ (
						event.target
					);
				const limit = Number( input.getAttribute( 'data-limit' ) );
				if ( limit > 0 && input.value.length > limit ) {
					input.value = input.value.slice( 0, limit );
				}
				const hint = /** @type {HTMLElement} */ (
					input.parentElement
				).querySelector( '.ohmylms-character-limit-hints' );
				if ( hint && limit > 0 ) {
					hint.textContent = `${ input.value.length }/${ limit }`;
				}
				if ( input.matches( '.textarea-auto-resize' ) ) {
					input.style.height = 'auto';
					input.style.height = `${ input.scrollHeight }px`;
				}
				const c = getContext();
				if ( c.questionNumber ) {
					c.errors = {
						...c.errors,
						[ c.questionNumber ]: false,
					};
				}
				markAnswered( rootOf(), c, input );
				emit( rootOf(), 'quiz-answer-changed', {
					quizId: c.quizId,
					questionNumber: c.questionNumber,
				} );
			} ),
			openExit: withSyncEvent( ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				getContext().exitOpen = true;
			} ),
			closeExit: withSyncEvent( ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				getContext().exitOpen = false;
			} ),
			/** @param {KeyboardEvent} event Keydown. */
			escape( event ) {
				if ( event.key === 'Escape' ) {
					getContext().exitOpen = false;
				}
			},
			/** @param {Event} event Click. */
			outside( event ) {
				if (
					! (
						/** @type {Element} */ ( event.target ).closest(
							'.quiz-alert-wrapper,.quiz-page-close'
						)
					)
				) {
					getContext().exitOpen = false;
				}
			},
			/** @return {Generator<unknown, void, unknown>} Runtime-driven steps. */
			*expire() {
				const c = getContext();
				if ( c.submitting ) {
					return;
				}
				c.submitting = true;
				const root = rootOf();
				const form = /** @type {HTMLFormElement} */ (
					/** @type {Element} */ (
						root.querySelector( '.ohmylms-quiz-form' )
					).closest( 'form' )
				);
				if ( c.inlineEndpoint ) {
					yield inlineSubmit( form, 'timeout' );
					return;
				}
				if ( c.preview ) {
					HTMLFormElement.prototype.submit.call( form );
					return;
				}
				const body = new URLSearchParams(
					/** @type {?} */ ( new FormData( form ) )
				);
				body.set( 'action', 'ohmylms_quiz_exit_submission' );
				body.set( 'content_id', String( c.quizId ) );
				body.set( 'attempt_id', String( c.attemptId ) );
				body.set( 'nonce', c.expiryNonce );
				try {
					const response = /** @type {Response} */ (
						yield fetch( c.ajaxUrl, {
							method: 'POST',
							credentials: 'same-origin',
							body,
						} )
					);
					const result =
						/** @type {{success?: boolean, data?: {url?: string}}} */ (
							yield response.json()
						);
					if (
						! response.ok ||
						! result.success ||
						! result.data?.url
					) {
						throw new Error( c.submissionError );
					}
					emit( root, 'quiz-expired', {
						quizId: c.quizId,
					} );
					window.location.assign( c.returnUrl || result.data.url );
				} catch {
					c.error = c.submissionError;
					c.submitting = false;
					emit( root, 'quiz-error', {
						quizId: c.quizId,
						operation: 'expiry',
					} );
				}
			},
		},
		callbacks: {
			mount() {
				const root = /** @type {HTMLElement} */ ( getElement().ref );
				if ( roots.has( root ) ) {
					return;
				}
				const c = getContext();
				const deadline = Date.now() + c.remaining * 1000;
				if (
					mathEnabled() &&
					( root.querySelector( '.ohmylms-expression-input' ) ||
						root.textContent.includes( '[[ohmylms-math:' ) ||
						/\\[([]|\$\S/.test( root.textContent ) )
				) {
					loadMath()
						.then( ( runtime ) => runtime.renderContent( root ) )
						.catch( () => {} );
				}
				/** @type {number|undefined} */
				let timer;
				const scopedExpire = withScope( () => expire() );
				if ( c.timed ) {
					timer = setInterval( () => {
						c.remaining = Math.max(
							0,
							Math.ceil( ( deadline - Date.now() ) / 1000 )
						);
						if ( ! c.remaining ) {
							clearInterval( timer );
							scopedExpire();
						}
					}, 250 );
				}
				scanAnswered( root, c );
				const restored = withScope( () => scanAnswered( root, c ) );
				root.addEventListener?.(
					'ohmylms:answer-restored',
					restored,
					true
				);
				roots.set( root, true );
				emit( root, 'quiz-mounted', {
					quizId: c.quizId,
				} );
				return () => {
					clearInterval( timer );
					root.removeEventListener?.(
						'ohmylms:answer-restored',
						restored,
						true
					);
					roots.delete( root );
					emit( root, 'quiz-unmounted', {
						quizId: c.quizId,
					} );
				};
			},
		},
	};
}
