const { __, sprintf } = window.wp.i18n;

/**
 * Render study state received from the server; hidden backs are never preloaded.
 * @param {HTMLElement}                              stage   Player region.
 * @param {Object}                                   data    Frozen study session state.
 * @param {(card: string, revision: number) => void} reveal  Request a card reveal.
 * @param {() => void}                               restart Start a fresh run.
 */
export function renderStudy( stage, data, reveal, restart ) {
	const element = ( tag, text ) => {
		const node = document.createElement( tag );
		node.textContent = text;
		return node;
	};
	const title = element(
		'h3',
		data.activityMode === 'flashcards'
			? __( 'Flashcards', 'ohmylms' )
			: __( 'Matching pairs', 'ohmylms' )
	);
	const status = element(
		'p',
		data.status === 'complete'
			? __(
					'Study activity complete. No academic score was recorded.',
					'ohmylms'
				)
			: __(
					'Reveal cards to study. Progress is saved automatically.',
					'ohmylms'
				)
	);
	status.setAttribute( 'role', 'status' );
	stage.append( title, status );
	const grid = document.createElement( 'div' );
	grid.className = 'ohmylms-study-cards';
	data.cards.forEach( ( card, index ) => {
		const article = document.createElement( 'article' );
		const button = element(
			'button',
			card.text === null
				? sprintf(
						/* translators: %d: card number. */ __(
							'Card %d',
							'ohmylms'
						),
						index + 1
					)
				: card.text
		);
		button.type = 'button';
		button.dataset.cardId = card.id;
		button.disabled =
			card.matched || card.revealed || card.answer !== undefined;
		button.setAttribute(
			'aria-pressed',
			card.revealed || card.answer !== undefined ? 'true' : 'false'
		);
		button.addEventListener( 'click', () =>
			reveal( card.id, data.revision )
		);
		article.append( button );
		if ( card.answer !== undefined ) {
			article.append( element( 'p', card.answer ) );
		}
		if ( card.matched ) {
			article.append( element( 'span', __( 'Matched', 'ohmylms' ) ) );
		}
		grid.append( article );
	} );
	stage.append( grid );
	if ( data.activityMode === 'memory-pairs' ) {
		stage.append(
			element(
				'p',
				sprintf(
					/* translators: %d: completed turns. */ __(
						'Turns: %d',
						'ohmylms'
					),
					data.turns
				)
			)
		);
	}
	if ( data.status === 'complete' ) {
		const again = element( 'button', __( 'Play again', 'ohmylms' ) );
		again.type = 'button';
		again.addEventListener( 'click', restart );
		stage.append( again );
	}
}
