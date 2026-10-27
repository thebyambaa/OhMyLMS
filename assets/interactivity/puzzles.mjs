const { __, sprintf } = window.wp.i18n;

/**
 * Shared learner controls and editor preview. This receives public settings only.
 *
 * @param {HTMLElement} root Puzzle region.
 * @param {string} type Crossword or wordsearch.
 * @param {Object} config Frozen public layout.
 * @return {Function} Detach optional listeners.
 */
export function buildPuzzle( root, type, config ) {
	const doc = root.ownerDocument;
	const create = ( tag, text = '' ) => {
		const node = doc.createElement( tag );
		node.textContent = text;
		return node;
	};
	const letters = ( text ) =>
		typeof Intl.Segmenter === 'function'
			? Array.from(
					new Intl.Segmenter( undefined, {
						granularity: 'grapheme',
					} ).segment( text.normalize( 'NFC' ).toUpperCase() ),
					( part ) => part.segment
				)
			: text.toUpperCase().match( /\P{M}\p{M}*/gu ) || [];
	const size = config.size;
	const occupied =
		type === 'crossword'
			? config.cells.map( ( key ) =>
					key
						.match( /^r(\d+)c(\d+)$/ )
						.slice( 1 )
						.map( Number )
				)
			: [
					[ 0, 0 ],
					[ size - 1, size - 1 ],
				];
	const firstRow = Math.min( ...occupied.map( ( cell ) => cell[ 0 ] ) );
	const lastRow = Math.max( ...occupied.map( ( cell ) => cell[ 0 ] ) );
	const firstCol = Math.min( ...occupied.map( ( cell ) => cell[ 1 ] ) );
	const lastCol = Math.max( ...occupied.map( ( cell ) => cell[ 1 ] ) );
	const clues = config.clues || [];
	const prefix = root.dataset.namePrefix;
	const storage =
		prefix && ! root.closest( '[data-autosave]' )
			? `ohmylms:puzzle:${ window.location.pathname }${ window.location.search }:${ prefix }`
			: null;
	let cached = {};
	try {
		cached = storage
			? JSON.parse( sessionStorage.getItem( storage ) || '{}' )
			: {};
	} catch {
		/* Storage is optional. */
	}
	const old = Object.fromEntries(
		Array.from( root.querySelectorAll( '[data-answer-key]' ), ( input ) => [
			input.dataset.answerKey,
			input.value,
		] )
	);
	root.replaceChildren();
	root.classList.add( 'ohmylms-puzzle' );
	const fields = new Map();
	const cellNodes = new Map();
	const changed = ( field ) => {
		try {
			if ( storage )
				sessionStorage.setItem(
					storage,
					JSON.stringify(
						Object.fromEntries(
							[ ...fields ].map( ( [ key, node ] ) => [
								key,
								node.value,
							] )
						)
					)
				);
		} catch {
			/* Play works without storage. */
		}
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		root.dispatchEvent(
			new CustomEvent( 'ohmylms:puzzle-changed', { bubbles: true } )
		);
	};
	const field = ( key, hidden = false ) => {
		const input = create( 'input' );
		input.type = hidden ? 'hidden' : 'text';
		input.dataset.answerKey = key;
		if ( prefix ) input.name = `${ prefix }[${ key }]`;
		const initial = old[ key ] || cached?.[ key ];
		input.value =
			typeof initial === 'string' && initial.length <= 100 ? initial : '';
		input.autocomplete = 'off';
		input.spellcheck = false;
		fields.set( key, input );
		return input;
	};
	root.append(
		create(
			'p',
			type === 'crossword'
				? __(
						'Choose a word, read its clue, then type the letters.',
						'ohmylms'
					)
				: __(
						'Find the words. Drag between the first and last letters, or select both ends with clicks or the keyboard.',
						'ohmylms'
					)
		)
	);
	const toolbar = create( 'div' );
	toolbar.className = 'ohmylms-puzzle-toolbar';
	const status = create( 'p' );
	status.setAttribute( 'role', 'status' );
	const fullscreen = create( 'button', __( 'Fullscreen', 'ohmylms' ) );
	fullscreen.type = 'button';
	fullscreen.addEventListener( 'click', async () => {
		try {
			if ( doc.fullscreenElement ) await doc.exitFullscreen();
			else await root.requestFullscreen();
		} catch {
			status.textContent = __(
				'Fullscreen is unavailable in this view.',
				'ohmylms'
			);
		}
	} );
	if ( root.requestFullscreen ) toolbar.append( fullscreen );
	toolbar.append( status );
	root.append( toolbar );
	const layout = create( 'div' );
	layout.className = 'ohmylms-puzzle-layout';
	const scroll = create( 'div' );
	scroll.className = 'ohmylms-puzzle-scroll';
	const grid = create( 'div' );
	grid.className = 'ohmylms-puzzle-grid';
	grid.style.setProperty( '--puzzle-size', lastCol - firstCol + 1 );
	grid.setAttribute( 'role', 'group' );
	grid.setAttribute(
		'aria-label',
		type === 'crossword'
			? __( 'Crossword grid', 'ohmylms' )
			: __( 'Wordsearch grid', 'ohmylms' )
	);
	scroll.append( grid );
	const sidebar = create( 'div' );
	sidebar.className = 'ohmylms-puzzle-clues';
	layout.append( scroll, sidebar );
	root.append( layout );
	const keyFor = ( row, col ) => `r${ row }c${ col }`;
	const clueCells = ( clue ) =>
		Array.from( { length: clue.length }, ( _, i ) =>
			keyFor(
				clue.row + ( clue.direction === 'down' ? i : 0 ),
				clue.col + ( clue.direction === 'across' ? i : 0 )
			)
		);
	let active = clues[ 0 ];
	const selectWord = ( clue, focus = true ) => {
		active = clue;
		const cells = clueCells( clue );
		for ( const [ key, node ] of cellNodes )
			node.parentElement.classList.toggle(
				'is-active',
				cells.includes( key )
			);
		for ( const button of sidebar.querySelectorAll( '[data-clue]' ) )
			button.setAttribute(
				'aria-pressed',
				button.dataset.clue === clue.id ? 'true' : 'false'
			);
		status.textContent = `${ clue.number }. ${ clue.clue }`;
		if ( focus )
			(
				cells
					.map( ( key ) => cellNodes.get( key ) )
					.find( ( input ) => ! input.value ) ||
				cellNodes.get( cells[ 0 ] )
			)?.focus();
	};
	const move = ( key, dr, dc ) => {
		const [ , row, col ] = key.match( /^r(\d+)c(\d+)$/ );
		cellNodes
			.get( keyFor( Number( row ) + dr, Number( col ) + dc ) )
			?.focus();
	};
	let start = null;
	const paths = [];
	const selectionStatus = () => {
		status.textContent = sprintf(
			/* translators: 1: number of selections, 2: number of target words. */ __(
				'Selections: %1$d / %2$d. Submit to check your answers.',
				'ohmylms'
			),
			paths.filter( ( input ) => input.value ).length,
			clues.length
		);
	};
	let paintPaths = () => {};
	const parsePath = ( value ) => {
		const match = value.match( /^r(\d+)c(\d+):r(\d+)c(\d+)$/ );
		if ( ! match ) return [];
		const [ r1, c1, r2, c2 ] = match.slice( 1 ).map( Number );
		const length = Math.max( Math.abs( r2 - r1 ), Math.abs( c2 - c1 ) );
		if (
			length < 1 ||
			Math.max( r1, c1, r2, c2 ) >= size ||
			( r1 !== r2 &&
				c1 !== c2 &&
				Math.abs( r2 - r1 ) !== Math.abs( c2 - c1 ) )
		)
			return [];
		return Array.from( { length: length + 1 }, ( _, i ) =>
			keyFor(
				r1 + Math.sign( r2 - r1 ) * i,
				c1 + Math.sign( c2 - c1 ) * i
			)
		);
	};
	const choosePath = ( key ) => {
		if ( start === null ) {
			start = key;
			status.textContent = __(
				'Select the last letter. Escape cancels this selection.',
				'ohmylms'
			);
			paintPaths();
			return;
		}
		const value = [ start, key ].sort().join( ':' );
		start = null;
		if ( ! parsePath( value ).length )
			status.textContent = __(
				'Choose a straight line of at least two letters.',
				'ohmylms'
			);
		else if ( ! paths.some( ( input ) => input.value === value ) ) {
			const input = paths.find( ( item ) => ! item.value );
			if ( input ) {
				input.value = value;
				changed( input );
				selectionStatus();
			} else
				status.textContent = __(
					'Remove a selection before adding another.',
					'ohmylms'
				);
		} else selectionStatus();
		paintPaths();
	};
	for ( let row = firstRow; row <= lastRow; row++ )
		for ( let col = firstCol; col <= lastCol; col++ ) {
			const key = keyFor( row, col );
			const box = create( 'div' );
			box.className = 'ohmylms-puzzle-cell';
			if ( type === 'crossword' && ! config.cells.includes( key ) ) {
				box.classList.add( 'is-empty' );
				grid.append( box );
				continue;
			}
			const node =
				type === 'crossword'
					? field( key )
					: create( 'button', config.grid[ row ][ col ] );
			node.dataset.puzzleCell = key;
			node.setAttribute(
				'aria-label',
				sprintf(
					/* translators: 1: row number, 2: column number, 3: letter for wordsearch. */ __(
						'Row %1$d, column %2$d%3$s',
						'ohmylms'
					),
					row + 1,
					col + 1,
					type === 'wordsearch'
						? `, ${ config.grid[ row ][ col ] }`
						: ''
				)
			);
			if ( type === 'crossword' ) {
				node.maxLength = 100;
				const starts = clues.filter(
					( clue ) => clue.row === row && clue.col === col
				);
				if ( starts.length ) {
					const number = create( 'small', starts[ 0 ].number );
					number.setAttribute( 'aria-hidden', 'true' );
					box.append( number );
				}
				const candidates = clues.filter( ( clue ) =>
					clueCells( clue ).includes( key )
				);
				node.addEventListener( 'focus', () => {
					selectWord(
						candidates.find( ( clue ) => clue.id === active?.id ) ||
							candidates[ 0 ],
						false
					);
					node.select();
				} );
				node.addEventListener( 'click', () => {
					if ( candidates.length > 1 )
						selectWord(
							candidates.find(
								( clue ) => clue.id !== active?.id
							),
							false
						);
				} );
				const applyInput = () => {
					const value = letters( node.value );
					const cells = clueCells( active );
					const index = cells.indexOf( key );
					if ( ! value.length ) {
						changed( node );
						return;
					}
					value
						.slice( 0, cells.length - index )
						.forEach( ( letter, offset ) => {
							const target = cellNodes.get(
								cells[ index + offset ]
							);
							target.value = /^\p{L}\p{M}*$/u.test( letter )
								? letter
								: '';
							changed( target );
						} );
					cellNodes
						.get(
							cells[
								Math.min(
									cells.length - 1,
									index + value.length
								)
							]
						)
						?.focus();
				};
				node.addEventListener( 'input', ( event ) => {
					if ( event.isTrusted && ! event.isComposing ) applyInput();
				} );
				node.addEventListener( 'compositionend', applyInput );
			} else {
				node.type = 'button';
				node.addEventListener( 'click', ( event ) => {
					if ( event.detail && Date.now() < suppressClickUntil )
						return;
					choosePath( key );
				} );
			}
			node.addEventListener( 'keydown', ( event ) => {
				const arrows = {
					ArrowLeft: [ 0, -1 ],
					ArrowRight: [ 0, 1 ],
					ArrowUp: [ -1, 0 ],
					ArrowDown: [ 1, 0 ],
				};
				if ( arrows[ event.key ] ) {
					event.preventDefault();
					move( key, ...arrows[ event.key ] );
				}
				if (
					type === 'crossword' &&
					event.key === 'Backspace' &&
					! node.value
				) {
					event.preventDefault();
					const cells = clueCells( active );
					cellNodes.get( cells[ cells.indexOf( key ) - 1 ] )?.focus();
				}
				if ( event.key === 'Escape' ) {
					start = null;
					paintPaths();
					status.textContent = __(
						'Selection cancelled.',
						'ohmylms'
					);
				}
			} );
			cellNodes.set( key, node );
			box.append( node );
			grid.append( box );
		}
	let suppressClickUntil = 0;
	if ( type === 'crossword' ) {
		for ( const direction of [ 'across', 'down' ] ) {
			sidebar.append(
				create(
					'h4',
					direction === 'across'
						? __( 'Across', 'ohmylms' )
						: __( 'Down', 'ohmylms' )
				)
			);
			for ( const clue of clues
				.filter( ( item ) => item.direction === direction )
				.sort( ( a, b ) => a.number - b.number ) ) {
				const button = create(
					'button',
					`${ clue.number }. ${ clue.clue } (${ clue.length })`
				);
				button.type = 'button';
				button.dataset.clue = clue.id;
				button.addEventListener( 'click', () => selectWord( clue ) );
				sidebar.append( button );
			}
		}
		if ( active ) selectWord( active, false );
	} else {
		sidebar.append( create( 'h4', __( 'Find these words', 'ohmylms' ) ) );
		const list = create( 'ul' );
		for ( const clue of clues )
			list.append( create( 'li', `${ clue.clue } (${ clue.length })` ) );
		sidebar.append(
			list,
			create( 'h4', __( 'Your selections', 'ohmylms' ) )
		);
		const selected = create( 'div' );
		sidebar.append( selected );
		for ( let i = 0; i < clues.length; i++ ) {
			const input = field( `p${ i }`, true );
			paths.push( input );
			root.append( input );
		}
		paintPaths = () => {
			selected.replaceChildren();
			const marked = new Set(
				paths.flatMap( ( input ) => parsePath( input.value ) )
			);
			for ( const [ key, node ] of cellNodes ) {
				node.setAttribute(
					'aria-pressed',
					marked.has( key ) ? 'true' : 'false'
				);
				node.parentElement.classList.toggle(
					'is-selected',
					marked.has( key )
				);
				node.parentElement.classList.toggle(
					'is-active',
					key === start
				);
			}
			paths.forEach( ( input ) => {
				const cells = parsePath( input.value );
				if ( ! cells.length ) return;
				const text = cells
					.map( ( key ) => cellNodes.get( key ).textContent )
					.join( '' );
				const remove = create(
					'button',
					sprintf(
						/* translators: %s: selected letters. */ __(
							'Remove %s',
							'ohmylms'
						),
						text
					)
				);
				remove.type = 'button';
				remove.addEventListener( 'click', () => {
					input.value = '';
					changed( input );
					paintPaths();
					selectionStatus();
				} );
				selected.append( remove );
			} );
		};
		let pointerStart;
		grid.addEventListener( 'pointerdown', ( event ) => {
			pointerStart =
				event.target.closest( '[data-puzzle-cell]' )?.dataset
					.puzzleCell;
		} );
		grid.addEventListener( 'pointerup', ( event ) => {
			const end = doc
				.elementFromPoint( event.clientX, event.clientY )
				?.closest( '[data-puzzle-cell]' );
			if (
				pointerStart &&
				end &&
				grid.contains( end ) &&
				pointerStart !== end.dataset.puzzleCell
			) {
				start = pointerStart;
				choosePath( end.dataset.puzzleCell );
				suppressClickUntil = Date.now() + 300;
			}
			pointerStart = null;
		} );
		paintPaths();
		selectionStatus();
	}
	const form = root.closest( 'form' );
	const restored = ( event ) => {
		if ( String( event.detail?.questionId ) === root.dataset.questionId ) {
			paintPaths();
			if ( type === 'wordsearch' ) selectionStatus();
			else if ( active ) selectWord( active, false );
		}
	};
	form?.addEventListener( 'ohmylms:answer-restored', restored );
	return () =>
		form?.removeEventListener( 'ohmylms:answer-restored', restored );
}
