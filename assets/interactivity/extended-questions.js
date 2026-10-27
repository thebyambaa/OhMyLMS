/** Extended question widgets share the quiz player's native answer fields. */
( function () {
	'use strict';
	const api = window.OhMyLMSInteractive;
	if ( ! api ) {
		return;
	}
	const { el, setAnswer } = api.util;
	const diagramModule = new URL(
		'./diagram-labels.mjs',
		document.currentScript.src
	);
	diagramModule.search = new URL( document.currentScript.src ).search;
	const { __, sprintf } = window.wp.i18n;
	let instanceId = 0;
	const types = [
		'passage',
		'graphing',
		'hot-text',
		'match-table-grid',
		'labeling',
		'hotspot',
		'draw',
		'audio-response',
		'video-response',
		'poll',
		'word-cloud',
		'discussion-board',
		'slide',
		'interactive-video',
		'spin-wheel',
		'gameshow',
		'hangman',
		'open-box',
		'speaking-cards',
		'flip-tiles',
		'flashcards',
		'timed-true-false',
		'whack-a-mole',
		'flying-fruit',
	];
	const button = ( text, action ) => {
		const node = el( 'button', { type: 'button', text } );
		node.addEventListener( 'click', action );
		return node;
	};
	function field( root, key, label, multiline = false ) {
		const wrapper = el( 'label', { class: 'ohmylms-extended-field' }, [
			el( 'span', { text: label } ),
		] );
		const input = el( multiline ? 'textarea' : 'input', {
			'aria-label': label,
			...( multiline ? { rows: 4 } : { type: 'text' } ),
		} );
		input.addEventListener( 'input', () =>
			setAnswer( root, key, input.value )
		);
		wrapper.appendChild( input );
		root.appendChild( wrapper );
		return input;
	}
	/**
	 * Ungraded card activities. Every card is public content, so nothing here
	 * hides an answer key; the answer only records that the learner took part.
	 * @param root
	 * @param type
	 * @param config
	 */
	function deck( root, type, config ) {
		const cards = config.cards || [];
		const seen = new Set();
		root.classList.add( 'ohmylms-activity' );
		const progress = el( 'p', {
			class: 'ohmylms-deck-progress',
			role: 'status',
			'aria-live': 'polite',
		} );
		const mark = ( card ) => {
			seen.add( card.id );
			setAnswer( root, 'seen', String( seen.size ) );
			progress.textContent = sprintf(
				/* translators: 1: cards seen, 2: total cards. */
				__( '%1$d of %2$d cards seen', 'ohmylms' ),
				seen.size,
				cards.length
			);
		};
		if ( type === 'open-box' || type === 'flip-tiles' ) {
			const hint = el( 'p', {
				class: 'ohmylms-vis-hint',
				text:
					type === 'open-box'
						? __( 'Choose a box to open it.', 'ohmylms' )
						: __(
								'Tap a tile to flip it. Tap again to flip back.',
								'ohmylms'
							),
			} );
			const grid = el( 'div', { class: 'ohmylms-deck-grid' } );
			cards.forEach( ( card, index ) => {
				const tile = el( 'button', {
					type: 'button',
					class: 'ohmylms-deck-tile',
					'aria-pressed': 'false',
					text: String( index + 1 ),
				} );
				let shown = false;
				tile.addEventListener( 'click', () => {
					shown = ! shown;
					tile.setAttribute( 'aria-pressed', String( shown ) );
					tile.classList.toggle( 'is-open', shown );
					if ( type === 'open-box' ) {
						tile.textContent = card.text;
						tile.disabled = true;
					} else {
						tile.textContent = shown
							? card.back || card.text
							: card.text || String( index + 1 );
					}
					mark( card );
				} );
				if ( type === 'flip-tiles' ) {
					tile.textContent = card.text;
				}
				grid.appendChild( tile );
			} );
			root.append( hint, grid, progress );
			return;
		}
		if ( type === 'speaking-cards' ) {
			const order = [];
			const shown = el( 'div', {
				class: 'ohmylms-spin-result',
				role: 'status',
				'aria-live': 'polite',
				text: __( 'Draw a card to begin.', 'ohmylms' ),
			} );
			const draw = button( __( 'Draw a card', 'ohmylms' ), () => {
				const left = cards.filter( ( card ) => ! seen.has( card.id ) );
				if ( ! left.length ) {
					return;
				}
				const card = left[ Math.floor( Math.random() * left.length ) ];
				order.push( card );
				shown.textContent = card.text;
				mark( card );
				draw.disabled = seen.size >= cards.length;
				if ( draw.disabled ) {
					draw.textContent = __( 'All cards drawn', 'ohmylms' );
				}
			} );
			draw.classList.add( 'ohmylms-activity-button' );
			root.append( draw, shown, progress );
			return;
		}
		// Flashcards: one card at a time, tap to reveal the back.
		let index = 0;
		let showBack = false;
		const face = el( 'button', {
			type: 'button',
			class: 'ohmylms-flashcard',
			'aria-live': 'polite',
		} );
		const paint = () => {
			const card = cards[ index ];
			face.textContent = showBack ? card.back || card.text : card.text;
			face.classList.toggle( 'is-back', showBack );
			face.setAttribute(
				'aria-label',
				( showBack
					? __( 'Back', 'ohmylms' )
					: __( 'Front', 'ohmylms' ) ) +
					': ' +
					face.textContent
			);
			previous.disabled = index === 0;
			next.disabled = index === cards.length - 1;
			mark( card );
		};
		face.addEventListener( 'click', () => {
			showBack = ! showBack;
			paint();
		} );
		const previous = button( __( 'Previous card', 'ohmylms' ), () => {
			index = Math.max( 0, index - 1 );
			showBack = false;
			paint();
		} );
		const next = button( __( 'Next card', 'ohmylms' ), () => {
			index = Math.min( cards.length - 1, index + 1 );
			showBack = false;
			paint();
		} );
		root.append(
			el( 'p', {
				class: 'ohmylms-vis-hint',
				text: __( 'Tap the card to turn it over.', 'ohmylms' ),
			} ),
			face,
			el( 'div', { class: 'ohmylms-graph-actions' }, [ previous, next ] ),
			progress
		);
		if ( cards.length ) {
			paint();
		}
	}

	/**
	 * Timed answer-pick games. The learner only chooses an answer; the server
	 * holds the key and does the grading, as for the gameshow quiz.
	 * @param root
	 * @param type
	 * @param config
	 */
	function pick( root, type, config ) {
		const total = Math.max( 5, Number( config.seconds ) || 20 );
		let remaining = total;
		root.classList.add( 'ohmylms-activity', 'is-' + type );
		const clock = el( 'p', {
			class: 'ohmylms-gameshow-clock',
			role: 'timer',
			text: sprintf(
				/* translators: %d: seconds left. */
				__( '%d seconds left', 'ohmylms' ),
				remaining
			),
		} );
		const bar = el( 'div', { class: 'ohmylms-gameshow-bar' }, [
			el( 'span' ),
		] );
		const hints = {
			'timed-true-false': __(
				'Choose True or False before the time runs out.',
				'ohmylms'
			),
			'whack-a-mole': __(
				'Tap the right answer when it pops up.',
				'ohmylms'
			),
			'flying-fruit': __(
				'Tap the answer you want as it floats past.',
				'ohmylms'
			),
		};
		const pickField = el( 'div', { class: 'ohmylms-pick-field' } );
		const buttons = ( config.choices || [] ).map( ( choice, index ) => {
			const target = el( 'button', {
				type: 'button',
				class: 'ohmylms-pick-target',
				'aria-pressed': 'false',
				text: choice.text,
			} );
			target.style.setProperty( '--ohmylms-slot', String( index ) );
			target.style.setProperty(
				'--ohmylms-count',
				String( ( config.choices || [] ).length )
			);
			target.addEventListener( 'click', () => {
				buttons.forEach( ( other ) =>
					other.setAttribute(
						'aria-pressed',
						String( other === target )
					)
				);
				setAnswer( root, 'choice', choice.id );
			} );
			pickField.appendChild( target );
			return target;
		} );
		root.append(
			el( 'p', { class: 'ohmylms-vis-hint', text: hints[ type ] } ),
			clock,
			bar,
			pickField
		);
		const startTimer = () => {
			const timer = window.setInterval( () => {
				if ( ! root.isConnected ) {
					window.clearInterval( timer );
					return;
				}
				remaining -= 1;
				bar.firstChild.style.width = ( remaining / total ) * 100 + '%';
				if ( remaining <= 0 ) {
					window.clearInterval( timer );
					buttons.forEach( ( target ) => {
						target.disabled = true;
					} );
					clock.textContent = __( 'Time is up', 'ohmylms' );
					return;
				}
				clock.textContent = sprintf(
					/* translators: %d: seconds left. */
					__( '%d seconds left', 'ohmylms' ),
					remaining
				);
			}, 1000 );
		};
		bar.firstChild.style.width = '100%';
		if ( 'IntersectionObserver' in window ) {
			const watcher = new window.IntersectionObserver( ( entries ) => {
				if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
					watcher.disconnect();
					startTimer();
				}
			} );
			watcher.observe( root );
		} else {
			startTimer();
		}
	}

	/**
	 * Coordinate graph on a JSXGraph board. Learners click the board (or type x and y)
	 * to place points; points can be dragged and snap to quarter units. Line mode
	 * keeps two points and draws the line through them. The answer is the same JSON
	 * list of { x, y } points the server has always graded.
	 * @param root
	 * @param config
	 * @param instance
	 */
	function graph( root, config, instance ) {
		const min = Number( config.min ?? -5 );
		const max = Number( config.max ?? 5 );
		const line = config.mode === 'line';
		const limit = line ? 2 : 50;
		const hint = el( 'p', {
			class: 'ohmylms-vis-hint',
			text: line
				? __(
						'Click the graph to place two points. The line through them is your answer. Drag a point to move it.',
						'ohmylms'
					)
				: __(
						'Click the graph to place a point. Drag a point to move it.',
						'ohmylms'
					),
		} );
		const box = el( 'div', {
			class: 'ohmylms-jsxgraph jxgbox',
			id: 'ohmylms-jxg-' + instance,
			role: 'img',
			'aria-label': __( 'Coordinate graph', 'ohmylms' ),
		} );
		const readout = el( 'p', {
			class: 'ohmylms-vis-readout',
			'aria-live': 'polite',
			text: __( 'No points placed yet.', 'ohmylms' ),
		} );
		const points = [];
		let board = null;
		const snap = ( value ) => Math.round( value * 4 ) / 4;
		const inRange = ( value ) => value >= min && value <= max;
		const write = () => {
			const list = points.map( ( point ) => ( {
				x: snap( point.X() ),
				y: snap( point.Y() ),
			} ) );
			setAnswer(
				root,
				'points',
				list.length ? JSON.stringify( list ) : ''
			);
			readout.textContent = list.length
				? list
						.map(
							( point ) => '(' + point.x + ', ' + point.y + ')'
						)
						.join( '  ' )
				: __( 'No points placed yet.', 'ohmylms' );
		};
		const place = ( x, y ) => {
			if (
				! board ||
				points.length >= limit ||
				! inRange( x ) ||
				! inRange( y )
			) {
				return;
			}
			const point = board.create( 'point', [ snap( x ), snap( y ) ], {
				name: '',
				size: 5,
				fillColor: '#7153c6',
				strokeColor: '#432474',
				highlightFillColor: '#e8a317',
				snapToGrid: true,
				snapSizeX: 0.25,
				snapSizeY: 0.25,
				showInfobox: true,
			} );
			point.on( 'up', write );
			points.push( point );
			if ( line && points.length === 2 ) {
				board.create( 'line', points, {
					strokeColor: '#7153c6',
					strokeWidth: 3,
					straightFirst: true,
					straightLast: true,
					fixed: true,
					highlight: false,
				} );
			}
			write();
		};
		const start = () => {
			if ( board || ! window.JXG ) {
				return;
			}
			board = window.JXG.JSXGraph.initBoard( box, {
				document: box.ownerDocument,
				boundingbox: [ min, max, max, min ],
				axis: true,
				grid: true,
				showCopyright: false,
				showNavigation: false,
				pan: { enabled: false },
				zoom: { enabled: false },
			} );
			board.on( 'down', ( event ) => {
				if (
					board
						.getAllObjectsUnderMouse( event )
						.some( ( object ) => object.elType === 'point' )
				) {
					return;
				}
				const coords = board.getUsrCoordsOfMouse( event );
				place( coords[ 0 ], coords[ 1 ] );
			} );
		};
		const undo = button( __( 'Undo last point', 'ohmylms' ), () => {
			const last = points.pop();
			if ( last && board ) {
				board.removeObject( last );
				board.fullUpdate();
				if ( line && points.length < 2 ) {
					board.objectsList
						.filter( ( object ) => object.elType === 'line' )
						.forEach( ( object ) => board.removeObject( object ) );
				}
				write();
			}
		} );
		const clear = button( __( 'Clear', 'ohmylms' ), () => {
			while ( points.length ) {
				board.removeObject( points.pop() );
			}
			board.objectsList
				.filter( ( object ) => object.elType === 'line' )
				.forEach( ( object ) => board.removeObject( object ) );
			write();
		} );
		// Typed coordinates are the keyboard and screen reader route.
		const xField = el( 'input', {
			type: 'number',
			step: '0.25',
			'aria-label': __( 'x value', 'ohmylms' ),
		} );
		const yField = el( 'input', {
			type: 'number',
			step: '0.25',
			'aria-label': __( 'y value', 'ohmylms' ),
		} );
		const add = button( __( 'Add point', 'ohmylms' ), () => {
			if ( xField.value !== '' && yField.value !== '' ) {
				place( Number( xField.value ), Number( yField.value ) );
				xField.value = '';
				yField.value = '';
			}
		} );
		const typed = el( 'div', { class: 'ohmylms-graph-typed' }, [
			el( 'span', { text: __( 'Or type a point:', 'ohmylms' ) } ),
			xField,
			yField,
			add,
		] );
		root.append(
			hint,
			box,
			readout,
			el( 'div', { class: 'ohmylms-graph-actions' }, [ undo, clear ] ),
			typed
		);
		// The board needs a visible box to measure, so it starts when the question is shown.
		if ( 'IntersectionObserver' in window ) {
			const watcher = new window.IntersectionObserver( ( entries ) => {
				if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
					watcher.disconnect();
					start();
				}
			} );
			watcher.observe( box );
		} else {
			start();
		}
	}
	function plot( root, config, drawing ) {
		const canvas = el( 'canvas', {
			width: 640,
			height: 400,
			tabindex: 0,
			'aria-label': drawing
				? __( 'Drawing canvas', 'ohmylms' )
				: __( 'Coordinate graph', 'ohmylms' ),
		} );
		const context = canvas.getContext( '2d' );
		const min = Number( config.min ?? -5 ),
			max = Number( config.max ?? 5 ),
			span = max - min;
		let points = [],
			painting = false;
		const pixel = ( point ) => ( {
			x: ( ( point.x - min ) / span ) * 640,
			y: ( ( max - point.y ) / span ) * 400,
		} );
		function paint() {
			context.clearRect( 0, 0, 640, 400 );
			if ( ! drawing ) {
				context.strokeStyle = '#cfd6e0';
				context.lineWidth = 1;
				for ( let value = Math.ceil( min ); value <= max; value++ ) {
					const position = pixel( { x: value, y: value } );
					context.beginPath();
					context.moveTo( position.x, 0 );
					context.lineTo( position.x, 400 );
					context.moveTo( 0, position.y );
					context.lineTo( 640, position.y );
					context.stroke();
				}
				context.strokeStyle = '#3c434a';
				context.beginPath();
				const zero = pixel( { x: 0, y: 0 } );
				context.moveTo( zero.x, 0 );
				context.lineTo( zero.x, 400 );
				context.moveTo( 0, zero.y );
				context.lineTo( 640, zero.y );
				context.stroke();
				context.fillStyle = '#3c434a';
				context.font = '12px sans-serif';
				const labelStep = Math.max( 1, Math.ceil( span / 20 ) );
				for (
					let value = Math.ceil( min );
					value <= max;
					value += labelStep
				) {
					const position = pixel( { x: value, y: value } );
					context.fillText(
						String( value ),
						Math.min( 615, Math.max( 4, position.x + 3 ) ),
						Math.min( 390, Math.max( 14, zero.y + 14 ) )
					);
					if ( value !== 0 ) {
						context.fillText(
							String( value ),
							Math.min( 615, Math.max( 4, zero.x + 3 ) ),
							Math.min( 390, Math.max( 14, position.y - 3 ) )
						);
					}
				}
			}
			context.strokeStyle = '#7153c6';
			context.fillStyle = '#7153c6';
			context.lineWidth = 3;
			if ( drawing || config.mode === 'line' ) {
				context.beginPath();
				points.forEach( ( point, index ) => {
					const p = drawing
						? { x: point.x * 6.4, y: point.y * 4 }
						: pixel( point );
					if ( index === 0 || point.start ) {
						context.moveTo( p.x, p.y );
					} else {
						context.lineTo( p.x, p.y );
					}
				} );
				context.stroke();
			}
			if ( ! drawing ) {
				points.forEach( ( point ) => {
					const p = pixel( point );
					context.beginPath();
					context.arc( p.x, p.y, 6, 0, Math.PI * 2 );
					context.fill();
				} );
			}
		}
		const write = () => {
			setAnswer(
				root,
				'points',
				points.length ? JSON.stringify( points ) : ''
			);
			paint();
		};
		function add( event, start = false ) {
			const bounds = canvas.getBoundingClientRect();
			const x = Math.max(
					0,
					Math.min(
						1,
						( event.clientX - bounds.left ) / bounds.width
					)
				),
				y = Math.max(
					0,
					Math.min(
						1,
						( event.clientY - bounds.top ) / bounds.height
					)
				);
			const point = drawing
				? { x: x * 100, y: y * 100, start }
				: {
						x: Math.round( ( min + x * span ) * 4 ) / 4,
						y: Math.round( ( max - y * span ) * 4 ) / 4,
					};
			if ( points.length < ( drawing ? 5000 : 50 ) ) {
				points.push( point );
			}
			write();
		}
		canvas.addEventListener( 'pointerdown', ( event ) => {
			canvas.setPointerCapture( event.pointerId );
			painting = true;
			if ( ! drawing && config.mode === 'line' && points.length === 2 ) {
				points = [];
			}
			add( event, true );
		} );
		canvas.addEventListener( 'pointermove', ( event ) => {
			if ( drawing && painting ) {
				add( event );
			}
		} );
		canvas.addEventListener( 'pointerup', () => {
			painting = false;
		} );
		canvas.addEventListener( 'pointercancel', () => {
			painting = false;
		} );
		root.appendChild( canvas );
		if ( ! drawing ) {
			const toolbar = el( 'div', { class: 'ohmylms-plot-toolbar' } ),
				x = el( 'input', {
					type: 'number',
					min,
					max,
					step: '0.25',
					'aria-label': __( 'X coordinate', 'ohmylms' ),
					value: 0,
				} ),
				y = el( 'input', {
					type: 'number',
					min,
					max,
					step: '0.25',
					'aria-label': __( 'Y coordinate', 'ohmylms' ),
					value: 0,
				} );
			toolbar.append(
				x,
				y,
				button( __( 'Plot point', 'ohmylms' ), () => {
					const a = Number( x.value ),
						b = Number( y.value );
					if (
						Number.isFinite( a ) &&
						Number.isFinite( b ) &&
						a >= min &&
						a <= max &&
						b >= min &&
						b <= max
					) {
						if ( config.mode === 'line' && points.length === 2 ) {
							points = [];
						}
						points.push( { x: a, y: b } );
						write();
					}
				} )
			);
			root.appendChild( toolbar );
		}
		root.appendChild(
			button( __( 'Clear', 'ohmylms' ), () => {
				points = [];
				write();
			} )
		);
		paint();
		root.addEventListener( 'change', ( event ) => {
			if ( event.target.getAttribute( 'data-answer-key' ) === 'points' ) {
				try {
					points = JSON.parse( event.target.value || '[]' );
					paint();
				} catch {
					points = [];
				}
			}
		} );
	}
	function media( root, type, config ) {
		const video = type === 'video-response';
		const player = el( video ? 'video' : 'audio', {
			controls: '',
			hidden: '',
			playsinline: '',
			class: 'ohmylms-response-media',
		} );
		root.appendChild( player );
		const status = el( 'p', { role: 'status' } );
		root.appendChild( status );
		const link = field(
			root,
			'media',
			__( 'Media link (optional)', 'ohmylms' )
		);
		link.addEventListener( 'change', () => {
			if ( /^https?:\/\//i.test( link.value ) ) {
				player.src = link.value;
				player.hidden = false;
			}
		} );
		const settings = window.ohmylmsResponseMedia || {};
		const limit = Number(
			( video ? settings.videoBytes : settings.audioBytes ) ||
				4 * 1024 * 1024
		);
		const megabytes = Math.max( 1, Math.floor( limit / 1048576 ) );
		function accept( blob, name ) {
			if ( blob.size > limit ) {
				status.textContent = sprintf(
					/* translators: %d: megabytes. */
					__(
						'Use a recording or file smaller than %d MB.',
						'ohmylms'
					),
					megabytes
				);
				return;
			}
			if ( ! settings.url ) {
				status.textContent = __(
					'Sign in to submit a recording.',
					'ohmylms'
				);
				return;
			}
			const mime = ( blob.type || '' ).split( ';' )[ 0 ];
			const extension =
				( name || '' ).split( '.' ).pop().toLowerCase() ||
				mime.split( '/' ).pop();
			const body = new FormData();
			body.append(
				'file',
				blob,
				'response.' + extension.replace( /[^a-z0-9]/g, '' )
			);
			body.append( 'kind', video ? 'video' : 'audio' );
			status.textContent = __( 'Saving recording…', 'ohmylms' );
			// The file is stored in the uploads folder; only its link is kept in the answer.
			window
				.fetch( settings.url, {
					method: 'POST',
					headers: { 'X-WP-Nonce': settings.nonce },
					credentials: 'same-origin',
					body,
				} )
				.then( ( response ) =>
					response.json().then( ( data ) => {
						if ( ! response.ok || ! data.url ) {
							throw new Error( data.message || '' );
						}
						return data.url;
					} )
				)
				.then( ( url ) => {
					player.srcObject = null;
					player.muted = false;
					player.controls = true;
					player.src = url;
					player.hidden = false;
					setAnswer( root, 'media', url );
					status.textContent = __(
						'Media response ready.',
						'ohmylms'
					);
				} )
				.catch( ( error ) => {
					status.textContent =
						error.message ||
						__(
							'The recording could not be saved. Please try again.',
							'ohmylms'
						);
				} );
		}
		const upload = el( 'input', {
			type: 'file',
			accept: video
				? 'video/webm,video/mp4,video/ogg'
				: 'audio/webm,audio/mp4,audio/mpeg,audio/ogg,audio/wav',
			'aria-label': __( 'Upload media response', 'ohmylms' ),
		} );
		upload.addEventListener( 'change', () => {
			if ( upload.files[ 0 ] ) {
				accept( upload.files[ 0 ], upload.files[ 0 ].name );
			}
		} );
		root.appendChild( upload );

		const buttons = [];
		const stopButton = button( __( 'Stop recording', 'ohmylms' ), () =>
			stop()
		);
		stopButton.disabled = true;
		let recorder, stream, timer, ticker, audioContext;
		const finish = () => {
			stream?.getTracks().forEach( ( track ) => track.stop() );
			audioContext?.close();
			audioContext = null;
			clearTimeout( timer );
			clearInterval( ticker );
			buttons.forEach( ( node ) => ( node.disabled = false ) );
			stopButton.disabled = true;
		};
		function stop() {
			if ( recorder?.state === 'recording' ) {
				recorder.stop();
			} else {
				finish();
			}
		}
		const supported = ( list ) =>
			list.find( ( mime ) =>
				window.MediaRecorder?.isTypeSupported?.( mime )
			);
		/**
		 * Camera and microphone, or the screen with the microphone mixed into its sound.
		 * @param source Either camera or screen.
		 */
		async function capture( source ) {
			if ( source !== 'screen' ) {
				return navigator.mediaDevices.getUserMedia( {
					audio: true,
					video,
				} );
			}
			if ( ! navigator.mediaDevices?.getDisplayMedia ) {
				throw new Error( 'screen' );
			}
			const screen = await navigator.mediaDevices.getDisplayMedia( {
				video: true,
				audio: true,
			} );
			let microphone = null;
			try {
				microphone = await navigator.mediaDevices.getUserMedia( {
					audio: true,
				} );
			} catch {
				microphone = null;
			}
			const withSound = [ screen, microphone ].filter(
				( item ) => item && item.getAudioTracks().length
			);
			const tracks = [ ...screen.getVideoTracks() ];
			if ( withSound.length && window.AudioContext ) {
				audioContext = new window.AudioContext();
				const mix = audioContext.createMediaStreamDestination();
				withSound.forEach( ( item ) =>
					audioContext
						.createMediaStreamSource(
							new MediaStream( item.getAudioTracks() )
						)
						.connect( mix )
				);
				tracks.push( ...mix.stream.getAudioTracks() );
			}
			const combined = new MediaStream( tracks );
			// Stopping must also release the screen and microphone themselves.
			const own = combined.getTracks.bind( combined );
			combined.getTracks = () => [
				...own(),
				...screen.getTracks(),
				...( microphone ? microphone.getTracks() : [] ),
			];
			// The browser's own "Stop sharing" button ends the recording.
			screen.getVideoTracks()[ 0 ]?.addEventListener( 'ended', stop );
			return combined;
		}
		async function record( source ) {
			try {
				if (
					! navigator.mediaDevices?.getUserMedia ||
					! window.MediaRecorder
				) {
					status.textContent = window.isSecureContext
						? __(
								'Recording is unavailable here. Upload a file or use a media link.',
								'ohmylms'
							)
						: __(
								'Browsers only allow recording on secure (HTTPS) pages. Upload a file or use a media link instead.',
								'ohmylms'
							);
					return;
				}
				stream = await capture( source );
				const mimeType = supported(
					video
						? [
								'video/webm;codecs=vp9,opus',
								'video/webm;codecs=vp8,opus',
								'video/webm',
								'video/mp4',
							]
						: [
								'audio/webm;codecs=opus',
								'audio/webm',
								'audio/mp4',
							]
				);
				recorder = new MediaRecorder( stream, {
					...( mimeType ? { mimeType } : {} ),
					// A modest bitrate keeps a minute of video well under the upload limit.
					...( video ? { videoBitsPerSecond: 1500000 } : {} ),
					audioBitsPerSecond: 96000,
				} );
				const chunks = [];
				recorder.addEventListener( 'dataavailable', ( event ) => {
					if ( event.data.size ) {
						chunks.push( event.data );
					}
				} );
				recorder.addEventListener( 'stop', () => {
					const blobType = recorder.mimeType.split( ';' )[ 0 ];
					finish();
					player.srcObject = null;
					accept( new Blob( chunks, { type: blobType } ) );
				} );
				if ( video ) {
					player.controls = false;
					player.muted = true;
					player.srcObject = stream;
					player.hidden = false;
					player.play().catch( () => {} );
				}
				recorder.start( 1000 );
				buttons.forEach( ( node ) => ( node.disabled = true ) );
				stopButton.disabled = false;
				const seconds = Math.max(
					1,
					Math.min( 300, Number( config.max_seconds || 60 ) )
				);
				const started = Date.now();
				const clock = () => {
					status.textContent = sprintf(
						/* translators: 1: elapsed seconds, 2: maximum seconds. */
						__( 'Recording… %1$d s of %2$d s', 'ohmylms' ),
						Math.floor( ( Date.now() - started ) / 1000 ),
						seconds
					);
				};
				clock();
				ticker = setInterval( clock, 1000 );
				timer = setTimeout( stop, seconds * 1000 );
			} catch ( error ) {
				status.textContent =
					error?.message === 'screen'
						? __(
								'Screen recording is not supported in this browser. Use a camera recording or upload a file.',
								'ohmylms'
							)
						: __(
								'Recording permission was not granted. You can upload a file or use a media link.',
								'ohmylms'
							);
				finish();
			}
		}
		const sources =
			video && Array.isArray( config.sources ) && config.sources.length
				? config.sources
				: [ 'camera', 'screen' ];
		const labels = video
			? {
					camera: __( 'Record camera', 'ohmylms' ),
					screen: __( 'Record screen', 'ohmylms' ),
				}
			: { camera: __( 'Record', 'ohmylms' ) };
		( video ? sources : [ 'camera' ] ).forEach( ( source ) => {
			if ( ! labels[ source ] ) {
				return;
			}
			const node = button( labels[ source ], () => record( source ) );
			buttons.push( node );
			root.appendChild( node );
		} );
		root.appendChild( stopButton );
		const observer = new MutationObserver( () => {
			if ( ! root.isConnected ) {
				stop();
				observer.disconnect();
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}
	/**
	 * How to play an interactive video link. Mirrors videoSource in
	 * assets/src/features/question-editor/extendedModel.mjs; only YouTube, Google Drive
	 * and OneDrive addresses are ever placed in an iframe.
	 * @param input Saved link.
	 */
	function videoSource( input ) {
		const none = { provider: 'invalid', embed: '', id: '' };
		const text = String( input || '' ).trim();
		const frame = /<iframe[^>]+src\s*=\s*["']([^"']+)["']/i.exec( text );
		let url;
		try {
			url = new URL( frame ? frame[ 1 ].replace( /&amp;/g, '&' ) : text );
		} catch {
			return none;
		}
		if ( ! /^https?:$/.test( url.protocol ) ) {
			return none;
		}
		const host = url.hostname.toLowerCase().replace( /^www\./, '' );
		const parts = url.pathname.split( '/' ).filter( Boolean );
		const youtube = ( id ) =>
			/^[\w-]{11}$/.test( id || '' )
				? { provider: 'youtube', embed: '', id }
				: none;
		if ( host === 'youtu.be' ) {
			return youtube( parts[ 0 ] );
		}
		if (
			[
				'youtube.com',
				'm.youtube.com',
				'music.youtube.com',
				'youtube-nocookie.com',
			].includes( host )
		) {
			return youtube(
				[ 'embed', 'shorts', 'live', 'v' ].includes( parts[ 0 ] )
					? parts[ 1 ]
					: url.searchParams.get( 'v' )
			);
		}
		if ( host === 'drive.google.com' ) {
			const id =
				parts[ 0 ] === 'file' && parts[ 1 ] === 'd'
					? parts[ 2 ]
					: url.searchParams.get( 'id' );
			return /^[\w-]{10,}$/.test( id || '' )
				? {
						provider: 'drive',
						embed:
							'https://drive.google.com/file/d/' +
							id +
							'/preview',
						id,
					}
				: none;
		}
		if ( host === 'onedrive.live.com' ) {
			return [ 'embed', 'redir' ].includes( parts[ 0 ] ) &&
				url.searchParams.get( 'resid' )
				? {
						provider: 'onedrive',
						embed:
							'https://onedrive.live.com/embed?' +
							url.searchParams.toString(),
						id: '',
					}
				: none;
		}
		if ( host.endsWith( '.sharepoint.com' ) ) {
			return url.pathname.toLowerCase().endsWith( '/embed.aspx' )
				? { provider: 'onedrive', embed: url.href, id: '' }
				: none;
		}
		if ( host === '1drv.ms' ) {
			return none;
		}
		return { provider: 'file', embed: url.href, id: '' };
	}
	let youtubeApi = null;
	/** Load the YouTube player API once. */
	function loadYouTube() {
		if ( window.YT?.Player ) {
			return Promise.resolve( window.YT );
		}
		if ( ! youtubeApi ) {
			youtubeApi = new Promise( ( resolve ) => {
				const previous = window.onYouTubeIframeAPIReady;
				window.onYouTubeIframeAPIReady = () => {
					previous?.();
					resolve( window.YT );
				};
				const script = document.createElement( 'script' );
				script.src = 'https://www.youtube.com/iframe_api';
				document.head.appendChild( script );
			} );
		}
		return youtubeApi;
	}
	function build( root, type, config ) {
		const instance = ++instanceId;
		root.classList.add( 'ohmylms-extended-question' );
		if (
			[
				'open-box',
				'speaking-cards',
				'flip-tiles',
				'flashcards',
			].includes( type )
		) {
			deck( root, type, config );
			return;
		}
		if (
			[ 'timed-true-false', 'whack-a-mole', 'flying-fruit' ].includes(
				type
			)
		) {
			pick( root, type, config );
			return;
		}
		if ( type === 'graphing' ) {
			graph( root, config, instance );
			return;
		}
		if ( type === 'draw' ) {
			plot( root, config, true );
			return;
		}
		if ( [ 'audio-response', 'video-response' ].includes( type ) ) {
			media( root, type, config );
			return;
		}
		if ( type === 'slide' ) {
			setAnswer( root, 'seen', '1' );
			return;
		}
		if ( [ 'word-cloud', 'discussion-board' ].includes( type ) ) {
			const input = field(
				root,
				'text',
				type === 'word-cloud'
					? __( 'Your words', 'ohmylms' )
					: __( 'Your response', 'ohmylms' ),
				type === 'discussion-board'
			);
			input.maxLength = type === 'word-cloud' ? 1000 : 20000;
			if ( type === 'word-cloud' ) {
				const cloud = el( 'div', {
					class: 'ohmylms-word-cloud',
					'aria-label': __( 'Your word cloud', 'ohmylms' ),
				} );
				root.appendChild( cloud );
				input.addEventListener( 'input', () => {
					cloud.textContent = '';
					const counts = new Map();
					input.value
						.trim()
						.split( /\s+/ )
						.filter( Boolean )
						.slice( 0, 100 )
						.forEach( ( word ) =>
							counts.set( word, ( counts.get( word ) || 0 ) + 1 )
						);
					counts.forEach( ( count, word ) =>
						cloud.appendChild(
							el( 'span', {
								text: word,
								style:
									'font-size:' +
									Math.min( 40, 16 + count * 4 ) +
									'px',
							} )
						)
					);
				} );
			}
			return;
		}
		if ( type === 'hot-text' ) {
			root.appendChild(
				el( 'p', {
					class: 'ohmylms-vis-hint',
					text: __(
						'Tap every answer that fits. Tap again to undo.',
						'ohmylms'
					),
				} )
			);
			( config.tokens || [] ).forEach( ( token ) => {
				const item = button( token.text, () => {
					const selected =
						item.getAttribute( 'aria-pressed' ) !== 'true';
					item.setAttribute( 'aria-pressed', String( selected ) );
					setAnswer( root, token.id, selected ? '1' : '' );
				} );
				item.setAttribute( 'aria-pressed', 'false' );
				root.appendChild( item );
			} );
			return;
		}
		if ( type === 'spin-wheel' ) {
			const cards = config.cards || [];
			const used = new Set();
			const colors = [
				'#7153c6',
				'#e8a317',
				'#2b8fd9',
				'#d9436a',
				'#2fb67a',
			];
			const step = 360 / Math.max( 1, cards.length );
			let turns = 0;
			const stage = el( 'div', { class: 'ohmylms-wheel-stage' } );
			const wheel = el( 'div', {
				class: 'ohmylms-spin-wheel',
				role: 'img',
				'aria-label': sprintf(
					/* translators: %d: number of cards on the wheel. */
					__( 'Wheel with %d cards', 'ohmylms' ),
					cards.length
				),
			} );
			wheel.style.background =
				'conic-gradient(' +
				cards
					.map(
						( card, index ) =>
							colors[ index % colors.length ] +
							' ' +
							index * step +
							'deg ' +
							( index + 1 ) * step +
							'deg'
					)
					.join( ',' ) +
				')';
			stage.append(
				wheel,
				el( 'span', {
					class: 'ohmylms-wheel-pointer',
					'aria-hidden': 'true',
				} )
			);
			const result = el( 'div', {
				class: 'ohmylms-spin-result',
				role: 'status',
				'aria-live': 'polite',
				text: __( 'Spin to draw a card.', 'ohmylms' ),
			} );
			const history = el( 'ol', { class: 'ohmylms-spin-history' } );
			const spin = button( __( 'Spin the wheel', 'ohmylms' ), () => {
				const left = cards.filter( ( card ) => ! used.has( card.id ) );
				if ( ! left.length ) {
					return;
				}
				const card = left[ Math.floor( Math.random() * left.length ) ];
				const index = cards.indexOf( card );
				used.add( card.id );
				turns += 1;
				// Land the pointer on the middle of the drawn card's slice.
				wheel.style.setProperty(
					'--ohmylms-spin',
					360 * ( turns * 3 ) - ( index * step + step / 2 ) + 'deg'
				);
				result.textContent = card.text;
				history.appendChild( el( 'li', { text: card.text } ) );
				setAnswer( root, 'spun', String( used.size ) );
				spin.disabled = used.size >= cards.length;
				if ( spin.disabled ) {
					spin.textContent = __( 'All cards drawn', 'ohmylms' );
				}
			} );
			spin.classList.add( 'ohmylms-activity-button' );
			root.classList.add( 'ohmylms-activity' );
			root.append( stage, spin, result, history );
			return;
		}
		if ( type === 'gameshow' ) {
			const total = Math.max( 5, Number( config.seconds ) || 20 );
			let remaining = total;
			root.classList.add( 'ohmylms-activity' );
			const clock = el( 'p', {
				class: 'ohmylms-gameshow-clock',
				role: 'timer',
				text: sprintf(
					/* translators: %d: seconds left. */
					__( '%d seconds left', 'ohmylms' ),
					remaining
				),
			} );
			const bar = el( 'div', { class: 'ohmylms-gameshow-bar' }, [
				el( 'span' ),
			] );
			root.append( clock, bar );
			const cards = ( config.choices || [] ).map( ( choice, index ) => {
				const card = el( 'button', {
					type: 'button',
					class: 'ohmylms-gameshow-choice',
					'aria-pressed': 'false',
				} );
				card.append(
					el( 'span', {
						class: 'ohmylms-gameshow-key',
						text: String.fromCharCode( 65 + index ),
					} ),
					el( 'span', { text: choice.text } )
				);
				card.addEventListener( 'click', () => {
					cards.forEach( ( other ) =>
						other.setAttribute(
							'aria-pressed',
							String( other === card )
						)
					);
					setAnswer( root, 'choice', choice.id );
				} );
				root.appendChild( card );
				return card;
			} );
			const startTimer = () => {
				const timer = window.setInterval( () => {
					if ( ! root.isConnected ) {
						window.clearInterval( timer );
						return;
					}
					remaining -= 1;
					bar.firstChild.style.width =
						( remaining / total ) * 100 + '%';
					if ( remaining <= 0 ) {
						window.clearInterval( timer );
						cards.forEach( ( card ) => {
							card.disabled = true;
						} );
						clock.textContent = __( 'Time is up', 'ohmylms' );
						return;
					}
					clock.textContent = sprintf(
						/* translators: %d: seconds left. */
						__( '%d seconds left', 'ohmylms' ),
						remaining
					);
				}, 1000 );
			};
			bar.firstChild.style.width = '100%';
			// The clock runs from the moment the learner can see the question.
			if ( 'IntersectionObserver' in window ) {
				const watcher = new window.IntersectionObserver(
					( entries ) => {
						if (
							entries.some( ( entry ) => entry.isIntersecting )
						) {
							watcher.disconnect();
							startTimer();
						}
					}
				);
				watcher.observe( root );
			} else {
				startTimer();
			}
			return;
		}
		if ( type === 'hangman' ) {
			const max = Math.max( 1, Number( config.max_misses ) || 6 );
			const guessed = [];
			root.classList.add( 'ohmylms-activity' );
			const shape = el( 'p', {
				class: 'ohmylms-hangman-shape',
				'aria-label': __( 'Word to guess', 'ohmylms' ),
				text: ( config.shape || '' ).split( '' ).join( ' ' ),
			} );
			const status = el( 'p', {
				role: 'status',
				'aria-live': 'polite',
				text: sprintf(
					/* translators: %d: wrong letters allowed. */
					__(
						'Pick the letters you think are in the word. More than %d wrong letters fails the question.',
						'ohmylms'
					),
					max
				),
			} );
			const keyboard = el( 'div', {
				class: 'ohmylms-hangman-keys',
				role: 'group',
				'aria-label': __( 'Letters', 'ohmylms' ),
			} );
			const keys = new Map();
			const guess = ( letter ) => {
				if ( guessed.includes( letter ) ) {
					return;
				}
				guessed.push( letter );
				const key = keys.get( letter );
				if ( key ) {
					key.setAttribute( 'aria-pressed', 'true' );
					key.disabled = true;
				}
				setAnswer( root, 'guesses', guessed.join( '' ) );
				status.textContent = sprintf(
					/* translators: %s: letters chosen so far. */
					__( 'Chosen: %s', 'ohmylms' ),
					guessed.join( ' ' )
				);
			};
			const alphabets = [
				[ __( 'Latin', 'ohmylms' ), 'abcdefghijklmnopqrstuvwxyz' ],
				[
					__( 'Cyrillic', 'ohmylms' ),
					'абвгдеёжзийклмноөпрстуүфхцчшщъыьэюя',
				],
			];
			const fill = ( letters ) => {
				keyboard.textContent = '';
				keys.clear();
				[ ...letters ].forEach( ( letter ) => {
					const key = button( letter.toUpperCase(), () =>
						guess( letter )
					);
					key.setAttribute(
						'aria-pressed',
						String( guessed.includes( letter ) )
					);
					key.disabled = guessed.includes( letter );
					keys.set( letter, key );
					keyboard.appendChild( key );
				} );
			};
			const tabs = el( 'div', { class: 'ohmylms-hangman-tabs' } );
			alphabets.forEach( ( [ label, letters ], index ) => {
				const tab = button( label, () => {
					tabs.querySelectorAll( 'button' ).forEach( ( other ) =>
						other.setAttribute(
							'aria-pressed',
							String( other === tab )
						)
					);
					fill( letters );
				} );
				tab.setAttribute( 'aria-pressed', String( index === 0 ) );
				tabs.appendChild( tab );
			} );
			fill( alphabets[ 0 ][ 1 ] );
			root.append( shape, status, tabs, keyboard );
			return;
		}
		if ( type === 'poll' ) {
			( config.choices || [] ).forEach( ( choice ) => {
				const label = el( 'label', {
						class: 'ohmylms-preview-choice',
					} ),
					input = el( 'input', {
						type: 'radio',
						name: 'ohmylms-poll-' + instance,
					} );
				input.addEventListener( 'change', () => {
					root.querySelectorAll( 'input[type="radio"]' ).forEach(
						( option ) => {
							option.checked = option === input;
						}
					);
					setAnswer( root, 'choice', choice.id );
				} );
				label.append( input, document.createTextNode( choice.text ) );
				root.appendChild( label );
			} );
			return;
		}
		if ( type === 'passage' ) {
			root.appendChild(
				el( 'div', {
					class: 'ohmylms-passage',
					text: config.passage || '',
				} )
			);
			( config.parts || [] ).forEach( ( part ) => {
				const input = field(
					root,
					part.id,
					( part.label || '' ) + ' ' + ( part.prompt || '' ),
					part.kind === 'written'
				);
				if ( part.kind === 'numerical' ) {
					input.type = 'number';
				}
			} );
			return;
		}
		if ( type === 'match-table-grid' ) {
			root.appendChild(
				el( 'p', {
					class: 'ohmylms-vis-hint',
					text: config.multiple
						? __(
								'Tick every column that fits each row.',
								'ohmylms'
							)
						: __( 'Pick one column for each row.', 'ohmylms' ),
				} )
			);
			const table = el( 'table', { class: 'ohmylms-match-grid' } ),
				head = el( 'tr', {}, [
					el( 'th', { text: __( 'Item', 'ohmylms' ) } ),
				] );
			( config.columns || [] ).forEach( ( column ) =>
				head.appendChild(
					el( 'th', { text: column.label, scope: 'col' } )
				)
			);
			table.appendChild( el( 'thead', {}, [ head ] ) );
			const body = el( 'tbody' );
			( config.rows || [] ).forEach( ( row ) => {
				const tr = el( 'tr', {}, [
					el( 'th', { text: row.label, scope: 'row' } ),
				] );
				( config.columns || [] ).forEach( ( column ) => {
					const input = el( 'input', {
						type: config.multiple ? 'checkbox' : 'radio',
						'data-grid-column': column.id,
						name: 'ohmylms-grid-' + instance + '-' + row.id,
						'aria-label': row.label + ' — ' + column.label,
					} );
					input.addEventListener( 'change', () => {
						if ( config.multiple ) {
							const selected = Array.from(
								tr.querySelectorAll( 'input:checked' )
							).map( ( item ) =>
								item.getAttribute( 'data-grid-column' )
							);
							setAnswer(
								root,
								row.id,
								selected.length
									? JSON.stringify( selected )
									: null
							);
						} else {
							setAnswer( root, row.id, column.id );
						}
					} );
					tr.appendChild( el( 'td', {}, [ input ] ) );
				} );
				body.appendChild( tr );
			} );
			table.appendChild( body );
			root.appendChild( table );
			return;
		}
		if ( [ 'labeling', 'hotspot' ].includes( type ) ) {
			// The stage hugs the image so marker percentages line up with the picture, whatever its size.
			const box = el( 'div', { class: 'ohmylms-diagram' } ),
				stage = el( 'div', { class: 'ohmylms-diagram-stage' } ),
				image = el( 'img', {
					src: config.image_url || '',
					alt: __( 'Question diagram', 'ohmylms' ),
				} );
			stage.appendChild( image );
			box.appendChild( stage );
			// A larger view of the picture to answer on, closed with the button or Escape.
			const enlarge = button(
				__( 'View image full screen', 'ohmylms' ),
				() =>
					setFull(
						! box.classList.contains( 'ohmylms-diagram-full' )
					)
			);
			enlarge.classList.add( 'ohmylms-diagram-enlarge' );
			const closeFull = button(
				__( 'Close full screen', 'ohmylms' ),
				() => setFull( false )
			);
			closeFull.classList.add( 'ohmylms-diagram-close' );
			box.appendChild( closeFull );
			const fit = () => {
				if ( ! box.classList.contains( 'ohmylms-diagram-full' ) ) {
					image.style.width = '';
					image.style.height = '';
					return;
				}
				const width = image.naturalWidth || 1;
				const height = image.naturalHeight || 1;
				const scale = Math.min(
					( window.innerWidth - 32 ) / width,
					( window.innerHeight -
						( type === 'labeling' ? 300 : 96 ) ) /
						height,
					4
				);
				image.style.width = Math.round( width * scale ) + 'px';
				image.style.height = Math.round( height * scale ) + 'px';
			};
			const onKey = ( event ) => {
				if ( event.key === 'Escape' ) {
					setFull( false );
				} else if ( event.key === 'Tab' && type === 'labeling' ) {
					const controls = Array.from(
						box.querySelectorAll( 'button:not(:disabled), select' )
					).filter( ( node ) => node.getClientRects().length );
					const index = controls.indexOf( document.activeElement );
					if ( event.shiftKey && index <= 0 ) {
						event.preventDefault();
						controls.at( -1 )?.focus();
					} else if (
						! event.shiftKey &&
						index === controls.length - 1
					) {
						event.preventDefault();
						controls[ 0 ]?.focus();
					}
				}
			};
			function setFull( on ) {
				box.classList.toggle( 'ohmylms-diagram-full', on );
				document.body.classList.toggle( 'ohmylms-diagram-open', on );
				if ( on ) {
					box.setAttribute( 'role', 'dialog' );
					box.setAttribute( 'aria-modal', 'true' );
					box.setAttribute(
						'aria-label',
						__( 'Question diagram', 'ohmylms' )
					);
					window.addEventListener( 'keydown', onKey );
					window.addEventListener( 'resize', fit );
					closeFull.focus();
				} else {
					box.removeAttribute( 'role' );
					box.removeAttribute( 'aria-modal' );
					window.removeEventListener( 'keydown', onKey );
					window.removeEventListener( 'resize', fit );
					enlarge.focus();
				}
				fit();
			}
			root.appendChild( box );
			root.appendChild( enlarge );
			if ( type === 'labeling' ) {
				( config.targets || [] ).forEach( ( target ) => {
					let input = Array.from(
						root.querySelectorAll( 'input[data-answer-key]' )
					).find(
						( answerField ) =>
							answerField.dataset.answerKey === target.id
					);
					if ( ! input ) {
						input = el( 'input', {
							type: 'hidden',
							'data-answer-key': target.id,
							name: root.dataset.namePrefix
								? `${ root.dataset.namePrefix }[${ target.id }]`
								: null,
						} );
						root.appendChild( input );
					}

					const select = el( 'select', {
						'aria-label':
							__( 'Diagram label', 'ohmylms' ) + ' ' + target.id,
						style:
							'left:' +
							Number( target.x ) +
							'%;top:' +
							Number( target.y ) +
							'%',
					} );
					select.appendChild(
						el( 'option', {
							value: '',
							text: __( 'Choose label', 'ohmylms' ),
						} )
					);
					( config.choices || [] ).forEach( ( choice ) =>
						select.appendChild(
							el( 'option', { value: choice, text: choice } )
						)
					);
					select.value = input.value;
					select.addEventListener( 'change', () => {
						input.value = select.value;
						root.dataset.touched = '1';
						input.dispatchEvent(
							new Event( 'change', { bubbles: true } )
						);
					} );
					stage.appendChild( select );
				} );
				import( diagramModule.href )
					.then( ( { buildDiagramLabels } ) => {
						if ( root.isConnected ) {
							buildDiagramLabels( root, stage, config );
						}
					} )
					.catch( () => {
						// The original label menus remain usable if the enhancement cannot load.
					} );
			} else {
				const pin = el( 'span', {
					class: 'ohmylms-hotspot-pin',
					text: '●',
				} );
				const inputs = {};
				const place = ( x, y, fromInput ) => {
					const round = ( value ) =>
						Math.round(
							Math.max( 0, Math.min( 100, value ) ) * 10
						) / 10;
					x = round( x );
					y = round( y );
					setAnswer( root, 'x', String( x ) );
					setAnswer( root, 'y', String( y ) );
					pin.style.left = x + '%';
					pin.style.top = y + '%';
					stage.appendChild( pin );
					if ( ! fromInput ) {
						inputs.x.value = x;
						inputs.y.value = y;
					}
				};
				image.addEventListener( 'click', ( event ) => {
					const bounds = image.getBoundingClientRect();
					place(
						( ( event.clientX - bounds.left ) / bounds.width ) *
							100,
						( ( event.clientY - bounds.top ) / bounds.height ) * 100
					);
				} );
				const x = field(
						root,
						'x',
						__( 'Horizontal position (%)', 'ohmylms' )
					),
					y = field(
						root,
						'y',
						__( 'Vertical position (%)', 'ohmylms' )
					);
				inputs.x = x;
				inputs.y = y;
				[ x, y ].forEach( ( input ) => {
					input.type = 'number';
					input.min = 0;
					input.max = 100;
					input.step = 'any';
					input.addEventListener( 'input', () => {
						if ( x.value !== '' && y.value !== '' ) {
							place( Number( x.value ), Number( y.value ), true );
						}
					} );
				} );
			}
			return;
		}
		if ( type === 'interactive-video' ) {
			const source = videoSource( config.video_url );
			const frame = el( 'div', { class: 'ohmylms-video-frame' } );
			let player = null;
			let seekTo = () => {};
			let onTime = () => {};
			if ( source.provider === 'file' ) {
				player = el( 'video', { controls: '', src: source.embed } );
				root.appendChild( player );
				seekTo = ( seconds ) => {
					player.currentTime = seconds;
					player.pause();
				};
				player.addEventListener( 'timeupdate', () =>
					onTime( player.currentTime, () => player.pause() )
				);
			} else if ( source.provider === 'youtube' ) {
				root.appendChild( frame );
				const holder = el( 'div' );
				frame.appendChild( holder );
				loadYouTube().then( ( YT ) => {
					const yt = new YT.Player( holder, {
						videoId: source.id,
						playerVars: { rel: 0, modestbranding: 1 },
					} );
					seekTo = ( seconds ) => {
						yt.seekTo( seconds, true );
						yt.pauseVideo();
					};
					// The YouTube player has no time event, so its clock is read while it plays.
					setInterval( () => {
						if (
							yt.getPlayerState?.() === YT.PlayerState.PLAYING
						) {
							onTime( yt.getCurrentTime(), () =>
								yt.pauseVideo()
							);
						}
					}, 250 );
				} );
			} else if ( [ 'drive', 'onedrive' ].includes( source.provider ) ) {
				// These hosts cannot report playback time, so checkpoints stay open while watching.
				const iframe = el( 'iframe', {
					src: source.embed,
					title: __( 'Video', 'ohmylms' ),
					allow: 'autoplay; fullscreen',
					allowfullscreen: '',
					referrerpolicy: 'no-referrer',
					sandbox:
						'allow-scripts allow-same-origin allow-presentation allow-popups',
					loading: 'lazy',
				} );
				frame.appendChild( iframe );
				root.appendChild( frame );
			} else {
				root.appendChild(
					el( 'p', {
						role: 'alert',
						text: __(
							'This video link cannot be played.',
							'ohmylms'
						),
					} )
				);
			}
			const timed =
				source.provider === 'file' || source.provider === 'youtube';
			const reached = new Set();
			const checkpoints = [];
			( config.checkpoints || [] ).forEach( ( checkpoint ) => {
				const panel = el(
					'fieldset',
					{ class: 'ohmylms-video-checkpoint' },
					[
						el( 'legend', {
							text: timed
								? checkpoint.at + 's · ' + checkpoint.prompt
								: checkpoint.prompt,
						} ),
					]
				);
				const input = field( root, checkpoint.id, checkpoint.prompt );
				panel.appendChild( input.parentElement );
				if ( timed ) {
					panel.appendChild(
						button( __( 'Jump to checkpoint', 'ohmylms' ), () =>
							seekTo( Number( checkpoint.at ) )
						)
					);
				}
				root.appendChild( panel );
				checkpoints.push( { checkpoint, panel, input } );
			} );
			// Pause when playback reaches a checkpoint, once per pass.
			onTime = ( now, pause ) => {
				checkpoints.forEach( ( { checkpoint, panel, input } ) => {
					const at = Number( checkpoint.at );
					if ( now < at - 1 ) {
						reached.delete( checkpoint.id );
					} else if (
						now >= at &&
						now < at + 1 &&
						! reached.has( checkpoint.id )
					) {
						reached.add( checkpoint.id );
						pause();
						panel.scrollIntoView( { block: 'nearest' } );
						input.focus( { preventScroll: true } );
					}
				} );
			};
		}
	}
	types.forEach( ( type ) =>
		api.register( type, {
			build: ( root, config ) => build( root, type, config ),
		} )
	);
	api.enhance( document );
} )();
