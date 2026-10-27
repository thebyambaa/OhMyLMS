const { __, sprintf } = window.wp.i18n;

/**
 * Render server-owned Hangman or spelling state.
 *
 * @param {HTMLElement}                              stage   Player region.
 * @param {Object}                                   data    Public progress, without hidden answers.
 * @param {(command: string, value: string) => void} act     Submit an action.
 * @param {() => void}                               restart Start a new run.
 */
export function renderWord( stage, data, act, restart ) {
	const element = ( tag, text ) => {
		const node = document.createElement( tag );
		node.textContent = text;
		return node;
	};
	const hangman = data.activityMode === 'hangman';
	stage.append(
		element(
			'h3',
			hangman
				? __( 'Hangman', 'ohmylms' )
				: __( 'Spell the word', 'ohmylms' )
		),
		element(
			'p',
			sprintf(
				/* translators: 1: current round, 2: total rounds. */ __(
					'Round %1$d of %2$d',
					'ohmylms'
				),
				data.round,
				data.total
			)
		),
		element( 'p', data.clue )
	);
	const status = element(
		'p',
		sprintf(
			/* translators: 1: mistakes used, 2: maximum mistakes. */ __(
				'Mistakes: %1$d / %2$d',
				'ohmylms'
			),
			data.mistakes,
			data.limit
		)
	);
	status.setAttribute( 'role', 'status' );
	stage.append( status );
	if ( hangman ) {
		const drawing = document.createElementNS(
			'http://www.w3.org/2000/svg',
			'svg'
		);
		drawing.setAttribute( 'viewBox', '0 0 160 180' );
		drawing.setAttribute( 'class', 'ohmylms-hangman-drawing' );
		drawing.setAttribute( 'role', 'img' );
		drawing.setAttribute( 'aria-label', status.textContent );
		const paths = [
			'M10 170H100 M35 170V10H110V30',
			'M110 30a15 15 0 1 0 0 30a15 15 0 1 0 0-30',
			'M110 60V110',
			'M110 75L85 95',
			'M110 75L135 95',
			'M110 110L90 145',
			'M110 110L130 145',
		];
		paths.slice( 0, data.mistakes + 1 ).forEach( ( path ) => {
			const part = document.createElementNS(
				'http://www.w3.org/2000/svg',
				'path'
			);
			part.setAttribute( 'd', path );
			part.setAttribute( 'fill', 'none' );
			part.setAttribute( 'stroke', 'currentColor' );
			part.setAttribute( 'stroke-width', '3' );
			drawing.append( part );
		} );
		stage.append( drawing );
		const word = document.createElement( 'div' );
		word.className = 'ohmylms-word-slots';
		word.setAttribute( 'role', 'group' );
		word.setAttribute( 'aria-label', __( 'Word progress', 'ohmylms' ) );
		data.letters.forEach( ( letter ) => {
			const slot = element( 'span', letter === null ? '_' : letter );
			if ( letter === null ) {
				slot.setAttribute(
					'aria-label',
					__( 'Hidden letter', 'ohmylms' )
				);
			}
			word.append( slot );
		} );
		stage.append(
			word,
			element(
				'p',
				sprintf(
					/* translators: %s: previously guessed letters. */ __(
						'Guessed letters: %s',
						'ohmylms'
					),
					data.guessed.join( ', ' ) || '—'
				)
			)
		);
	}
	if ( data.done ) {
		stage.append(
			element(
				'p',
				data.solved
					? __( 'Correct!', 'ohmylms' )
					: __(
							'Round finished. Try again to practise this word.',
							'ohmylms'
						)
			),
			element(
				'p',
				sprintf(
					/* translators: %s: revealed answer. */ __(
						'Answer: %s',
						'ohmylms'
					),
					data.answer
				)
			)
		);
		const button = element(
			'button',
			data.status === 'complete'
				? __( 'Play again', 'ohmylms' )
				: __( 'Next word', 'ohmylms' )
		);
		button.type = 'button';
		button.addEventListener(
			'click',
			data.status === 'complete' ? restart : () => act( 'next', '' )
		);
		stage.append( button );
		return;
	}
	const form = document.createElement( 'form' );
	const label = element(
		'label',
		hangman
			? __( 'Guess a letter', 'ohmylms' )
			: __( 'Spell the word', 'ohmylms' )
	);
	const input = document.createElement( 'input' );
	input.type = 'text';
	input.required = true;
	input.maxLength = hangman ? 16 : 2000;
	input.autocomplete = 'off';
	input.spellcheck = false;
	input.setAttribute( 'autocapitalize', 'off' );
	input.setAttribute( 'autocorrect', 'off' );
	label.append( input );
	const submit = element( 'button', __( 'Check', 'ohmylms' ) );
	submit.type = 'submit';
	form.append( label, submit );
	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		form.inert = true;
		try {
			await act( 'guess', input.value );
		} finally {
			form.inert = false;
		}
	} );
	stage.append( form );
}
