/** Fixed declarative exam figures; the protected original remains the fallback. */
( function () {
	let counter = 0;
	const mounted = new Map();
	const release = ( figure, state ) => {
		window.JXG.JSXGraph.freeBoard( state.board );
		state.node.remove();
		state.printed.remove();
		state.image.classList.remove( 'ohmylms-figure-original' );
		mounted.delete( figure );
	};
	const unary = {
		sin: Math.sin,
		cos: Math.cos,
		tan: Math.tan,
		sqrt: Math.sqrt,
		abs: Math.abs,
		exp: Math.exp,
		log: Math.log,
	};
	const binary = {
		'+': ( a, b ) => a + b,
		'-': ( a, b ) => a - b,
		'*': ( a, b ) => a * b,
		'/': ( a, b ) => a / b,
		'^': ( a, b ) => a ** b,
	};
	const expression = ( node, x, depth = 0 ) => {
		if ( depth > 8 ) {
			throw new Error( 'Figure expression depth' );
		}
		if ( node === 'x' ) {
			return x;
		}
		if ( typeof node === 'number' && Number.isFinite( node ) ) {
			return node;
		}
		if ( ! node || ! Array.isArray( node.args ) ) {
			throw new Error( 'Figure expression' );
		}
		const args = node.args.map( ( child ) => expression( child, x, depth + 1 ) );
		if ( Object.hasOwn( binary, node.op ) && args.length === 2 ) {
			return binary[ node.op ]( ...args );
		}
		if ( Object.hasOwn( unary, node.op ) && args.length === 1 ) {
			return unary[ node.op ]( ...args );
		}
		throw new Error( 'Unsupported figure expression' );
	};
	const mount = ( figure ) => {
		const image = figure.querySelector( 'img' );
		let board;
		let node;
		try {
			const spec = JSON.parse( figure.dataset.figureSpec );
			if ( spec.version !== 1 || ! Array.isArray( spec.bounds ) || spec.bounds.length !== 4 || ! spec.bounds.every( Number.isFinite ) || ! Array.isArray( spec.objects ) || spec.objects.length > 100 ) {
				throw new Error( 'Unsupported figure version' );
			}
			node = document.createElement( 'div' );
			node.id = `ohmylms-figure-${ ++counter }`;
			node.className = 'ohmylms-figure-board';
			node.setAttribute( 'role', 'img' );
			node.setAttribute( 'aria-label', image.alt );
			figure.insertBefore( node, image );
			board = window.JXG.JSXGraph.initBoard( node.id, {
				boundingBox: spec.bounds,
				keepAspectRatio: true,
				showNavigation: false,
				showCopyright: false,
				pan: { enabled: false },
				zoom: { enabled: false, wheel: false, pinch: false },
				keyboard: { enabled: false },
				showInfobox: false,
			} );
			const points = new Map();
			for ( const object of spec.objects ) {
				const color = object.color || '#202020';
				const options = {
					fixed: true,
					name: object.label || '',
					withLabel: !! object.label,
					strokeColor: color,
					fillColor: color,
					fillOpacity: 0,
					dash: object.dashed ? 2 : 0,
					highlight: false,
					showInfobox: false,
					borders: { strokeColor: color, highlight: false, fixed: true },
					arc: { strokeColor: color, highlight: false, fixed: true },
					label: { display: 'internal', parse: false, strokeColor: color, fixed: true, highlight: false },
				};
				if ( object.type === 'point' ) {
					points.set( object.id, board.create( 'point', [ object.x, object.y ], { ...options, size: object.marker ? 2 : 0 } ) );
				} else if ( [ 'segment', 'polygon', 'angle' ].includes( object.type ) ) {
					board.create( object.type, object.points.map( ( id ) => {
						if ( ! points.has( id ) ) {
							throw new Error( 'Missing figure point' );
						}
						return points.get( id );
					} ), options );
				} else if ( object.type === 'circle' && points.has( object.center ) ) {
					board.create( 'circle', [ points.get( object.center ), object.radius ], options );
				} else if ( object.type === 'axes' ) {
					board.create( 'axis', [ [ 0, 0 ], [ 1, 0 ] ], options );
					board.create( 'axis', [ [ 0, 0 ], [ 0, 1 ] ], options );
				} else if ( object.type === 'function' ) {
					board.create( 'functiongraph', [ ( x ) => expression( object.expression, x ) ], options );
				} else {
					throw new Error( 'Unsupported figure object' );
				}
			}
			board.update();
			const svg = node.querySelector( 'svg' );
			if ( ! svg ) {
				throw new Error( 'Missing static figure' );
			}
			const printed = document.createElement( 'div' );
			printed.className = 'ohmylms-figure-static';
			const copy = svg.cloneNode( true );
			const rect = svg.getBoundingClientRect();
			copy.setAttribute( 'viewBox', `0 0 ${ rect.width } ${ rect.height }` );
			copy.setAttribute( 'width', rect.width );
			copy.setAttribute( 'height', rect.height );
			printed.append( copy );
			figure.append( printed );
			// An accompanying reconstruction keeps the original crop visible beside it.
			if ( figure.dataset.figureDisplay !== 'accompany' ) {
				image.classList.add( 'ohmylms-figure-original' );
			}
			mounted.set( figure, { board, node, image, printed } );
		} catch {
			if ( board ) {
				window.JXG.JSXGraph.freeBoard( board );
			}
			node?.remove();
			image?.classList.remove( 'ohmylms-figure-original' );
			figure.dataset.figureFailed = 'true';
		}
	};
	const scan = () => {
		for ( const [ figure, state ] of mounted ) {
			if ( ! figure.isConnected ) {
				release( figure, state );
			}
		}
		if ( ! window.JXG ) {
			return;
		}
		document.querySelectorAll( '.ohmylms-import-figure[data-figure-spec]' ).forEach( ( figure ) => {
			if ( figure.dataset.figureSpec && ! mounted.has( figure ) && ! figure.dataset.figureFailed ) {
				mount( figure );
			}
		} );
	};
	const observer = new MutationObserver( scan );
	observer.observe( document.documentElement, { childList: true, subtree: true } );
	window.OhMyLMSFigures = { scan };
	scan();
	window.addEventListener( 'pagehide', () => {
		observer.disconnect();
		for ( const [ figure, state ] of mounted ) {
			release( figure, state );
		}
	} );
	window.addEventListener( 'pageshow', () => {
		observer.observe( document.documentElement, { childList: true, subtree: true } );
		scan();
	} );
	window.addEventListener( 'beforeprint', scan );
} )();
