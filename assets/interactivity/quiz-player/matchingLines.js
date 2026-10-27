const COLORS = [
	'#f08a6a',
	'#d9534f',
	'#1cb8d6',
	'#8e6bd8',
	'#58cc02',
	'#f5a623',
];
const NS = 'http://www.w3.org/2000/svg';
const attached = new WeakSet();

/**
 * Draws a coloured line from each matched term to the definition it was placed on.
 * The hidden answer fields stay the source of truth; this only reads the DOM and
 * redraws whenever a pair is placed, cleared, restored or the layout changes.
 * @param {HTMLElement} root The .quiz-matching-options element.
 * @return {() => void} Cleanup.
 */
export function attachMatchingLines( root ) {
	if ( ! root || attached.has( root ) ) {
		return () => {};
	}
	attached.add( root );
	const svg = document.createElementNS( NS, 'svg' );
	svg.setAttribute( 'class', 'ohmylms-match-lines' );
	svg.setAttribute( 'aria-hidden', 'true' );
	root.prepend( svg );

	let frame = 0;
	const draw = () => {
		frame = 0;
		const base = root.getBoundingClientRect();
		const terms = [
			...root.querySelectorAll( '.quiz-match-items .matching-option' ),
		];
		terms.forEach( ( term ) => {
			term.classList.remove( 'is-linked' );
			term.style.removeProperty( '--pair-color' );
		} );
		svg.setAttribute( 'width', base.width );
		svg.setAttribute( 'height', base.height );
		svg.textContent = '';
		root.querySelectorAll( '.matching-definition' ).forEach(
			( definition ) => {
				const box = definition.querySelector( '.option-drop-box' );
				const input = definition.querySelector(
					'.matching-answer-input'
				);
				const linked = box?.querySelector( '.dropped' ) && input?.value;
				definition.classList.toggle( 'is-linked', Boolean( linked ) );
				definition.style.removeProperty( '--pair-color' );
				if ( ! linked ) {
					return;
				}
				const index = terms.findIndex(
					( term ) => term.dataset.optionId === input.value
				);
				if ( index < 0 ) {
					return;
				}
				const color = COLORS[ index % COLORS.length ];
				terms[ index ].classList.add( 'is-linked' );
				terms[ index ].style.setProperty( '--pair-color', color );
				definition.style.setProperty( '--pair-color', color );
				// The grid layout shows a pair by position, so it needs no line.
				if ( root.classList.contains( 'is-grid' ) ) {
					return;
				}
				const from = terms[ index ].getBoundingClientRect();
				const to = box.getBoundingClientRect();
				const x1 = from.right - base.left;
				const y1 = from.top + from.height / 2 - base.top;
				const x2 = to.left - base.left;
				const y2 = to.top + to.height / 2 - base.top;
				const mid = ( x1 + x2 ) / 2;
				const path = document.createElementNS( NS, 'path' );
				path.setAttribute(
					'd',
					`M${ x1 } ${ y1 } C${ mid } ${ y1 } ${ mid } ${ y2 } ${ x2 } ${ y2 }`
				);
				path.setAttribute( 'stroke', color );
				svg.append( path );
			}
		);
	};
	const schedule = () => {
		if ( ! frame ) {
			frame = requestAnimationFrame( draw );
		}
	};
	const mutations = new MutationObserver( schedule );
	mutations.observe( root, { childList: true, subtree: true } );
	const resize =
		typeof ResizeObserver === 'undefined'
			? null
			: new ResizeObserver( schedule );
	resize?.observe( root );
	window.addEventListener( 'resize', schedule );
	schedule();
	return () => {
		mutations.disconnect();
		resize?.disconnect();
		window.removeEventListener( 'resize', schedule );
		cancelAnimationFrame( frame );
		svg.remove();
		attached.delete( root );
	};
}
