/**
 * Render the ungraded, server-checked Hangman game.
 * @param {HTMLElement} root Game container.
 * @param {{max_misses?: number, play_url?: string, play_nonce?: string, play_token?: string}} config Public capability.
 * @param {{el: <K extends keyof HTMLElementTagNameMap>(tag: K, props: Record<string, unknown>, children?: HTMLElement[]) => HTMLElementTagNameMap[K], setAnswer: (root: HTMLElement, key: string, value: string) => void}} utils DOM and answer helpers.
 * @param {{__: (text: string, domain: string) => string, sprintf: (format: string, ...args: number[]) => string}} i18n Translations.
 */
export function renderHangman(
	root,
	config,
	{ el, setAnswer },
	{ __, sprintf }
) {
	/** @type {string[]} */
	let guessed = [];
	let busy = false;
	let finished = false;
	/** @type {string[]|null} */
	let restored = null;
	const keys = new Map(
		/** @type {Array<[string, HTMLButtonElement]>} */ ( [] )
	);
	root.classList.add( 'ohmylms-live-hangman' );
	const word = el( 'div', {
		class: 'ohmylms-hangman-shape',
		'aria-label': __( 'Word progress', 'ohmylms' ),
	} );
	const status = el( 'p', { role: 'status', 'aria-live': 'polite' } );
	const mistakes = el( 'meter', {
		min: 0,
		max: Number( config.max_misses ) || 6,
		value: 0,
		'aria-label': __( 'Wrong guesses', 'ohmylms' ),
	} );
	const keyboard = el( 'div', {
		class: 'ohmylms-hangman-keys',
		role: 'group',
		'aria-label': __( 'Letters', 'ohmylms' ),
	} );
	const input = el( 'input', {
		type: 'text',
		'aria-label': __( 'Type a letter', 'ohmylms' ),
		autocomplete: 'off',
		maxlength: 8,
	} );
	const submit = el( 'button', {
		type: 'button',
		text: __( 'Guess', 'ohmylms' ),
	} );
	const restart = el( 'button', {
		type: 'button',
		text: __( 'Play again', 'ohmylms' ),
	} );
	restart.hidden = true;
	const controls = el( 'div', { class: 'ohmylms-hangman-input' }, [
		input,
		submit,
	] );
	const updateKeys = () => {
		keys.forEach( ( key, letter ) => {
			key.disabled = busy || finished || guessed.includes( letter );
			key.setAttribute(
				'aria-pressed',
				String( guessed.includes( letter ) )
			);
		} );
		input.disabled = busy || finished;
		submit.disabled = busy || finished;
		restart.disabled = busy;
	};
	/** @param {string[]} letters Ordered guesses. */
	const play = async ( letters ) => {
		if ( busy ) {
			return;
		}
		busy = true;
		updateKeys();
		try {
			const response = await fetch( config.play_url || '', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.play_nonce || '',
				},
				body: JSON.stringify( {
					token: config.play_token,
					guesses: letters.join( '' ),
				} ),
			} );
			/** @type {{guessed: string[], done: boolean, solved: boolean, mistakes: number, limit: number, letters: Array<string|null>, message?: string}} */
			const data = await response.json();
			if ( ! response.ok ) {
				throw new Error(
					data.message ||
						__(
							'Could not check the letter. Try again.',
							'ohmylms'
						)
				);
			}
			guessed = data.guessed;
			finished = data.done;
			word.replaceChildren(
				...data.letters.map( ( letter ) =>
					el( 'span', {
						text: letter ?? '_',
						class: letter === null ? 'is-hidden' : 'is-revealed',
					} )
				)
			);
			mistakes.value = data.mistakes;
			mistakes.max = data.limit;
			status.textContent = data.done
				? data.solved
					? __( 'You found the word!', 'ohmylms' )
					: __( 'Round finished. Try another round!', 'ohmylms' )
				: sprintf(
						/* translators: 1: wrong guesses, 2: allowed mistakes. */ __(
							'%1$d of %2$d wrong guesses · No marks',
							'ohmylms'
						),
						data.mistakes,
						data.limit
					);
			restart.hidden = ! finished;
			setAnswer( root, 'guesses', guessed.join( '' ) );
			input.value = '';
		} catch ( error ) {
			status.textContent =
				error instanceof Error
					? error.message
					: __( 'Could not check the letter. Try again.', 'ohmylms' );
		} finally {
			busy = false;
			updateKeys();
			if ( restored !== null ) {
				const next = restored;
				restored = null;
				void play( next );
			}
		}
	};
	/** @param {string} value Letter to submit. */
	const guess = ( value ) => {
		const letter = value.trim().toLocaleLowerCase();
		if (
			! /^\p{L}\p{M}*$/u.test( letter ) ||
			guessed.includes( letter ) ||
			finished
		) {
			return;
		}
		void play( [ ...guessed, letter ] );
	};
	submit.addEventListener( 'click', () => guess( input.value ) );
	input.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			guess( input.value );
		}
	} );
	restart.addEventListener( 'click', () => void play( [] ) );
	const tabs = el( 'div', { class: 'ohmylms-hangman-tabs' } );
	const alphabets = [
		[ __( 'Latin', 'ohmylms' ), 'abcdefghijklmnopqrstuvwxyz' ],
		[ __( 'Cyrillic', 'ohmylms' ), 'абвгдеёжзийклмноөпрстуүфхцчшщъыьэюя' ],
	];
	/** @param {string} alphabet Keyboard letters. */
	const fill = ( alphabet ) => {
		keyboard.replaceChildren();
		keys.clear();
		for ( const letter of alphabet ) {
			const key = el( 'button', {
				type: 'button',
				text: letter.toLocaleUpperCase(),
			} );
			key.addEventListener( 'click', () => guess( letter ) );
			keys.set( letter, key );
			keyboard.append( key );
		}
		updateKeys();
	};
	alphabets.forEach( ( [ label, alphabet ], index ) => {
		const tab = el( 'button', {
			type: 'button',
			text: label,
			'aria-pressed': String( index === 0 ),
		} );
		tab.addEventListener( 'click', () => {
			tabs.querySelectorAll( 'button' ).forEach( ( other ) =>
				other.setAttribute( 'aria-pressed', String( other === tab ) )
			);
			fill( alphabet );
		} );
		tabs.append( tab );
	} );
	fill( alphabets[ 0 ][ 1 ] );
	root.append( word, mistakes, status, tabs, keyboard, controls, restart );
	if ( ! config.play_url || ! config.play_token ) {
		finished = true;
		updateKeys();
		status.textContent = __(
			'Save this question and open Student preview to play the live game.',
			'ohmylms'
		);
		return;
	}
	const savedGuesses = () => {
		const saved = /** @type {HTMLInputElement|null} */ (
			root.querySelector( 'input[data-answer-key="guesses"]' )
		);
		return Array.from( saved?.value || '' );
	};
	root.closest( 'form' )?.addEventListener(
		'ohmylms:answer-restored',
		( event ) => {
			const detail = /** @type {CustomEvent<{questionId?: unknown}>} */ (
				event
			).detail;
			if ( String( detail?.questionId ) !== root.dataset.questionId ) {
				return;
			}
			if ( busy ) {
				restored = savedGuesses();
			} else {
				void play( savedGuesses() );
			}
		}
	);
	void play( savedGuesses() );
}
