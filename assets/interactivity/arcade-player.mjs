const { __ } = window.wp.i18n;

/**
 * Optional arcade surface. Stationary native answer buttons remain available.
 *
 * @param {HTMLElement} area Activity region.
 * @param {Object} data Safe round.
 * @param {(command: string, value?: string) => Promise<void>} act Save practice action.
 * @return {() => void} Stop this surface's animation.
 */
export function renderArcade( area, data, act ) {
	const make = ( tag, text = '' ) => {
		const node = area.ownerDocument.createElement( tag );
		node.textContent = text;
		return node;
	};
	const board = make( 'div' );
	board.className = 'ohmylms-arcade';
	board.tabIndex = 0;
	board.setAttribute( 'role', 'group' );
	board.setAttribute(
		'aria-label',
		__(
			'Arcade board. Use arrow keys or the direction buttons. Stationary answer buttons are also available.',
			'ohmylms'
		)
	);
	const status = make( 'p' );
	status.setAttribute( 'role', 'status' );
	area.append( board, status );
	let stopped = false;
	let chosen = false;
	let frame;
	let interval;
	let falling;
	const send = ( command, value = '' ) => {
		if ( chosen || stopped ) return;
		chosen = true;
		void act( command, value );
	};
	const choices = data.choices;
	const option = ( choice ) => {
		const node = make( 'button', choice.text );
		node.type = 'button';
		node.dataset.cardId = choice.id;
		if ( choice.image ) {
			const image = make( 'img' );
			image.src = choice.image;
			image.alt = choice.text;
			node.append( image );
		}
		board.append( node );
		return node;
	};
	const mode = data.activityMode;
	const reduced = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;
	if ( mode === 'flying-fruit' || mode === 'balloon-pop' ) {
		board.classList.add(
			mode === 'flying-fruit' ? 'ohmylms-fruit' : 'ohmylms-balloons'
		);
		choices.forEach( ( choice, index ) => {
			const node = option( choice );
			node.style.left = `${ 5 + ( index * 80 ) / Math.max( 1, choices.length - 1 ) }%`;
			node.style.animationDelay = `-${ index * 0.8 }s`;
			node.addEventListener( 'click', () => {
				if ( mode === 'balloon-pop' ) {
					if ( chosen ) return;
					chosen = true;
					node.classList.add( 'is-falling' );
					status.textContent = __(
						'Label dropping onto the prompt…',
						'ohmylms'
					);
					falling = setTimeout(
						() => {
							if ( ! stopped ) void act( 'answer', choice.id );
						},
						reduced ? 0 : 350
					);
				} else send( 'answer', choice.id );
			} );
		} );
		status.textContent =
			mode === 'balloon-pop'
				? __(
						'Pop the matching balloon. Its label drops onto the prompt.',
						'ohmylms'
					)
				: __(
						'Select the fruit carrying the correct answer.',
						'ohmylms'
					);
	} else if ( mode === 'whack-a-mole' ) {
		board.classList.add( 'ohmylms-moles' );
		const holes = Array.from( { length: 9 }, () => {
			const hole = make( 'div' );
			hole.className = 'ohmylms-mole-hole';
			board.append( hole );
			return hole;
		} );
		let turn = 0;
		const pop = () => {
			if ( chosen || stopped ) return;
			holes.forEach( ( hole ) => hole.replaceChildren() );
			const choice = choices[ turn % choices.length ];
			const node = make( 'button', choice.text );
			node.type = 'button';
			node.addEventListener( 'click', () => send( 'answer', choice.id ) );
			holes[ ( turn * 4 ) % 9 ].append( node );
			turn++;
		};
		pop();
		if ( ! reduced ) interval = setInterval( pop, 1100 );
		else
			choices.slice( 1 ).forEach( ( choice, index ) => {
				const node = option( choice );
				holes[ index + 1 ].append( node );
				node.addEventListener( 'click', () =>
					send( 'answer', choice.id )
				);
			} );
		status.textContent = __(
			'Wait for the correct answer to pop up, then tap it.',
			'ohmylms'
		);
	} else {
		const player = make( 'span', mode === 'airplane' ? '✈' : '●' );
		player.className = 'ohmylms-arcade-player';
		player.setAttribute( 'aria-hidden', 'true' );
		board.append( player );
		let x = mode === 'airplane' ? 10 : 0;
		let y = mode === 'airplane' ? 50 : 3;
		const moveButtons = make( 'div' );
		moveButtons.className = 'ohmylms-arcade-directions';
		area.append( moveButtons );
		let move = () => {};
		if ( mode === 'maze-chase' ) {
			board.classList.add( 'ohmylms-maze' );
			const walls = new Set();
			for ( let row = 0; row < 7; row++ ) {
				if ( row !== 3 ) {
					if ( row < 6 ) walls.add( `2:${ row }` );
					if ( row > 0 ) walls.add( `6:${ row }` );
				}
			}
			for ( const key of walls ) {
				const [ wx, wy ] = key.split( ':' ).map( Number );
				const wall = make( 'span' );
				wall.className = 'ohmylms-maze-wall';
				wall.style.left = `${ ( wx * 100 ) / 9 }%`;
				wall.style.top = `${ ( wy * 100 ) / 7 }%`;
				board.append( wall );
			}
			const spots = [
				[ 8, 0 ],
				[ 8, 6 ],
				[ 4, 0 ],
				[ 4, 6 ],
				[ 0, 0 ],
				[ 0, 6 ],
			];
			choices.forEach( ( choice, index ) => {
				const node = option( choice );
				node.className = 'ohmylms-maze-goal';
				node.style.left = `${ ( ( spots[ index ][ 0 ] + 0.5 ) * 100 ) / 9 }%`;
				node.style.top = `${ ( ( spots[ index ][ 1 ] + 0.5 ) * 100 ) / 7 }%`;
				node.addEventListener( 'click', () =>
					send( 'answer', choice.id )
				);
			} );
			const paint = () => {
				player.style.left = `${ ( ( x + 0.5 ) * 100 ) / 9 }%`;
				player.style.top = `${ ( ( y + 0.5 ) * 100 ) / 7 }%`;
			};
			move = ( dx, dy ) => {
				if ( chosen ) return;
				const nx = x + dx,
					ny = y + dy;
				if (
					nx < 0 ||
					nx > 8 ||
					ny < 0 ||
					ny > 6 ||
					walls.has( `${ nx }:${ ny }` )
				)
					return;
				x = nx;
				y = ny;
				paint();
				const index = spots.findIndex(
					( [ gx, gy ] ) => gx === x && gy === y
				);
				if ( index >= 0 && choices[ index ] )
					send( 'answer', choices[ index ].id );
			};
			const enemy = make( 'span', '◆' );
			enemy.className = 'ohmylms-arcade-enemy';
			enemy.setAttribute( 'aria-hidden', 'true' );
			board.append( enemy );
			let ex = 8,
				ey = 3;
			const chase = () => {
				if ( chosen || stopped ) return;
				const candidates = [
					[ ex - 1, ey ],
					[ ex + 1, ey ],
					[ ex, ey - 1 ],
					[ ex, ey + 1 ],
				].filter(
					( [ nx, ny ] ) =>
						nx >= 0 &&
						nx <= 8 &&
						ny >= 0 &&
						ny <= 6 &&
						! walls.has( `${ nx }:${ ny }` )
				);
				candidates.sort(
					( a, b ) =>
						Math.abs( a[ 0 ] - x ) +
						Math.abs( a[ 1 ] - y ) -
						Math.abs( b[ 0 ] - x ) -
						Math.abs( b[ 1 ] - y )
				);
				if ( candidates[ 0 ] ) [ ex, ey ] = candidates[ 0 ];
				enemy.style.left = `${ ( ( ex + 0.5 ) * 100 ) / 9 }%`;
				enemy.style.top = `${ ( ( ey + 0.5 ) * 100 ) / 7 }%`;
				if ( ex === x && ey === y ) send( 'miss' );
			};
			enemy.style.left = '94.4%';
			enemy.style.top = '50%';
			paint();
			if ( ! reduced ) interval = setInterval( chase, 1400 );
			status.textContent = __(
				'Reach the correct answer. Avoid the diamond chaser. You can also select an answer directly.',
				'ohmylms'
			);
		} else {
			board.classList.add( 'ohmylms-flight' );
			const clouds = choices.map( ( choice, index ) => ( {
				choice,
				node: option( choice ),
				x: 45 + ( index * 55 ) / choices.length,
				y: 12 + ( index * 76 ) / Math.max( 1, choices.length - 1 ),
			} ) );
			clouds.forEach( ( cloud ) => {
				cloud.node.addEventListener( 'click', () =>
					send( 'answer', cloud.choice.id )
				);
			} );
			const paint = () => {
				player.style.left = `${ x }%`;
				player.style.top = `${ y }%`;
				clouds.forEach( ( cloud ) => {
					cloud.node.style.left = `${ cloud.x }%`;
					cloud.node.style.top = `${ cloud.y }%`;
					if (
						Math.abs( cloud.x - x ) < 8 &&
						Math.abs( cloud.y - y ) < 9
					)
						send( 'answer', cloud.choice.id );
				} );
			};
			move = ( dx, dy ) => {
				x = Math.max( 3, Math.min( 97, x + dx * 6 ) );
				y = Math.max( 5, Math.min( 95, y + dy * 8 ) );
				paint();
			};
			let previous = performance.now();
			const animate = ( now ) => {
				if ( stopped || chosen ) return;
				const elapsed = Math.min( 0.1, ( now - previous ) / 1000 );
				previous = now;
				clouds.forEach( ( cloud ) => {
					if ( ! reduced ) cloud.x -= elapsed * 9;
					if ( cloud.x < 0 ) cloud.x = 100;
				} );
				paint();
				frame = requestAnimationFrame( animate );
			};
			paint();
			frame = requestAnimationFrame( animate );
			status.textContent = __(
				'Fly into the correct answer cloud. Use arrows, direction buttons, or select the cloud directly.',
				'ohmylms'
			);
		}
		const directions = [
			[ __( 'Left', 'ohmylms' ), -1, 0 ],
			[ __( 'Up', 'ohmylms' ), 0, -1 ],
			[ __( 'Down', 'ohmylms' ), 0, 1 ],
			[ __( 'Right', 'ohmylms' ), 1, 0 ],
		];
		directions.forEach( ( [ label, dx, dy ] ) => {
			const node = make( 'button', label );
			node.type = 'button';
			node.addEventListener( 'click', () => move( dx, dy ) );
			moveButtons.append( node );
		} );
		board.addEventListener( 'keydown', ( event ) => {
			const delta = {
				ArrowLeft: [ -1, 0 ],
				ArrowRight: [ 1, 0 ],
				ArrowUp: [ 0, -1 ],
				ArrowDown: [ 0, 1 ],
			}[ event.key ];
			if ( delta ) {
				event.preventDefault();
				move( ...delta );
			}
		} );
	}
	return () => {
		stopped = true;
		clearInterval( interval );
		clearTimeout( falling );
		cancelAnimationFrame( frame );
	};
}
