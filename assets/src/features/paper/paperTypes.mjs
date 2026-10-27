/**
 * Printed forms of matching, ordering, blank, sorting, tile, expression and word puzzle
 * questions. Each form is built from the learner view of the question: the editor and the
 * answer key derive that view from the full question, while the student print page receives it
 * from the server, so the printed layout is the same either way.
 */
import {
	publicPuzzle,
	puzzleCells,
	puzzleLetters,
} from '../question-editor/puzzleModel.mjs';
import { markerIds } from '../question-editor/interactiveModel.mjs';
import { dropdownAnswers } from '../question-editor/dropdownModel.mjs';
import { VISUAL_TYPES, visualFigure } from './paperVisual.mjs';

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const letter = ( index ) =>
	LETTERS[ index ] ||
	`${ LETTERS[ Math.floor( index / 26 ) - 1 ] }${ LETTERS[ index % 26 ] }`;

/**
 * FNV-1a hash of the UTF-8 bytes of a string. OhMyLMS\Exams\PrintPaper::hash() is identical.
 *
 * @param {string} text Text.
 * @return {number} Unsigned 32-bit hash.
 */
export function fnv1a( text ) {
	let hash = 0x811c9dc5;
	for ( const byte of new TextEncoder().encode( String( text ) ) ) {
		/* eslint-disable no-bitwise -- FNV-1a is defined on 32-bit XOR and multiplication, and must equal PrintPaper::hash(). */
		hash = ( hash ^ byte ) >>> 0;
		hash = Math.imul( hash, 0x01000193 ) >>> 0;
		/* eslint-enable no-bitwise */
	}
	return hash;
}

/**
 * A fixed jumbled order for a list, the same in the editor, on the print page and on the server
 * (OhMyLMS\Exams\PrintPaper::jumble()). With avoidIdentity, a list given in answer order is never
 * printed in that order.
 *
 * @param {string[]} texts                 Item texts, in answer order when avoidIdentity is set.
 * @param {string}   seed                  Question seed.
 * @param {Object}   options               Options.
 * @param {boolean}  options.avoidIdentity Rotate an order that would match the input.
 * @return {number[]} Input indexes in printed order.
 */
export function jumble( texts, seed, { avoidIdentity = false } = {} ) {
	const hashes = texts.map( ( text ) => fnv1a( `${ seed }|${ text }` ) );
	const order = texts
		.map( ( _, index ) => index )
		.sort( ( a, b ) => hashes[ a ] - hashes[ b ] || a - b );
	if (
		avoidIdentity &&
		order.length > 1 &&
		order.every( ( value, index ) => value === index )
	) {
		order.push( order.shift() );
	}
	return order;
}

const escapeHtml = ( text ) =>
	String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );

const MARKER = /\{([a-z0-9_-]{1,20})\}/gi;

/**
 * Split text at blank markers such as {a} or {1}.
 *
 * @param {string} text Text with markers.
 * @return {Array<{text: string}|{id: string}>} Parts.
 */
export function splitMarkers( text ) {
	const parts = [];
	let offset = 0;
	for ( const match of String( text || '' ).matchAll( MARKER ) ) {
		if ( match.index > offset ) {
			parts.push( { text: text.slice( offset, match.index ) } );
		}
		parts.push( { id: match[ 1 ] } );
		offset = match.index + match[ 0 ].length;
	}
	if ( offset < String( text || '' ).length ) {
		parts.push( { text: text.slice( offset ) } );
	}
	return parts;
}

/**
 * Replace blank markers in statement HTML with numbered answer blanks.
 *
 * @param {string}                 html  Statement HTML.
 * @param {(id: string) => string} label Printed label of a blank.
 * @param {Object<string, string>} units Unit printed after each blank.
 * @return {string} Statement HTML with blanks.
 */
function blankMarkers( html, label, units = {} ) {
	return String( html ).replace(
		MARKER,
		( whole, id ) =>
			`<span class="ohmylms-print-blank">(${ escapeHtml(
				label( id )
			) })</span>${
				units[ id ] ? ` ${ escapeHtml( units[ id ] ) }` : ''
			}`
	);
}

/**
 * The learner view of an interactive question's settings, as OhMyLMS\Assessment\Interactive
 * builds it, without the server's per-delivery shuffles: printed lists are jumbled here.
 *
 * @param {string}  type     Question type.
 * @param {Object}  settings Full or already public settings.
 * @param {boolean} isPublic Whether the settings are already the learner view.
 * @return {Object} Learner view.
 */
export function learnerSettings( type, settings, isPublic ) {
	if ( isPublic ) {
		return settings;
	}
	if ( [ 'crossword', 'wordsearch' ].includes( type ) ) {
		return Array.isArray( settings.entries )
			? { ...settings, ...publicPuzzle( type, settings ) }
			: settings;
	}
	if ( type === 'build-expression' ) {
		return {
			...settings,
			tiles: [
				...( settings.correct || [] ),
				...( settings.distractors || [] ),
			].map( String ),
		};
	}
	if ( type === 'multi-blank' ) {
		const ids =
			settings.layout === 'table'
				? markerIds( ( settings.rows || [] ).flat().join( ' ' ) )
				: markerIds( settings.text );
		return {
			...settings,
			fields: Object.fromEntries(
				ids.map( ( id ) => {
					const spec = settings.blanks?.[ id ] || {};
					return [
						id,
						{
							kind: [ 'numerical', 'expression' ].includes(
								spec.kind
							)
								? spec.kind
								: 'text',
							unit: String( spec.unit || '' ),
						},
					];
				} )
			),
		};
	}
	return settings;
}

/**
 * Readable answer of one multi-blank field.
 *
 * @param {Object} spec Private blank settings.
 * @return {string} Answer.
 */
function blankAnswer( spec = {} ) {
	if ( spec.kind === 'numerical' ) {
		return String( spec.answers?.[ 0 ] ?? spec.answer ?? '' );
	}
	if ( spec.kind === 'expression' ) {
		return String( spec.answer ?? '' );
	}
	return String( spec.accepted?.[ 0 ] ?? '' );
}

/**
 * Printed form of the question types in this module.
 *
 * @param {Object}  question    Question: settings, options and public flag.
 * @param {Object}  model       Base paper model (type, prompt, marks).
 * @param {boolean} showAnswers Whether to include the answer key.
 * @param {string}  seed        Jumble seed of the question in this paper version.
 * @return {Object|null} Paper model, or null for other types.
 */
export function typedPaper(
	question,
	model,
	showAnswers,
	seed = String( question.id ?? '' )
) {
	const { type } = model;
	const isPublic = Boolean( question.public );
	const settings = learnerSettings( type, question.settings || {}, isPublic );
	const rows = [ ...( question.questions || question.options || [] ) ].sort(
		( a, b ) => ( a.order_number || 0 ) - ( b.order_number || 0 )
	);
	const answers = showAnswers && ! isPublic;
	switch ( type ) {
		case 'matching': {
			const left = rows.map( ( row, index ) => ( {
				number: index + 1,
				text: String( row.answer ?? '' ),
				image: String( row.image_url ?? '' ),
			} ) );
			// Labels in pair order; the student print page receives them already jumbled.
			const labels = isPublic
				? ( settings.match_right || [] ).map( String )
				: rows.map( ( row ) =>
						String( row.matching_data?.label ?? '' )
					);
			const order = isPublic
				? labels.map( ( _, index ) => index )
				: jumble( labels, seed, { avoidIdentity: true } );
			const paper = {
				...model,
				layout: 'matching',
				left,
				right: order.map( ( from, index ) => ( {
					letter: letter( index ),
					text: labels[ from ],
				} ) ),
			};
			if ( answers ) {
				paper.key = left.map(
					( item, index ) =>
						`${ item.number } → ${ letter( order.indexOf( index ) ) }`
				);
			}
			return paper;
		}
		case 'reorder': {
			const texts = rows.map( ( row ) => String( row.answer ?? '' ) );
			const order = isPublic
				? texts.map( ( _, index ) => index )
				: jumble( texts, seed, { avoidIdentity: true } );
			const paper = {
				...model,
				layout: 'reorder',
				items: order.map( ( from, index ) => ( {
					letter: letter( index ),
					text: texts[ from ],
					image: String( rows[ from ]?.image_url ?? '' ),
				} ) ),
			};
			if ( answers ) {
				paper.key = [
					texts
						.map( ( _, index ) => letter( order.indexOf( index ) ) )
						.join( ', ' ),
				];
			}
			return paper;
		}
		case 'fill-in-the-blank': {
			const found = [
				...String( model.prompt ).matchAll( /\{([^{}<>]+)\}/g ),
			];
			return {
				...model,
				layout: 'blanks',
				prompt: answers
					? String( model.prompt ).replace(
							/\{([^{}<>]+)\}/g,
							( whole, text ) =>
								`<span class="ohmylms-print-blank is-answered">${ text }</span>`
						)
					: model.prompt,
				...( answers
					? { key: found.map( ( match ) => match[ 1 ] ) }
					: {} ),
			};
		}
		case 'dropdown-blanks': {
			const slots = ( settings.slots || [] ).map( ( slot, index ) => {
				const choices = ( slot.choices || [] ).map( String );
				return {
					id: String( slot.id ?? '' ),
					number: index + 1,
					multiple: Boolean( slot.multiple ),
					choices: jumble( choices, `${ seed }|${ slot.id }` ).map(
						( from ) => choices[ from ]
					),
					answers: answers
						? dropdownAnswers( slot ).map( String )
						: undefined,
				};
			} );
			const numbers = Object.fromEntries(
				slots.map( ( slot ) => [ slot.id, String( slot.number ) ] )
			);
			return {
				...model,
				layout: 'dropdown',
				prompt: blankMarkers(
					model.prompt,
					( id ) => numbers[ id ] || id
				),
				slots,
				...( answers
					? {
							key: slots.map(
								( slot ) =>
									`(${ slot.number }) ${ slot.answers.join(
										', '
									) }`
							),
						}
					: {} ),
			};
		}
		case 'multi-blank': {
			const fields = settings.fields || {};
			const paper = {
				...model,
				layout: 'multi-blank',
				table:
					settings.layout === 'table'
						? {
								columns: ( settings.columns || [] ).map(
									String
								),
								rows: ( settings.rows || [] ).map( ( row ) =>
									row.map( ( cell ) =>
										splitMarkers( String( cell ) )
									)
								),
							}
						: null,
				units: Object.fromEntries(
					Object.entries( fields ).map( ( [ id, field ] ) => [
						id,
						String( field.unit || '' ),
					] )
				),
			};
			if ( settings.layout !== 'table' ) {
				paper.prompt = blankMarkers(
					model.prompt,
					( id ) => id,
					paper.units
				);
			}
			if ( answers ) {
				paper.key = Object.keys( fields ).map(
					( id ) =>
						`(${ id }) ${ blankAnswer( settings.blanks?.[ id ] ) }`
				);
			}
			return paper;
		}
		case 'categorize': {
			const items = settings.items || [];
			const order = jumble(
				items.map( ( item ) => String( item.text ?? '' ) ),
				seed
			);
			const printed = order.map( ( from, index ) => ( {
				id: String( items[ from ].id ?? '' ),
				letter: letter( index ),
				text: String( items[ from ].text ?? '' ),
				image: String( items[ from ].image_url ?? '' ),
			} ) );
			const groups = ( settings.buckets || [] ).map( ( bucket ) => ( {
				id: String( bucket.id ?? '' ),
				label: String( bucket.label ?? '' ),
			} ) );
			const paper = {
				...model,
				layout: 'categorize',
				items: printed,
				groups,
			};
			if ( answers ) {
				paper.key = groups.map(
					( group ) =>
						`${ group.label }: ${
							printed
								.filter(
									( item ) =>
										settings.key?.[ item.id ] === group.id
								)
								.map( ( item ) => item.letter )
								.join( ', ' ) || '—'
						}`
				);
			}
			return paper;
		}
		case 'build-expression': {
			const tiles = ( settings.tiles || [] ).map( String );
			return {
				...model,
				layout: 'tiles',
				tiles: jumble( tiles, seed ).map( ( from ) => tiles[ from ] ),
				...( answers
					? {
							key: [
								( settings.correct || [] )
									.map( String )
									.join( ' ' ),
							],
						}
					: {} ),
			};
		}
		case 'expression':
			return {
				...model,
				layout: 'answer-line',
				...( answers
					? { answers: [ `$${ String( settings.answer ?? '' ) }$` ] }
					: {} ),
			};
		case 'crossword': {
			const size = Number( settings.size ) || 0;
			const numbers = {};
			for ( const clue of settings.clues || [] ) {
				numbers[ `r${ clue.row }c${ clue.col }` ] = clue.number;
			}
			const letters = {};
			if ( answers ) {
				for ( const entry of settings.entries || [] ) {
					const word = puzzleLetters( entry.answer );
					puzzleCells( entry ).forEach( ( cell, index ) => {
						letters[ cell ] = word[ index ];
					} );
				}
			}
			const clueList = ( direction ) =>
				( settings.clues || [] )
					.filter( ( clue ) => clue.direction === direction )
					.sort( ( a, b ) => a.number - b.number )
					.map( ( clue ) => ( {
						number: clue.number,
						text: String( clue.clue ?? '' ),
						length: clue.length,
					} ) );
			return {
				...model,
				layout: 'crossword',
				size,
				cells: new Set( settings.cells || [] ),
				numbers,
				letters,
				across: clueList( 'across' ),
				down: clueList( 'down' ),
			};
		}
		case 'wordsearch': {
			const marked = new Set(
				answers ? ( settings.entries || [] ).flatMap( puzzleCells ) : []
			);
			return {
				...model,
				layout: 'wordsearch',
				grid: ( settings.grid || [] ).map( ( row ) =>
					row.map( String )
				),
				marked,
				words: ( settings.clues || [] ).map( ( clue ) =>
					String( clue.clue ?? '' )
				),
			};
		}
		default:
			return VISUAL_TYPES.includes( type )
				? {
						...model,
						layout: 'visual',
						figure: visualFigure( type, settings, answers ),
					}
				: null;
	}
}
