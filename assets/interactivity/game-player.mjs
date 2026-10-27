const { __, sprintf } = window.wp.i18n;
const titles = {
	'spin-wheel': __( 'Spin the wheel', 'ohmylms' ),
	'open-box': __( 'Open the box', 'ohmylms' ),
	'speaking-cards': __( 'Speaking cards', 'ohmylms' ),
	'flip-tiles': __( 'Flip tiles', 'ohmylms' ),
	'find-match': __( 'Find the match', 'ohmylms' ),
	'pair-or-no-pair': __( 'Pair or No Pair', 'ohmylms' ),
	'balloon-pop': __( 'Balloon pop', 'ohmylms' ),
	gameshow: __( 'Gameshow quiz', 'ohmylms' ),
	'timed-true-false': __( 'Timed True or false', 'ohmylms' ),
	'win-or-lose': __( 'Win or lose quiz', 'ohmylms' ),
	'maze-chase': __( 'Maze chase', 'ohmylms' ),
	'flying-fruit': __( 'Flying fruit', 'ohmylms' ),
	'whack-a-mole': __( 'Whack-a-mole', 'ohmylms' ),
	airplane: __( 'Airplane', 'ohmylms' ),
	'speed-sorting': __( 'Speed sorting', 'ohmylms' ),
	'word-magnets': __( 'Word magnets', 'ohmylms' ),
};

/**
 * Reusable native controls; server commands own scoring and saved progress.
 *
 * @param {HTMLElement} stage Player region.
 * @param {Object} data Safe progress.
 * @param {(command: string, value?: string) => Promise<void>} act Save action.
 * @param {() => Promise<void>} restart Start a new run.
 * @return {() => void} Stop animation and timers.
 */
export function renderGame( stage, data, act, restart ) {
	const doc = stage.ownerDocument;
	const make = ( tag, text = '' ) => {
		const node = doc.createElement( tag );
		node.textContent = text;
		return node;
	};
	let pending = false;
	let disposed = false;
	const cleanups = [];
	const run = async ( command, value = '' ) => {
		if ( pending || disposed ) return;
		pending = true;
		const controls = Array.from(
			stage.querySelectorAll( 'button, select' ),
			( node ) => [ node, node.disabled ]
		);
		controls.forEach( ( [ node ] ) => {
			node.disabled = true;
		} );
		try {
			await act( command, value );
		} finally {
			pending = false;
			controls.forEach( ( [ node, disabled ] ) => {
				if ( node.isConnected ) node.disabled = disabled;
			} );
		}
	};
	const button = ( text, action ) => {
		const node = make( 'button', text );
		node.type = 'button';
		node.addEventListener( 'click', action );
		return node;
	};
	const showImage = ( parent, url, alt = '' ) => {
		if ( url ) {
			const image = make( 'img' );
			image.src = url;
			image.alt = alt;
			parent.append( image );
		}
	};
	const mode = data.activityMode;
	const complete = data.status === 'complete';
	const title = make( 'h3', titles[ mode ] );
	title.tabIndex = -1;
	stage.append( title );
	const feedback = make(
		'p',
		__(
			'Practice activity — no academic grade or skill update.',
			'ohmylms'
		)
	);
	stage.append( feedback );
	if ( data.gameFamily === 'deck' ) {
		const selected = data.cards.find(
			( card ) => card.id === data.selected
		);
		if ( mode === 'spin-wheel' ) {
			const wheel = make( 'div' );
			wheel.className = 'ohmylms-game-wheel';
			wheel.setAttribute( 'aria-hidden', 'true' );
			const colors = [
				'#7c3aed',
				'#0284c7',
				'#be185d',
				'#15803d',
				'#c2410c',
				'#a16207',
			];
			wheel.style.background = `conic-gradient(${ data.cards.map( ( card, index ) => `${ colors[ index % colors.length ] } ${ ( index * 360 ) / data.total }deg ${ ( ( index + 1 ) * 360 ) / data.total }deg` ).join( ',' ) })`;
			data.cards.forEach( ( card, index ) => {
				const number = make( 'span', String( index + 1 ) );
				number.style.transform = `rotate(${ ( ( index + 0.5 ) * 360 ) / data.total }deg) translateY(-105px) rotate(-${ ( ( index + 0.5 ) * 360 ) / data.total }deg)`;
				wheel.append( number );
			} );
			const frame = make( 'div' );
			frame.className = 'ohmylms-wheel-frame';
			frame.append( wheel );
			stage.append( frame );
			if ( selected ) {
				const animation = requestAnimationFrame( () => {
					wheel.style.transform = `rotate(${ 1080 - ( ( data.cards.indexOf( selected ) + 0.5 ) * 360 ) / data.total }deg)`;
				} );
				cleanups.push( () => cancelAnimationFrame( animation ) );
			}
		}
		const grid = make( 'div' );
		grid.className = 'ohmylms-study-cards ohmylms-game-deck';
		data.cards.forEach( ( card ) => {
			let text = `${ card.number }`;
			if ( mode === 'flip-tiles' )
				text = card.opened ? card.back : card.text;
			else if ( card.text ) text = `${ card.number }. ${ card.text }`;
			const control = button( text, () =>
				run( mode === 'flip-tiles' ? 'flip' : 'pick', card.id )
			);
			control.dataset.cardId = card.id;
			control.disabled = complete;
			control.setAttribute( 'aria-pressed', String( card.opened ) );
			grid.append( control );
		} );
		stage.append( grid );
		if ( selected ) {
			const reader = make( 'article' );
			reader.className = 'ohmylms-game-reader';
			reader.append(
				make( 'h4', selected.text ),
				make( 'p', selected.back )
			);
			showImage( reader, selected.image );
			reader.setAttribute( 'role', 'status' );
			stage.append( reader );
			stage.append(
				button( __( 'Enlarge selected card', 'ohmylms' ), () => {
					const dialog = make( 'dialog' );
					dialog.append(
						reader.cloneNode( true ),
						button( __( 'Close card', 'ohmylms' ), () =>
							dialog.close()
						)
					);
					stage.append( dialog );
					dialog.addEventListener( 'close', () => dialog.remove() );
					dialog.showModal();
					cleanups.push( () => {
						dialog.close();
						dialog.remove();
					} );
				} )
			);
		}
		if (
			! complete &&
			[ 'spin-wheel', 'speaking-cards' ].includes( mode )
		) {
			const draw = button(
				mode === 'spin-wheel'
					? __( 'Spin', 'ohmylms' )
					: __( 'Draw random card', 'ohmylms' ),
				() => run( 'spin' )
			);
			draw.disabled = data.cards.every( ( card ) => card.opened );
			stage.append( draw );
		}
		stage.append(
			button(
				complete
					? __( 'Play again', 'ohmylms' )
					: __( 'Finish activity', 'ohmylms' ),
				complete ? restart : () => run( 'finish' )
			)
		);
	} else if ( data.gameFamily === 'magnets' ) {
		stage.append( make( 'p', data.instructions ) );
		stage.append(
			make(
				'p',
				__(
					'Arrange the words freely. Drag a placed word, or focus it and use arrow keys (Shift moves farther). Each position is saved. There is no correct order in this activity.',
					'ohmylms'
				)
			)
		);
		const board = make( 'div' );
		board.className = 'ohmylms-magnet-board';
		board.setAttribute( 'role', 'group' );
		board.setAttribute(
			'aria-label',
			__( 'Word magnet board', 'ohmylms' )
		);
		const bank = make( 'div' );
		bank.className = 'ohmylms-label-bank';
		data.cards.forEach( ( card, index ) => {
			const at = data.positions[ card.id ];
			if ( ! at ) {
				const add = button( card.text, () =>
					run(
						'place',
						`${ card.id }|50|${ 10 + ( index % 6 ) * 14 }`
					)
				);
				add.disabled = complete;
				bank.append( add );
				return;
			}
			const node = button( card.text, () => {} );
			node.dataset.cardId = card.id;
			node.className = 'ohmylms-magnet';
			node.disabled = complete;
			let [ x, y ] = at;
			let start = null;
			const paint = () => {
				node.style.left = `${ x }%`;
				node.style.top = `${ y }%`;
			};
			const save = () => run( 'place', `${ card.id }|${ x }|${ y }` );
			node.addEventListener( 'pointerdown', ( event ) => {
				if ( event.button !== 0 ) return;
				start = event.pointerId;
				node.setPointerCapture( start );
			} );
			node.addEventListener( 'pointermove', ( event ) => {
				if ( start !== event.pointerId ) return;
				const rect = board.getBoundingClientRect();
				x = Math.max(
					0,
					Math.min(
						100,
						( 100 * ( event.clientX - rect.left ) ) / rect.width
					)
				);
				y = Math.max(
					0,
					Math.min(
						100,
						( 100 * ( event.clientY - rect.top ) ) / rect.height
					)
				);
				paint();
			} );
			node.addEventListener( 'pointerup', () => {
				if ( start === null ) return;
				start = null;
				void save();
			} );
			node.addEventListener( 'pointercancel', () => {
				start = null;
				[ x, y ] = at;
				paint();
			} );
			node.addEventListener( 'keydown', ( event ) => {
				const delta = {
					ArrowLeft: [ -1, 0 ],
					ArrowRight: [ 1, 0 ],
					ArrowUp: [ 0, -1 ],
					ArrowDown: [ 0, 1 ],
				}[ event.key ];
				if ( ! delta ) return;
				event.preventDefault();
				const step = event.shiftKey ? 5 : 1;
				x = Math.max( 0, Math.min( 100, x + step * delta[ 0 ] ) );
				y = Math.max( 0, Math.min( 100, y + step * delta[ 1 ] ) );
				paint();
			} );
			node.addEventListener( 'keyup', ( event ) => {
				if ( event.key.startsWith( 'Arrow' ) ) void save();
			} );
			paint();
			board.append( node );
		} );
		stage.append(
			board,
			bank,
			button(
				complete
					? __( 'Play again', 'ohmylms' )
					: __( 'Finish composition', 'ohmylms' ),
				complete ? restart : () => run( 'finish' )
			)
		);
	} else {
		stage.append(
			make(
				'p',
				sprintf(
					/* translators: 1: round, 2: total, 3: practice points. */ __(
						'Round %1$d / %2$d · Points: %3$d',
						'ohmylms'
					),
					data.round,
					data.total,
					data.points
				)
			),
			make( 'h4', data.prompt ),
			make( 'p', data.body )
		);
		showImage( stage, data.image );
		if ( data.phase === 'ready' ) {
			if ( mode === 'win-or-lose' ) {
				const label = make( 'label', __( 'Stake', 'ohmylms' ) );
				const select = make( 'select' );
				select.setAttribute( 'aria-label', __( 'Stake', 'ohmylms' ) );
				[ ...new Set( [ 10, 25, 50, Math.min( 10, data.points ) ] ) ]
					.filter( ( value ) => value <= data.points )
					.forEach( ( value ) => {
						const option = make( 'option', String( value ) );
						option.value = String( value );
						select.append( option );
					} );
				select.value = String( data.stake );
				select.addEventListener( 'change', () =>
					run( 'stake', select.value )
				);
				label.append( select );
				stage.append( label );
			}
			stage.append(
				button( __( 'Begin round', 'ohmylms' ), () => run( 'begin' ) )
			);
		} else if ( data.phase === 'paused' )
			stage.append(
				button( __( 'Resume round', 'ohmylms' ), () => run( 'resume' ) )
			);
		else if ( data.phase === 'review' ) {
			const result = make(
				'p',
				sprintf(
					/* translators: 1: result, 2: practice points awarded. */ __(
						'%1$s · %2$d points',
						'ohmylms'
					),
					data.correct
						? __( 'Correct', 'ohmylms' )
						: __( 'Incorrect or time expired', 'ohmylms' ),
					data.roundPoints
				)
			);
			result.setAttribute( 'role', 'status' );
			stage.append( result );
			data.choices.forEach( ( choice ) => {
				const row = make(
					'p',
					choice.text +
						( choice.id === data.correctId
							? ' — ' + __( 'Correct answer', 'ohmylms' )
							: '' )
				);
				if ( choice.id === data.selected )
					row.append(
						make( 'strong', ' — ' + __( 'Your answer', 'ohmylms' ) )
					);
				showImage( row, choice.image );
				stage.append( row );
			} );
			stage.append(
				button(
					complete
						? __( 'Play again', 'ohmylms' )
						: __( 'Next round', 'ohmylms' ),
					complete ? restart : () => run( 'next' )
				)
			);
		} else {
			const clock = make( 'p' );
			clock.setAttribute( 'role', 'timer' );
			stage.append( clock );
			const until = Date.now() + data.remainingSeconds * 1000;
			let expired = false;
			const retry = button(
				__( 'Time expired — continue', 'ohmylms' ),
				() => run( 'timeout' )
			);
			retry.hidden = true;
			const tick = () => {
				const seconds = Math.max(
					0,
					Math.ceil( ( until - Date.now() ) / 1000 )
				);
				clock.textContent = sprintf(
					/* translators: %d: seconds remaining. */ __(
						'Time remaining: %d',
						'ohmylms'
					),
					seconds
				);
				if ( ! seconds && ! expired ) {
					expired = true;
					retry.hidden = false;
					const timeout = setTimeout( () => {
						void run( 'timeout' );
					}, 0 );
					cleanups.push( () => clearTimeout( timeout ) );
				}
			};
			const timer = setInterval( tick, 250 );
			cleanups.push( () => clearInterval( timer ) );
			tick();
			const grid = make( 'div' );
			grid.className = 'ohmylms-study-cards ohmylms-game-answers';
			data.choices.forEach( ( choice ) => {
				const option = button( choice.text, () =>
					run( 'answer', choice.id )
				);
				option.dataset.cardId = choice.id;
				showImage( option, choice.image, choice.text );
				grid.append( option );
			} );
			const arcades = [
				'maze-chase',
				'airplane',
				'flying-fruit',
				'whack-a-mole',
				'balloon-pop',
			];
			if ( arcades.includes( mode ) ) {
				const area = make( 'div' );
				stage.append( area );
				import(
					new URL(
						'./arcade-player.mjs' +
							new URL( import.meta.url ).search,
						import.meta.url
					).href
				)
					.then( ( { renderArcade } ) => {
						if ( ! disposed )
							cleanups.push(
								renderArcade( area, data, ( command, value ) =>
									run( command, value )
								)
							);
					} )
					.catch( () => {} );
				const alternative = make( 'details' );
				alternative.open = window.matchMedia(
					'(prefers-reduced-motion: reduce)'
				).matches;
				alternative.append(
					make(
						'summary',
						__( 'Stationary answer buttons', 'ohmylms' )
					),
					grid
				);
				stage.append( alternative );
			} else stage.append( grid );
			if ( mode === 'gameshow' && ! data.helpUsed )
				stage.append(
					button( __( '50/50 lifeline', 'ohmylms' ), () =>
						run( 'help' )
					)
				);
			stage.append(
				button( __( 'Pause round', 'ohmylms' ), () => run( 'pause' ) ),
				retry
			);
		}
	}
	stage.querySelector( 'button:not(:disabled)' )?.focus();
	return () => {
		disposed = true;
		cleanups.forEach( ( cleanup ) => cleanup() );
	};
}
