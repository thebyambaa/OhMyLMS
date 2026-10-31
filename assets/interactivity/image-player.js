const { __, sprintf } = window.wp.i18n;

/**
 * @typedef {Object} ImageChoice
 * @property {string}  id         Answer token.
 * @property {string}  text       Answer label.
 * @property {string}  image      Answer image URL, '' for none.
 * @property {boolean} [correct]  Review phase: the right answer.
 * @property {boolean} [selected] Review phase: the learner's answer.
 */

/**
 * Server projection (OhMyLMS\Practice\ImageActivity). `choices` is sent in the `answer` and
 * `review` phases; `correct`, `roundPoints` and `skipped` in `review`.
 *
 * @typedef {Object} ImageQuizState
 * @property {string}        session       Session UUID.
 * @property {string}        status        `complete` when the game is over.
 * @property {string}        phase         `ready`, `reveal`, `answer` or `review`.
 * @property {number}        round         Current image.
 * @property {number}        total         Images.
 * @property {string}        prompt        Prompt.
 * @property {string}        image         Image URL.
 * @property {number}        revealed      Tiles revealed so far.
 * @property {number}        revealSeconds Seconds for a full reveal.
 * @property {number}        elapsed       Seconds since the reveal started.
 * @property {number}        points        Practice points.
 * @property {ImageChoice[]} [choices]     Answers.
 * @property {boolean}       [correct]     The answer was right.
 * @property {number}        [roundPoints] Points for this image.
 * @property {boolean}       [skipped]     The image was skipped.
 */

/**
 * Timed, keyboard-accessible image reveal with server-owned buzzer progress.
 *
 * @param {HTMLElement}                                        stage   Player region.
 * @param {ImageQuizState}                                     data    Safe progress.
 * @param {(command: string, value?: string) => Promise<void>} act     Persist command and optional answer token.
 * @param {() => Promise<void>}                                restart Start a fresh game.
 * @return {() => void} Stop the local reveal clock.
 */
export function renderImageQuiz( stage, data, act, restart ) {
	/**
	 * @param {string} tag    Tag name.
	 * @param {string} [text] Text content.
	 * @return {HTMLElement} Element.
	 */
	const make = ( tag, text = '' ) => {
		const node = document.createElement( tag );
		node.textContent = text;
		return node;
	};
	let pending = false;
	/**
	 * @param {string} command Command.
	 * @param {string} [value] Answer token.
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
	 * @param {string}        text   Label.
	 * @param {() => unknown} action Click handler.
	 * @return {HTMLButtonElement} Button.
	 */
	const button = ( text, action ) => {
		const node = /** @type {HTMLButtonElement} */ (
			make( 'button', text )
		);
		node.type = 'button';
		node.addEventListener( 'click', action );
		return node;
	};
	stage.append( make( 'h3', __( 'Image quiz', 'ohmylms' ) ) );
	stage.append(
		make(
			'p',
			sprintf(
				/* translators: 1: round, 2: total rounds, 3: practice points. */
				__( 'Image %1$d / %2$d · Practice points: %3$d', 'ohmylms' ),
				data.round,
				data.total,
				data.points
			)
		)
	);
	stage.append( make( 'h4', data.prompt ) );
	const picture = make( 'div' );
	picture.className = 'ohmylms-image-reveal';
	const image = /** @type {HTMLImageElement} */ ( make( 'img' ) );
	// A descriptive answer here would defeat the visual guessing exercise.
	image.alt = __( 'Image clue', 'ohmylms' );
	const mask = make( 'div' );
	mask.className = 'ohmylms-image-mask';
	mask.setAttribute( 'aria-hidden', 'true' );
	const tiles = Array.from( { length: 36 }, () => make( 'span' ) );
	mask.append( ...tiles );
	picture.append( image, mask );
	stage.append( picture );
	const status = make( 'p' );
	status.setAttribute( 'role', 'status' );
	stage.append( status );
	const controls = make( 'div' );
	controls.className = 'ohmylms-image-controls';
	stage.append( controls );
	/** @type {HTMLButtonElement|undefined} */
	let primary;
	if ( data.phase === 'ready' ) {
		status.textContent = __(
			'Start the reveal, then buzz when you recognize the image. Earlier correct guesses earn more practice points.',
			'ohmylms'
		);
		primary = button( __( 'Reveal image', 'ohmylms' ), () =>
			run( 'begin' )
		);
		primary.disabled = true;
		controls.append( primary );
	} else if ( data.phase === 'reveal' ) {
		primary = button( __( 'Buzz! I know it', 'ohmylms' ), () =>
			run( 'buzz' )
		);
		controls.append( primary );
	} else {
		const review = data.phase === 'review';
		status.textContent = __(
			'Reveal paused. Choose your answer.',
			'ohmylms'
		);
		if ( review ) {
			status.textContent = data.skipped
				? __( 'Image skipped. 0 points.', 'ohmylms' )
				: sprintf(
						/* translators: 1: correctness feedback, 2: practice points. */
						__( '%1$s · %2$d points', 'ohmylms' ),
						data.correct
							? __( 'Correct', 'ohmylms' )
							: __( 'Incorrect', 'ohmylms' ),
						/** @type {number} */ ( data.roundPoints )
					);
		}
		const choices = make( 'div' );
		choices.className = 'ohmylms-study-cards ohmylms-image-choices';
		/** @type {ImageChoice[]} */ ( data.choices ).forEach(
			( choice, index ) => {
				const option = button(
					`${ String.fromCharCode( 65 + index ) }. ${ choice.text }`,
					() => run( 'answer', choice.id )
				);
				option.dataset.cardId = choice.id;
				if ( choice.image ) {
					const thumbnail = /** @type {HTMLImageElement} */ (
						make( 'img' )
					);
					thumbnail.alt =
						choice.text ||
						sprintf(
							/* translators: %d: answer number. */ __(
								'Answer image %d',
								'ohmylms'
							),
							index + 1
						);
					thumbnail.src = choice.image;
					option.append( thumbnail );
				}
				option.disabled = review;
				if ( review ) {
					option.append(
						make(
							'span',
							choice.correct
								? ' — ' + __( 'Correct answer', 'ohmylms' )
								: ''
						),
						make(
							'span',
							choice.selected
								? ' — ' + __( 'Your answer', 'ohmylms' )
								: ''
						)
					);
				}
				choices.append( option );
			}
		);
		stage.append( choices );
		if ( review ) {
			if ( data.status === 'complete' ) {
				stage.append(
					make(
						'p',
						__(
							'Image quiz complete. No academic grade was recorded.',
							'ohmylms'
						)
					)
				);
				primary = button( __( 'Play again', 'ohmylms' ), restart );
			} else {
				primary = button( __( 'Next image', 'ohmylms' ), () =>
					run( 'next' )
				);
			}
			stage.append( primary );
		}
	}
	if ( data.phase !== 'review' ) {
		controls.append(
			button( __( 'Skip image', 'ohmylms' ), () => run( 'skip' ) )
		);
	}
	/** @param {number} count Tiles to hide. */
	const paint = ( count ) => {
		// Fixed interleaved order reveals pieces across the whole image.
		tiles.forEach( ( tile, index ) => {
			tile.style.visibility =
				( index * 13 ) % 36 < count ? 'hidden' : 'visible';
		} );
	};
	const mounted = Date.now();
	const tick = () => {
		let count = data.revealed;
		if ( data.phase === 'review' ) {
			count = 36;
		} else if ( data.phase === 'reveal' ) {
			count = Math.min(
				36,
				Math.floor(
					( ( data.elapsed + ( Date.now() - mounted ) / 1000 ) *
						36 ) /
						data.revealSeconds
				)
			);
		}
		paint( count );
		if ( data.phase === 'reveal' ) {
			status.textContent =
				count === 36
					? __(
							'Image fully revealed. Buzz to choose your answer.',
							'ohmylms'
						)
					: __(
							'Watch the image. Buzz when you recognize it.',
							'ohmylms'
						);
		}
	};
	tick();
	const timer =
		data.phase === 'reveal' ? setInterval( tick, 100 ) : undefined;
	image.addEventListener( 'load', () => {
		if ( data.phase === 'ready' && ! pending && primary ) {
			primary.disabled = false;
		}
	} );
	image.addEventListener( 'error', () => {
		status.textContent = __(
			'The image could not load. Reload the page to retry, or skip this image.',
			'ohmylms'
		);
		clearInterval( timer );
		if ( primary && data.phase !== 'review' ) {
			primary.disabled = true;
		}
	} );
	image.src = data.image;
	/** @type {HTMLButtonElement|null} */ (
		primary?.disabled
			? controls.querySelector( 'button:not(:disabled)' )
			: primary || stage.querySelector( 'button:not(:disabled)' )
	)?.focus();
	return () => clearInterval( timer );
}
