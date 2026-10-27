/** Bounded, seeded author-side generators. Published layouts never regenerate. */
export const PUZZLE_TYPES = [ 'crossword', 'wordsearch' ];
export const DIRECTIONS = {
	across: [ 0, 1 ],
	down: [ 1, 0 ],
	'diagonal-down': [ 1, 1 ],
	'diagonal-up': [ -1, 1 ],
	left: [ 0, -1 ],
	up: [ -1, 0 ],
	'up-left': [ -1, -1 ],
	'down-left': [ 1, -1 ],
};

/**
 * @param {string} text Word.
 * @return {string[]} Written letters.
 */
export function puzzleLetters( text ) {
	const word = String( text || '' )
		.trim()
		.normalize( 'NFC' )
		.toUpperCase();
	return typeof Intl.Segmenter === 'function'
		? Array.from(
				new Intl.Segmenter( undefined, {
					granularity: 'grapheme',
				} ).segment( word ),
				( part ) => part.segment
			)
		: word.match( /\P{M}\p{M}*/gu ) || [];
}

/**
 * @param {Object} entry Positioned word.
 * @return {string[]} Cell keys.
 */
export function puzzleCells( entry ) {
	const [ dr, dc ] = DIRECTIONS[ entry.direction ] || [ 0, 1 ];
	return puzzleLetters( entry.answer ).map(
		( _, i ) => `r${ entry.row + dr * i }c${ entry.col + dc * i }`
	);
}

/**
 * @param {string[]} ends Endpoint pair.
 * @return {string} Direction-independent identity.
 */
export const puzzlePath = ( ends ) =>
	[ ends[ 0 ], ends.at( -1 ) ].sort().join( ':' );

/**
 * @param {number} seed Shuffle seed.
 * @return {() => number} Random source.
 */
function randomSource( seed ) {
	let state = Math.abs( Math.trunc( seed ) ) % 4294967296 || 1;
	return () => {
		state = ( state * 1664525 + 1013904223 ) % 4294967296;
		return state / 4294967296;
	};
}

/**
 *
 * @param {Array}        values Source.
 * @param {() => number} random Random source.
 * @return {Array} Shuffled copy.
 */
function shuffled( values, random ) {
	const result = [ ...values ];
	for ( let i = result.length - 1; i > 0; i-- ) {
		const j = Math.floor( random() * ( i + 1 ) );
		[ result[ i ], result[ j ] ] = [ result[ j ], result[ i ] ];
	}
	return result;
}

/**
 * @param {Object[]} entries Words.
 * @return {Map} Private occupied cells.
 */
function boardFor( entries ) {
	const board = new Map();
	entries.forEach( ( entry ) =>
		puzzleCells( entry ).forEach( ( key, index ) => {
			const cell = board.get( key ) || {
				letter: puzzleLetters( entry.answer )[ index ],
				directions: [],
			};
			cell.directions.push( entry.direction );
			board.set( key, cell );
		} )
	);
	return board;
}

/**
 *
 * @param {Object} entry Candidate.
 * @param {Map}    board Occupied cells.
 * @param {number} size  Size.
 * @return {number} Crossings, or -1 when invalid.
 */
function crossFit( entry, board, size ) {
	const [ dr, dc ] = DIRECTIONS[ entry.direction ];
	const letters = puzzleLetters( entry.answer );
	let hits = 0;
	if (
		board.has( `r${ entry.row - dr }c${ entry.col - dc }` ) ||
		board.has(
			`r${ entry.row + dr * letters.length }c${ entry.col + dc * letters.length }`
		)
	) {
		return -1;
	}
	for ( let i = 0; i < letters.length; i++ ) {
		const row = entry.row + dr * i,
			col = entry.col + dc * i;
		if ( row < 0 || col < 0 || row >= size || col >= size ) {
			return -1;
		}
		const cell = board.get( `r${ row }c${ col }` );
		if ( cell ) {
			if (
				cell.letter !== letters[ i ] ||
				cell.directions.includes( entry.direction )
			) {
				return -1;
			}
			hits++;
		} else if (
			[ -1, 1 ].some( ( side ) =>
				board.has( `r${ row + dc * side }c${ col + dr * side }` )
			)
		) {
			return -1;
		}
	}
	return hits;
}

/**
 *
 * @param {string} type     Puzzle type.
 * @param {Object} settings Settings.
 * @return {string[]} Author errors.
 */
export function puzzleIssues( type, settings ) {
	const { entries = [], size = 10 } = settings;
	if (
		! Number.isInteger( size ) ||
		size < 4 ||
		size > 12 ||
		! entries.length ||
		entries.length > 12
	) {
		return [ 'Use 1–12 words and a grid size from 4 to 12.' ];
	}
	const words = entries.map( ( entry ) =>
		puzzleLetters( entry.answer ).join( '' )
	);
	if (
		new Set( words ).size !== words.length ||
		( type === 'wordsearch' &&
			words.some( ( word, index ) =>
				words.some(
					( other, otherIndex ) =>
						otherIndex !== index &&
						puzzleLetters( other ).toReversed().join( '' ) === word
				)
			) ) ||
		words.some(
			( word ) =>
				! /^(?:\p{L}\p{M}*){2,12}$/u.test( word ) ||
				puzzleLetters( word ).length > size
		)
	) {
		return [
			'Use distinct words with at least two letters. Words must fit the grid and contain no spaces or punctuation.',
		];
	}
	if (
		( type === 'crossword' || settings.with_clues ) &&
		entries.some( ( entry ) => ! entry.clue?.trim() )
	) {
		return [ 'Enter a clue for every word.' ];
	}
	if (
		settings.puzzle_version !== 1 ||
		entries.some(
			( entry ) =>
				! Number.isInteger( entry.row ) ||
				! Number.isInteger( entry.col ) ||
				! DIRECTIONS[ entry.direction ]
		)
	) {
		return [ 'Generate the layout before saving.' ];
	}
	if ( type === 'crossword' ) {
		const board = new Map();
		// Validate geometry without requiring insertion order to follow the crossing graph.
		for ( const entry of entries ) {
			if (
				! [ 'across', 'down' ].includes( entry.direction ) ||
				crossFit( entry, board, size ) < 0
			) {
				return [
					'Words conflict or touch. Generate the layout again.',
				];
			}
			for ( const [ key, cell ] of boardFor( [ entry ] ) ) {
				const existing = board.get( key );
				board.set( key, {
					letter: cell.letter,
					directions: [
						...( existing?.directions || [] ),
						...cell.directions,
					],
				} );
			}
		}
		const reached = new Set( [ entries[ 0 ].id ] );
		for ( let i = 0; i < entries.length; i++ ) {
			for ( const entry of entries ) {
				if (
					entries.some(
						( other ) =>
							reached.has( other.id ) &&
							puzzleCells( entry ).some( ( cell ) =>
								puzzleCells( other ).includes( cell )
							)
					)
				) {
					reached.add( entry.id );
				}
			}
		}
		if ( reached.size !== entries.length ) {
			return [ 'All crossword words must connect.' ];
		}
	} else {
		if (
			! Array.isArray( settings.grid ) ||
			settings.grid.length !== size ||
			settings.grid.some(
				( row ) =>
					! Array.isArray( row ) ||
					row.length !== size ||
					row.some( ( letter ) => ! /^\p{L}\p{M}*$/u.test( letter ) )
			)
		) {
			return [ 'Generate a complete wordsearch grid before saving.' ];
		}
		for ( const entry of entries ) {
			const [ dr, dc ] = DIRECTIONS[ entry.direction ];
			if (
				( ! settings.allow_diagonal && dr && dc ) ||
				( ! settings.allow_backward &&
					[ 'left', 'up', 'up-left', 'down-left' ].includes(
						entry.direction
					) ) ||
				puzzleLetters( entry.answer ).some(
					( letter, index ) =>
						settings.grid[ entry.row + dr * index ]?.[
							entry.col + dc * index
						] !== letter
				)
			) {
				return [
					'Words no longer match this grid. Generate the layout again.',
				];
			}
		}
	}
	return [];
}

/**
 *
 * @param {string} type     Type.
 * @param {Object} settings Word settings.
 * @param {number} seed     Seed.
 * @return {Object} Frozen layout patch.
 */
export function generatePuzzle( type, settings, seed = 1 ) {
	const size = settings.size || 10;
	const entries = ( settings.entries || [] ).map( ( entry ) => ( {
		...entry,
		answer: puzzleLetters( entry.answer ).join( '' ),
	} ) );
	const initial = puzzleIssues( type, {
		...settings,
		entries,
		puzzle_version: 0,
	} ).filter(
		( message ) => message !== 'Generate the layout before saving.'
	);
	if ( initial.length ) {
		throw new Error( initial[ 0 ] );
	}
	const random = randomSource( seed );
	for ( let attempt = 0; attempt < 24; attempt++ ) {
		const placed = [];
		const pending = shuffled( entries, random ).sort(
			( a, b ) =>
				puzzleLetters( b.answer ).length -
				puzzleLetters( a.answer ).length
		);
		if ( type === 'crossword' ) {
			const first = pending.shift();
			placed.push( {
				...first,
				row: Math.floor( size / 2 ),
				col: Math.floor(
					( size - puzzleLetters( first.answer ).length ) / 2
				),
				direction: 'across',
			} );
			while ( pending.length ) {
				let didPlace = false;
				for ( const entry of shuffled( pending, random ) ) {
					const board = boardFor( placed );
					const candidates = [];
					for ( const [ key, cell ] of board ) {
						const [ , r, c ] = key.match( /^r(\d+)c(\d+)$/ );
						puzzleLetters( entry.answer ).forEach(
							( letter, index ) => {
								if ( cell.letter !== letter ) {
									return;
								}
								for ( const direction of [
									'across',
									'down',
								] ) {
									const [ dr, dc ] = DIRECTIONS[ direction ];
									const candidate = {
										...entry,
										row: Number( r ) - dr * index,
										col: Number( c ) - dc * index,
										direction,
									};
									if (
										crossFit( candidate, board, size ) > 0
									) {
										candidates.push( candidate );
									}
								}
							}
						);
					}
					if ( candidates.length ) {
						placed.push(
							candidates[
								Math.floor( random() * candidates.length )
							]
						);
						pending.splice(
							pending.findIndex(
								( item ) => item.id === entry.id
							),
							1
						);
						didPlace = true;
						break;
					}
				}
				if ( ! didPlace ) {
					break;
				}
			}
		} else {
			const directions = Object.keys( DIRECTIONS ).filter(
				( direction ) => {
					const [ dr, dc ] = DIRECTIONS[ direction ];
					return (
						( settings.allow_diagonal || ! dr || ! dc ) &&
						( settings.allow_backward ||
							! [ 'left', 'up', 'up-left', 'down-left' ].includes(
								direction
							) )
					);
				}
			);
			for ( const entry of pending ) {
				const board = boardFor( placed );
				const candidates = [];
				for ( let row = 0; row < size; row++ ) {
					for ( let col = 0; col < size; col++ ) {
						for ( const direction of directions ) {
							const [ dr, dc ] = DIRECTIONS[ direction ];
							if (
								puzzleLetters( entry.answer ).every(
									( letter, i ) => {
										const r = row + dr * i,
											c = col + dc * i,
											existing = board.get(
												`r${ r }c${ c }`
											);
										return (
											r >= 0 &&
											c >= 0 &&
											r < size &&
											c < size &&
											( ! existing ||
												existing.letter === letter )
										);
									}
								)
							) {
								candidates.push( {
									...entry,
									row,
									col,
									direction,
								} );
							}
						}
					}
				}
				if ( ! candidates.length ) {
					break;
				}
				placed.push(
					candidates[ Math.floor( random() * candidates.length ) ]
				);
			}
		}
		if ( placed.length !== entries.length ) {
			continue;
		}
		const board = boardFor( placed );
		const alphabet = puzzleLetters(
			settings.alphabet || 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
		);
		if (
			alphabet.length < 2 ||
			alphabet.length > 64 ||
			alphabet.some( ( letter ) => ! /^\p{L}\p{M}*$/u.test( letter ) )
		) {
			throw new Error( 'Enter an alphabet with 2 to 64 letters.' );
		}
		const grid = Array.from( { length: size }, ( _, row ) =>
			Array.from(
				{ length: size },
				( __, col ) =>
					board.get( `r${ row }c${ col }` )?.letter ||
					alphabet[ Math.floor( random() * alphabet.length ) ]
			)
		);
		const patch = {
			entries: entries.map( ( entry ) =>
				placed.find( ( item ) => item.id === entry.id )
			),
			grid: type === 'wordsearch' ? grid : [],
			puzzle_version: 1,
		};
		if ( ! puzzleIssues( type, { ...settings, ...patch } ).length ) {
			return patch;
		}
	}
	throw new Error(
		'These words could not fit. Try a larger grid, fewer words, or words with more shared letters.'
	);
}

/**
 * @param {string} type Type.
 * @return {Object} Valid initial puzzle.
 */
export function puzzleDefaults( type ) {
	const settings = {
		size: 8,
		partial_credit: true,
		with_clues: type === 'crossword',
		allow_diagonal: false,
		allow_backward: false,
		alphabet: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
		entries: [
			{ id: 'w1', answer: 'CAT', clue: 'A pet that meows' },
			{ id: 'w2', answer: 'HAT', clue: 'Something worn on your head' },
		],
	};
	return { ...settings, ...generatePuzzle( type, settings ) };
}

/**
 *
 * @param {string} type     Type.
 * @param {Object} settings Private settings.
 * @return {Object} Public preview configuration.
 */
export function publicPuzzle( type, settings ) {
	const starts = [
		...new Set(
			settings.entries.map(
				( entry ) => entry.row * settings.size + entry.col
			)
		),
	].sort( ( a, b ) => a - b );
	const clues = settings.entries.map( ( entry ) => ( {
		id: entry.id,
		clue:
			type === 'wordsearch' && ! settings.with_clues
				? puzzleLetters( entry.answer ).join( '' )
				: entry.clue,
		length: puzzleLetters( entry.answer ).length,
		...( type === 'crossword'
			? {
					row: entry.row,
					col: entry.col,
					direction: entry.direction,
					number:
						starts.indexOf(
							entry.row * settings.size + entry.col
						) + 1,
				}
			: {} ),
	} ) );
	return {
		size: settings.size,
		clues,
		...( type === 'wordsearch'
			? { grid: settings.grid }
			: {
					cells: [
						...new Set( settings.entries.flatMap( puzzleCells ) ),
					],
				} ),
	};
}
