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
 * The learner's Paper View (`ohmylms/paper`): the question navigator, flags for review, timer
 * warnings and the flagged-questions check before submitting. Answers, autosave, the timer and
 * submission stay with the quiz player (`ohmylms/quiz`) and its REST endpoints; this store reads
 * the player's context and never sends answers itself.
 *
 * @param {Object}                                       runtime               Interactivity runtime.
 * @param {(namespace?: string) => Object}               runtime.getContext    getContext().
 * @param {() => {ref: Element}}                         runtime.getElement    getElement().
 * @param {(callback: (event: Event) => void) => Object} runtime.withSyncEvent withSyncEvent().
 * @param {() => Object}                                 runtime.state         Returns the registered store state.
 * @param {Storage}                                      runtime.storage       Storage for flags (sessionStorage).
 * @param {(message: string) => boolean}                 runtime.confirm       Blocking confirmation.
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
	const times = new WeakMap();
	const quizRoot = () =>
		getElement().ref.closest( `[data-wp-interactive="${ QUIZ }"]` );
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
			navKey: withSyncEvent( ( event ) => {
				const nav = getElement().ref;
				const state = current();
				if ( event.key === 'Escape' && state.navOpen ) {
					event.preventDefault();
					state.navOpen = false;
					nav.querySelector( '.ohmylms-paper-nav-toggle' )?.focus();
					return;
				}
				const items = [
					...nav.querySelectorAll( '.ohmylms-paper-nav-item' ),
				];
				const next = stepIndex(
					items.length,
					items.indexOf( nav.ownerDocument.activeElement ),
					event.key
				);
				if ( next >= 0 && items.includes( event.target ) ) {
					event.preventDefault();
					items[ next ].focus();
				}
			} ),
			// Move to a question and give it focus, so reading and typing continue there.
			jump: withSyncEvent( ( event ) => {
				event.preventDefault();
				const { questionId } = getContext();
				const box = quizRoot()?.querySelector(
					`.ohmylms-quiz-box[data-question-id="${ Number(
						questionId
					) }"]`
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
				const element = getElement().ref;
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
