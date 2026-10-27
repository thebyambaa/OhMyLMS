import { PUZZLE_TYPES, puzzleDefaults, puzzleIssues } from './puzzleModel.mjs';
/** Pure helpers for the interactive question types (dropdown sentence, sorting, multi-blank, tiles). */
import { dropdownAnswers } from './dropdownModel.mjs';
export const INTERACTIVE_TYPES = [
	...PUZZLE_TYPES,
	'text-entry',
	'dropdown-blanks',
	'categorize',
	'multi-blank',
	'build-expression',
	'expression',
	'number-line',
	'shade-model',
	'count-blocks',
	'set-clock',
	'make-amount',
	'fill-level',
	'build-chart',
	'grid-build',
];

/** Answer shapes the expression engine can require. */
export const ANSWER_FORMS = [ 'any', 'expanded', 'factored', 'simplified' ];
export const isInteractiveType = ( type ) => INTERACTIVE_TYPES.includes( type );
const MARKER = /\{([a-z0-9_-]{1,20})\}/gi;

/**
 * Marker IDs such as {1} or {a} in order of first appearance.
 * @param text
 */
export function markerIds( text ) {
	const found = [];
	for ( const match of String( text || '' )
		.replace(
			/\[\[ohmylms-math:latex:(?:inline|display)\]\][\s\S]{1,2000}?\[\[\/ohmylms-math\]\]/g,
			''
		)
		.replace( /\{\{[^{}]{1,240}\}\}/g, '' )
		.matchAll( MARKER ) ) {
		if ( ! found.includes( match[ 1 ] ) ) {
			found.push( match[ 1 ] );
		}
	}
	return found;
}

/**
 * Settings a freshly chosen interactive type starts with; they already pass server validation
 * where possible so authors edit a working example.
 * @param type
 */
export function interactiveDefaults( type ) {
	if ( PUZZLE_TYPES.includes( type ) ) {
		return puzzleDefaults( type );
	}
	switch ( type ) {
		case 'text-entry':
			return {
				accepted: [ 'Answer' ],
				case_sensitive: false,
				collapse_whitespace: true,
				ignore_punctuation: false,
			};
		case 'dropdown-blanks':
			return {
				text: 'The slope is {1}, so the line {2}.',
				slots: [
					{
						id: '1',
						choices: [ 'positive', 'negative' ],
						answer: 'positive',
					},
					{
						id: '2',
						choices: [ 'rises', 'falls' ],
						answer: 'rises',
					},
				],
			};
		case 'categorize':
			return {
				buckets: [
					{
						id: 'b1',
						label: '',
					},
					{
						id: 'b2',
						label: '',
					},
				],
				items: [
					{
						id: 'i1',
						text: '',
					},
					{
						id: 'i2',
						text: '',
					},
				],
				key: {
					i1: 'b1',
					i2: 'b2',
				},
			};
		case 'multi-blank':
			return {
				layout: 'inline',
				text: '3 + 4 = {a}',
				blanks: {
					a: {
						kind: 'numerical',
						answer: 7,
					},
				},
			};
		case 'build-expression':
			return {
				correct: [],
				distractors: [],
				alternatives: [],
				equivalence: false,
				form: 'any',
			};
		case 'expression':
			return {
				answer: '2x+6',
				alternatives: [],
				form: 'any',
			};
		case 'number-line':
			return {
				min: 0,
				max: 10,
				step: 1,
				target: 5,
			};
		case 'fill-level':
			return {
				min: 0,
				max: 1000,
				step: 50,
				unit: 'ml',
				target: 500,
			};
		case 'shade-model':
			return {
				shape: 'bar',
				parts: 8,
				cols: 8,
				answer: 3,
			};
		case 'count-blocks':
			return {
				places: [ 'hundreds', 'tens', 'ones' ],
				target: 243,
				max_per_place: 20,
				canonical: false,
			};
		case 'set-clock':
			return {
				hour: 3,
				minute: 45,
				tolerance: 1,
				snap: 1,
				show_digital: false,
			};
		case 'make-amount':
			return {
				denominations: [ 5000, 1000, 500, 100 ],
				symbol: '₮',
				target: 3500,
			};
		case 'build-chart':
			return {
				categories: [
					{
						id: 'a',
						label: 'A',
					},
					{
						id: 'b',
						label: 'B',
					},
				],
				max: 10,
				step: 1,
				unit: '',
				values: {
					a: 4,
					b: 7,
				},
				show_table: true,
			};
		case 'grid-build':
			return {
				rows: 6,
				cols: 6,
				constraints: {
					area: 12,
					perimeter: 14,
					rectangle: true,
				},
				show_measures: false,
			};
		default:
			return {};
	}
}

/**
 * Keep one dropdown per marker in the sentence; new markers get an empty dropdown.
 * @param text
 * @param slots
 */
export function syncSlots( text, slots = [] ) {
	return markerIds( text ).map(
		( id ) =>
			slots.find( ( slot ) => slot.id === id ) || {
				id,
				choices: [ '', '' ],
				answer: '',
			}
	);
}

/**
 * Blank IDs of a multi-blank question: from the table cells or the inline sentence.
 * @param settings
 */
export function blankIds( settings ) {
	if ( settings.layout === 'table' ) {
		return markerIds( ( settings.rows || [] ).flat().join( ' ' ) );
	}
	return markerIds( settings.text );
}

/**
 * Keep a spec per blank; specs for deleted markers are dropped.
 * @param settings
 */
export function syncBlanks( settings ) {
	const blanks = {};
	for ( const id of blankIds( settings ) ) {
		blanks[ id ] = settings.blanks?.[ id ] || {
			kind: 'text',
			accepted: [],
		};
	}
	return blanks;
}

/**
 * Next unused ID like b3 or i4.
 * @param list
 * @param prefix
 */
export function nextId( list, prefix ) {
	const used = new Set( list.map( ( entry ) => entry.id ) );
	let n = list.length + 1;
	while ( used.has( prefix + n ) ) {
		n += 1;
	}
	return prefix + n;
}

/**
 * Remove a group; items that belonged to it become unassigned.
 * @param settings
 * @param bucketId
 */
export function removeBucket( settings, bucketId ) {
	const key = {
		...settings.key,
	};
	for ( const [ item, bucket ] of Object.entries( key ) ) {
		if ( bucket === bucketId ) {
			delete key[ item ];
		}
	}
	return {
		buckets: settings.buckets.filter(
			( bucket ) => bucket.id !== bucketId
		),
		key,
	};
}

/**
 * Remove an item together with its group assignment.
 * @param settings
 * @param itemId
 */
export function removeItem( settings, itemId ) {
	const key = {
		...settings.key,
	};
	delete key[ itemId ];
	return {
		items: settings.items.filter( ( item ) => item.id !== itemId ),
		key,
	};
}

/**
 * Tiles typed as space-separated text ("3 x + 4") become a tile list.
 * @param text
 */
export function tilesFromText( text ) {
	return String( text || '' )
		.split( /\s+/ )
		.filter( Boolean );
}

/**
 * Alternative orders, one per line.
 * @param text
 */
export function sequencesFromText( text ) {
	return String( text || '' )
		.split( /\n/ )
		.map( tilesFromText )
		.filter( ( sequence ) => sequence.length );
}

/**
 * Problems an author should fix before learners see the question.
 * Mirrors the server validation closely enough to warn early; the server stays authoritative.
 * @param type
 * @param settings
 */
export function interactiveIssues( type, settings = {} ) {
	if ( PUZZLE_TYPES.includes( type ) ) {
		return puzzleIssues( type, settings );
	}
	// A template's numbers are drawn later; the server validates real examples instead.
	if ( settings.template?.variables?.length ) {
		return [];
	}
	const issues = [];
	if (
		type === 'text-entry' &&
		( ! Array.isArray( settings.accepted ) ||
			! settings.accepted.length ||
			settings.accepted.length > 50 ||
			settings.accepted.some(
				( answer ) =>
					typeof answer !== 'string' ||
					! answer.trim() ||
					answer.length > 2000
			) )
	) {
		issues.push( 'accepted' );
	}
	if ( type === 'dropdown-blanks' ) {
		const slots = settings.slots || [];
		if ( ! markerIds( settings.text ).length || ! slots.length ) {
			issues.push( 'sentence' );
		}
		for ( const slot of slots ) {
			const choices = ( slot.choices || [] ).map( ( c ) => c.trim() );
			if (
				choices.length < 2 ||
				choices.includes( '' ) ||
				! dropdownAnswers( slot ).length ||
				dropdownAnswers( slot ).some(
					( answer ) => ! choices.includes( answer.trim() )
				) ||
				( settings.dropdown_grading_version === 2 &&
					( ! Number.isFinite( Number( slot.points ) ) ||
						Number( slot.points ) < 0 ||
						! [ 'equal', 'any', 'no-wrong' ].includes(
							slot.grading
						) ) )
			) {
				issues.push( 'choices' );
				break;
			}
		}
	}
	if ( type === 'categorize' ) {
		const buckets = settings.buckets || [];
		const items = settings.items || [];
		if (
			buckets.length < 2 ||
			buckets.some( ( bucket ) => ! ( bucket.label || '' ).trim() )
		) {
			issues.push( 'groups' );
		}
		if (
			! items.length ||
			items.some(
				( item ) => ! ( item.text || '' ).trim() && ! item.image_url
			)
		) {
			issues.push( 'items' );
		}
		if (
			items.some(
				( item ) =>
					! buckets.some(
						( bucket ) => bucket.id === settings.key?.[ item.id ]
					)
			)
		) {
			issues.push( 'assignments' );
		}
	}
	if ( type === 'multi-blank' ) {
		const ids = blankIds( settings );
		if ( ! ids.length ) {
			issues.push( 'blanks' );
		}
		for ( const id of ids ) {
			const spec = settings.blanks?.[ id ] || {};
			const answered = ( () => {
				if ( spec.kind === 'expression' ) {
					return String( spec.answer || '' ).trim() !== '';
				}
				if ( spec.kind === 'numerical' ) {
					return [ spec.answer, ...( spec.answers || [] ) ].some(
						( value ) =>
							value !== undefined &&
							value !== '' &&
							Number.isFinite( Number( value ) )
					);
				}
				return ( spec.accepted || [] ).some( ( a ) => a.trim() );
			} )();
			if ( ! answered ) {
				issues.push( 'answers' );
				break;
			}
		}
	}
	if ( type === 'build-expression' ) {
		if ( ( settings.correct || [] ).length < 2 ) {
			issues.push( 'tiles' );
		}
	}
	if ( type === 'expression' && ! String( settings.answer || '' ).trim() ) {
		issues.push( 'answer' );
	}
	issues.push( ...visualIssues( type, settings ) );
	return issues;
}
const finite = ( value ) =>
	value !== undefined &&
	value !== null &&
	value !== '' &&
	Number.isFinite( Number( value ) );
export const PLACE_VALUES = {
	thousands: 1000,
	hundreds: 100,
	tens: 10,
	ones: 1,
};

/**
 * Whole numbers from "1000, 500 ,100" (invalid and repeated entries dropped).
 * @param text
 */
export function parseAmounts( text ) {
	const seen = new Set();
	return String( text || '' )
		.split( /[\s,;]+/ )
		.map( ( part ) => Number( part ) )
		.filter(
			( n ) =>
				Number.isInteger( n ) &&
				n > 0 &&
				! seen.has( n ) &&
				seen.add( n )
		);
}

/**
 * Add a bar; the new bar gets a unique ID and a zero value.
 * @param settings
 */
export function addCategory( settings ) {
	const categories = settings.categories || [];
	const id = nextId( categories, 'c' );
	return {
		categories: [
			...categories,
			{
				id,
				label: String.fromCharCode( 65 + categories.length ),
			},
		],
		values: {
			...settings.values,
			[ id ]: 0,
		},
	};
}

/**
 * Remove a bar together with its value.
 * @param settings
 * @param id
 */
export function removeCategory( settings, id ) {
	const values = {
		...settings.values,
	};
	delete values[ id ];
	return {
		categories: ( settings.categories || [] ).filter(
			( category ) => category.id !== id
		),
		values,
	};
}

/**
 * Problems with the visual types, mirroring the server checks closely enough to warn early.
 * @param type
 * @param s
 */
export function visualIssues( type, s ) {
	const issues = [];
	if ( type === 'number-line' || type === 'fill-level' ) {
		const min = Number( s.min ?? 0 );
		const max = Number( s.max );
		const step = Number( s.step );
		if ( ! Number.isFinite( max ) || max <= min ) {
			issues.push( 'range' );
		} else if ( ! ( step > 0 ) || ( max - min ) / step > 200 ) {
			issues.push( 'step' );
		}
		if ( ! finite( s.target ) || s.target < min || s.target > max ) {
			issues.push( 'target' );
		}
	}
	if ( type === 'shade-model' ) {
		if ( ! ( s.parts >= 2 && s.parts <= 100 ) ) {
			issues.push( 'parts' );
		}
		if ( ! finite( s.answer ) || s.answer < 0 || s.answer > s.parts ) {
			issues.push( 'shaded' );
		}
	}
	if ( type === 'count-blocks' ) {
		const places = ( s.places || [] ).filter( ( p ) => PLACE_VALUES[ p ] );
		if ( ! places.length ) {
			issues.push( 'places' );
		} else if (
			! ( s.target >= 1 ) ||
			s.target % Math.min( ...places.map( ( p ) => PLACE_VALUES[ p ] ) )
		) {
			issues.push( 'target' );
		}
	}
	if ( type === 'set-clock' ) {
		if (
			! ( s.hour >= 1 && s.hour <= 12 ) ||
			! ( s.minute >= 0 && s.minute <= 59 )
		) {
			issues.push( 'time' );
		}
	}
	if ( type === 'make-amount' ) {
		const bills = s.denominations || [];
		if ( bills.length < 2 ) {
			issues.push( 'bills' );
		} else if ( ! ( s.target >= 1 ) || s.target % Math.min( ...bills ) ) {
			issues.push( 'target' );
		}
	}
	if ( type === 'build-chart' ) {
		const categories = s.categories || [];
		if (
			! categories.length ||
			categories.some( ( c ) => ! ( c.label || '' ).trim() )
		) {
			issues.push( 'bars' );
		}
		if ( ! ( s.max > 0 ) || ! ( s.step > 0 ) ) {
			issues.push( 'scale' );
		}
		if (
			categories.some(
				( c ) =>
					! finite( s.values?.[ c.id ] ) ||
					s.values[ c.id ] < 0 ||
					s.values[ c.id ] > s.max
			)
		) {
			issues.push( 'values' );
		}
	}
	if ( type === 'grid-build' ) {
		const c = s.constraints || {};
		if ( ! c.area && ! c.perimeter && ! c.rectangle && ! c.connected ) {
			issues.push( 'conditions' );
		}
		if ( c.area > ( s.rows || 0 ) * ( s.cols || 0 ) ) {
			issues.push( 'area' );
		}
	}
	return issues;
}

/**
 * Tokenize language tiles without splitting combining marks or repeated letters.
 * @param {string} text Authored answer.
 * @param {string} mode Tile mode.
 * @return {Array} Tiles in answer order.
 */
export function languageTiles( text, mode = 'words' ) {
	const value = String( text || '' ).normalize( 'NFC' );
	if ( mode !== 'letters' ) {
		return tilesFromText( value );
	}
	if ( typeof Intl.Segmenter === 'function' ) {
		return [
			...new Intl.Segmenter( undefined, {
				granularity: 'grapheme',
			} ).segment( value ),
		].map( ( part ) => part.segment );
	}
	return value.match( /\P{M}\p{M}*|\p{M}+/gu ) || [];
}
