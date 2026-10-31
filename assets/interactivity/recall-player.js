const { __, sprintf } = window.wp.i18n;

/**
 * @typedef {Object} RecallChoice
 * @property {string}  id         Item token.
 * @property {string}  text       Item label.
 * @property {boolean} selected   Picked by the learner.
 * @property {boolean} [wasShown] Complete phase: shown during study.
 */

/**
 * @typedef {Object} RecallResult
 * @property {number} remembered Targets picked.
 * @property {number} wrong      Wrong picks.
 * @property {number} maximum    Targets.
 * @property {number} fraction   Practice accuracy, 0–1.
 */

/**
 * Server projection (OhMyLMS\Practice\RecallActivity). `studyItems` and `remainingSeconds` are
 * sent in the `study` phase, `choices` in `recall` and `complete`, `recallResult` in `complete`.
 *
 * @typedef {Object} RecallState
 * @property {string}                session            Session UUID.
 * @property {string}                status             `complete` when the session is over.
 * @property {string}                phase              `ready`, `study`, `recall` or `complete`.
 * @property {number}                rememberCount      Items to remember.
 * @property {number}                studySeconds       Study time.
 * @property {Array<{text: string}>} [studyItems]       Items to memorize.
 * @property {number}                [remainingSeconds] Study time left.
 * @property {RecallChoice[]}        [choices]          Items to choose from.
 * @property {RecallResult}          [recallResult]     Outcome.
 */

/**
 * Accessible observation and recall, using only server-projected phase data.
 *
 * @param {HTMLElement}                                        stage   Player region.
 * @param {RecallState}                                        data    Safe frozen progress.
 * @param {(command: string, value?: string) => Promise<void>} act     Save an action.
 * @param {() => Promise<void>}                                restart Start a fresh session.
 * @return {() => void} Stop countdown and visibility listeners.
 */
export function renderRecall( stage, data, act, restart ) {
	/**
	 * @param {string} tag  Tag name.
	 * @param {string} text Text content.
	 * @return {HTMLElement} Element.
	 */
	const make = ( tag, text ) => {
		const node = document.createElement( tag );
		node.textContent = text;
		return node;
	};
	let pending = false;
	/**
	 * @param {string} command Command.
	 * @param {string} [value] Item token.
	 */
	const run = async ( command, value = '' ) => {
		if ( pending ) {
			return;
		}
		pending = true;
		/** @type {Array<[HTMLButtonElement, boolean]>} */
		const buttons = Array.from(
			stage.querySelectorAll( 'button' ),
			( node ) => [ node, node.disabled ]
		);
		buttons.forEach( ( [ node ] ) => {
			node.disabled = true;
		} );
		try {
			await act( command, value );
		} finally {
			pending = false;
			buttons.forEach( ( [ node, disabled ] ) => {
				if ( node.isConnected ) {
					node.disabled = disabled;
				}
			} );
		}
	};
	/**
	 * @param {string}        label  Label.
	 * @param {() => unknown} action Click handler.
	 * @return {HTMLButtonElement} Button.
	 */
	const button = ( label, action ) => {
		const node = /** @type {HTMLButtonElement} */ (
			make( 'button', label )
		);
		node.type = 'button';
		node.addEventListener( 'click', action );
		return node;
	};
	stage.append( make( 'h3', __( 'Watch and memorize', 'ohmylms' ) ) );
	const status = make( 'p', '' );
	status.setAttribute( 'role', 'status' );
	stage.append( status );
	/** @type {ReturnType<typeof setInterval>|undefined} */
	let timer;
	/** @type {ReturnType<typeof setTimeout>|undefined} */
	let transitionTimer;
	let onVisibility = () => {};
	if ( data.phase === 'ready' ) {
		status.textContent = sprintf(
			/* translators: 1: target count, 2: study duration. */ __(
				'Remember %1$d items. You will have %2$d seconds to study, then choose what you saw.',
				'ohmylms'
			),
			data.rememberCount,
			data.studySeconds
		);
		const begin = button( __( 'Begin study', 'ohmylms' ), () =>
			run( 'begin' )
		);
		stage.append( begin );
		begin.focus();
	} else if ( data.phase === 'study' ) {
		const list = make( 'ul', '' );
		list.className = 'ohmylms-recall-study';
		/** @type {Array<{text: string}>} */ ( data.studyItems ).forEach(
			( item ) => list.append( make( 'li', item.text ) )
		);
		stage.append( list );
		const recall = () => {
			list.replaceChildren();
			return run( 'recall' );
		};
		const ready = button( __( 'Ready to recall', 'ohmylms' ), recall );
		stage.append( ready );
		const until =
			Date.now() + /** @type {number} */ ( data.remainingSeconds ) * 1000;
		let expired = false;
		let previousSecond = -1;
		onVisibility = () => {
			const seconds = Math.max(
				0,
				Math.ceil( ( until - Date.now() ) / 1000 )
			);
			if ( seconds !== previousSecond ) {
				previousSecond = seconds;
				status.textContent = sprintf(
					/* translators: %d: seconds remaining. */ __(
						'Study time remaining: %d seconds',
						'ohmylms'
					),
					seconds
				);
			}
			if ( seconds === 0 && ! expired ) {
				expired = true;
				clearInterval( timer );
				list.replaceChildren();
				transitionTimer = setTimeout( () => {
					void run( 'recall' );
				}, 0 );
			}
		};
		timer = setInterval( onVisibility, 250 );
		onVisibility();
		ready.focus();
		document.addEventListener( 'visibilitychange', onVisibility );
	} else {
		const complete = data.phase === 'complete';
		const choices = /** @type {RecallChoice[]} */ ( data.choices );
		const selected = choices.filter( ( choice ) => choice.selected ).length;
		status.textContent = complete
			? __(
					'Memory activity complete. No academic grade was recorded.',
					'ohmylms'
				)
			: sprintf(
					/* translators: 1: selected count, 2: allowed count. */ __(
						'Choose the items you saw: %1$d / %2$d selected.',
						'ohmylms'
					),
					selected,
					data.rememberCount
				);
		const grid = make( 'div', '' );
		grid.className = 'ohmylms-study-cards ohmylms-recall-choices';
		choices.forEach( ( choice ) => {
			const article = make( 'article', '' );
			const control = button( choice.text, () =>
				run( 'toggle', choice.id )
			);
			control.dataset.cardId = choice.id;
			control.setAttribute(
				'aria-pressed',
				choice.selected ? 'true' : 'false'
			);
			control.disabled =
				complete ||
				( selected >= data.rememberCount && ! choice.selected );
			article.append( control );
			if ( complete ) {
				article.append(
					make(
						'p',
						choice.wasShown
							? __( 'Shown during study', 'ohmylms' )
							: __( 'Not shown', 'ohmylms' )
					)
				);
			}
			grid.append( article );
		} );
		stage.append( grid );
		if ( complete ) {
			const result = /** @type {RecallResult} */ ( data.recallResult );
			stage.append(
				make(
					'p',
					sprintf(
						/* translators: 1: remembered count, 2: target count, 3: false choices. */ __(
							'Remembered: %1$d / %2$d. Incorrect selections: %3$d.',
							'ohmylms'
						),
						result.remembered,
						result.maximum,
						result.wrong
					)
				)
			);
			stage.append(
				make(
					'p',
					sprintf(
						/* translators: %d: practice recall accuracy percentage. */ __(
							'Practice accuracy: %d%%',
							'ohmylms'
						),
						Math.round( result.fraction * 100 )
					)
				)
			);
			stage.append( button( __( 'Play again', 'ohmylms' ), restart ) );
		} else {
			stage.append(
				button( __( 'Check recall', 'ohmylms' ), () => run( 'finish' ) )
			);
		}
		/** @type {HTMLButtonElement|null} */ (
			stage.querySelector( 'button:not(:disabled)' )
		)?.focus();
	}
	return () => {
		clearInterval( timer );
		clearTimeout( transitionTimer );
		document.removeEventListener( 'visibilitychange', onVisibility );
	};
}
