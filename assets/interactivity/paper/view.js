import {
	countFlags,
	format,
	itemStatus,
	parseFlags,
	serializeFlags,
	stepIndex,
	submitCheck,
	timeWarning,
	toggleFlag,
	warningText,
} from './model.mjs';

const QUIZ = 'ohmylms/quiz';

/**
 * `@wordpress/interactivity` withSyncEvent, for handlers of any event or of keyboard events.
 *
 * @typedef {((callback: (event: Event) => void) => (event: Event) => void) & ((callback: (event: KeyboardEvent) => void) => (event: KeyboardEvent) => void)} SyncEvent
 */

/**
 * @typedef {Object} PaperState Store state; the strings come from PaperPresentation.
 * @property {Object<string, true>} flags         Flags by question ID.
 * @property {boolean}              navOpen       The navigator is open.
 * @property {string}               announcement  Live region text.
 * @property {string}               storageKey    Storage key of the flags.
 * @property {string}               flaggedText   "Question %s flagged for review."
 * @property {string}               unflaggedText "Question %s no longer flagged."
 * @property {string}               confirmFlags  Confirmation with `%d` flagged questions.
 * @property {string}               minutesLeft   "%d minutes left."
 * @property {string}               oneMinuteLeft "One minute left."
 * @property {string}               answeredText  Status word.
 * @property {string}               blankText     Status word.
 * @property {string}               flagText      Status word.
 * @property {boolean}              isAnswered    The item's question has an answer.
 * @property {boolean}              isFlagged     The item's question is flagged.
 */

/**
 * @typedef {Object} PaperContext Context of the navigator (`storageKey`) or of one of its items.
 * @property {string} storageKey     Storage key of the flags (navigator).
 * @property {number} questionId     Question (item).
 * @property {number} questionNumber Question number (item).
 * @property {string} label          Question label (item).
 */

/**
 * The learner's Paper View (`ohmylms/paper`): the question navigator, flags for review, timer
 * warnings and the flagged-questions check before submitting. Answers, autosave, the timer and
 * submission stay with the quiz player (`ohmylms/quiz`) and its REST endpoints; this store reads
 * the player's context and never sends answers itself.
 *
 * @param {Object}                                                                                                          runtime               Interactivity runtime.
 * @param {((namespace: 'ohmylms/quiz') => import('../quiz-player/state.js').QuizContext|undefined) & (() => PaperContext)} runtime.getContext    getContext().
 * @param {() => {ref: HTMLElement|null}}                                                                                   runtime.getElement    getElement().
 * @param {SyncEvent}                                                                                                       runtime.withSyncEvent withSyncEvent().
 * @param {() => PaperState}                                                                                                runtime.state         Returns the registered store state.
 * @param {Storage}                                                                                                         runtime.storage       Storage for flags (sessionStorage).
 * @param {(message: string) => boolean}                                                                                    runtime.confirm       Blocking confirmation.
 * @return {Object} Store definition.
 */
export function createPaperView( {
	getContext,
	getElement,
	withSyncEvent,
	state: current,
	storage,
	confirm,
} ) {
	/** @type {WeakMap<Element, number>} */
	const times = new WeakMap();
	/** @return {HTMLElement} Element of the running directive. */
	const ref = () => /** @type {HTMLElement} */ ( getElement().ref );
	const quizRoot = () => ref().closest( `[data-wp-interactive="${ QUIZ }"]` );
	const save = () => {
		const state = current();
		try {
			storage?.setItem( state.storageKey, serializeFlags( state.flags ) );
		} catch {
			// Private browsing may refuse storage; flags then last for this page only.
		}
	};
	return {
		state: {
			flags: {},
			navOpen: false,
			announcement: '',
			storageKey: '',
			get flaggedCount() {
				return countFlags( current().flags );
			},
			get isFlagged() {
				return !! current().flags[ String( getContext().questionId ) ];
			},
			get flagPressed() {
				return current().flags[ String( getContext().questionId ) ]
					? 'true'
					: 'false';
			},
			get isAnswered() {
				const quiz = getContext( QUIZ );
				return !! quiz?.answered?.[ getContext().questionNumber ];
			},
			get itemStatus() {
				const state = current();
				return itemStatus(
					{
						answered: state.isAnswered,
						flagged: state.isFlagged,
					},
					state
				);
			},
		},
		actions: {
			toggleNav: withSyncEvent( ( event ) => {
				event.preventDefault();
				const state = current();
				state.navOpen = ! state.navOpen;
			} ),
			// Escape closes the list; arrow keys, Home and End move between its questions.
			navKey: withSyncEvent( ( /** @type {KeyboardEvent} */ event ) => {
				const nav = ref();
				const state = current();
				if ( event.key === 'Escape' && state.navOpen ) {
					event.preventDefault();
					state.navOpen = false;
					/** @type {HTMLElement|null} */ (
						nav.querySelector( '.ohmylms-paper-nav-toggle' )
					)?.focus();
					return;
				}
				const items = /** @type {HTMLElement[]} */ ( [
					...nav.querySelectorAll( '.ohmylms-paper-nav-item' ),
				] );
				const next = stepIndex(
					items.length,
					items.indexOf(
						/** @type {HTMLElement} */ (
							nav.ownerDocument.activeElement
						)
					),
					event.key
				);
				if (
					next >= 0 &&
					items.includes(
						/** @type {HTMLElement} */ ( event.target )
					)
				) {
					event.preventDefault();
					items[ next ].focus();
				}
			} ),
			// Move to a question and give it focus, so reading and typing continue there.
			jump: withSyncEvent( ( event ) => {
				event.preventDefault();
				const { questionId } = getContext();
				const box = /** @type {HTMLElement|null|undefined} */ (
					quizRoot()?.querySelector(
						`.ohmylms-quiz-box[data-question-id="${ Number(
							questionId
						) }"]`
					)
				);
				if ( ! box ) {
					return;
				}
				current().navOpen = false;
				if ( ! box.hasAttribute( 'tabindex' ) ) {
					box.setAttribute( 'tabindex', '-1' );
				}
				box.focus( { preventScroll: true } );
				const still = box.ownerDocument.defaultView?.matchMedia?.(
					'(prefers-reduced-motion: reduce)'
				)?.matches;
				box.scrollIntoView( {
					block: 'start',
					behavior: still ? 'auto' : 'smooth',
				} );
			} ),
			toggleFlag: withSyncEvent( ( event ) => {
				event.preventDefault();
				const { questionId, label } = getContext();
				const state = current();
				state.flags = toggleFlag( state.flags, questionId );
				state.announcement = format(
					state.flags[ String( questionId ) ]
						? state.flaggedText
						: state.unflaggedText,
					label
				);
				save();
			} ),
		},
		callbacks: {
			init() {
				const { storageKey } = getContext();
				const state = current();
				const quiz = getContext( QUIZ );
				const root = quizRoot();
				state.storageKey = storageKey;
				try {
					state.flags = parseFlags( storage?.getItem( storageKey ) );
				} catch {
					state.flags = {};
				}
				if ( ! root || ! quiz ) {
					return undefined;
				}
				// Runs inside the player's submit handler; cancelling it keeps the paper open.
				/** @param {Event & Partial<CustomEvent>} event `ohmylms:quiz-before-submit`. */
				const beforeSubmit = ( event ) => {
					if ( event.detail?.exit ) {
						return;
					}
					const flagged = countFlags( state.flags );
					const check = submitCheck( {
						questions: Number( quiz.questionCount ) || 0,
						answered: Object.values( quiz.answered || {} ).filter(
							Boolean
						).length,
						flagged,
					} );
					if (
						check === 'flags' &&
						! confirm( format( state.confirmFlags, flagged ) )
					) {
						event.preventDefault();
					}
				};
				root.addEventListener(
					'ohmylms:quiz-before-submit',
					beforeSubmit
				);
				return () =>
					root.removeEventListener(
						'ohmylms:quiz-before-submit',
						beforeSubmit
					);
			},
			// Announce five minutes and one minute left; the player shows the clock itself.
			watchTime() {
				const quiz = getContext( QUIZ );
				if ( ! quiz?.timed ) {
					return;
				}
				const element = ref();
				const remaining = Number( quiz.remaining );
				const crossed = timeWarning(
					times.has( element ) ? times.get( element ) : null,
					remaining
				);
				times.set( element, remaining );
				if ( crossed ) {
					const state = current();
					state.announcement = warningText( crossed, state );
				}
			},
		},
	};
}
