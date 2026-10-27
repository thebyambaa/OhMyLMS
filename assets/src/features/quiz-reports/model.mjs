import { dropdownAnswers } from '../question-editor/dropdownModel.mjs';

/**
 * REST option IDs may be numbers or strings; result controls compare them strictly.
 * @param attempt
 */
export function normalizeQuizReport( attempt ) {
	if ( ! attempt?.report ) {
		return attempt;
	}
	return {
		...attempt,
		report: {
			...attempt.report,
			questions: ( attempt.report.questions || [] ).map( ( question ) => {
				const type = question.settings?.type;
				let givenAnswer = question.given_answer;
				if (
					[
						'single-choice',
						'multiple-choice',
						'true-false',
						'reorder',
					].includes( type ) &&
					Array.isArray( givenAnswer )
				) {
					givenAnswer = givenAnswer.map( String );
				} else if (
					type === 'matching' &&
					givenAnswer &&
					typeof givenAnswer === 'object'
				) {
					givenAnswer = Object.fromEntries(
						Object.entries( givenAnswer ).map(
							( [ key, value ] ) => [ key, String( value ) ]
						)
					);
				}
				return {
					...question,
					given_answer: givenAnswer,
					questions: ( question.questions || [] ).map(
						( option ) => ( {
							...option,
							id: String( option.id ),
							is_correct: String( Number( option.is_correct ) ),
						} )
					),
				};
			} ),
		},
	};
}
const list = ( values ) =>
	( () => {
		if ( Array.isArray( values ) ) {
			return values;
		}
		if ( values === undefined || values === null ) {
			return [];
		}
		return [ values ];
	} )();

/**
 * Teacher-facing description of the expected answer for numerical and other types.
 * @param question
 */
/**
 * Expected answer of the visual question types, or null for other types.
 * @param s
 */
function describeVisualExpected( s ) {
	switch ( s.type ) {
		case 'number-line':
		case 'fill-level':
			return `${ s.target }${ s.unit ? ` ${ s.unit }` : '' }`;
		case 'shade-model':
			return `${ s.answer } / ${ s.parts }`;
		case 'count-blocks':
			return String( s.target );
		case 'set-clock':
			return `${ s.hour }:${ String( s.minute ).padStart( 2, '0' ) }`;
		case 'make-amount':
			return `${ s.target } ${ s.symbol || '' }`.trim();
		case 'build-chart':
			return (
				( s.categories || [] )
					.map( ( c ) => `${ c.label } = ${ s.values?.[ c.id ] }` )
					.join( ', ' ) || '—'
			);
		case 'grid-build': {
			const c = s.constraints || {};
			return (
				[
					c.area ? `area ${ c.area }` : '',
					c.perimeter ? `perimeter ${ c.perimeter }` : '',
					c.rectangle ? 'rectangle' : '',
					c.connected ? 'connected' : '',
				]
					.filter( Boolean )
					.join( ', ' ) || '—'
			);
		}
		default:
			return null;
	}
}
export function describeExpected( question ) {
	const settings = question?.settings || {};
	if ( [ 'crossword', 'wordsearch' ].includes( settings.type ) ) {
		return ( settings.entries || [] )
			.map( ( entry ) => `${ entry.clue } = ${ entry.answer }` )
			.join( '; ' );
	}
	if ( settings.type === 'text-entry' ) {
		return ( settings.accepted || [] ).join( ' / ' );
	}
	if ( settings.type === 'graphing' ) {
		return ( settings.points || [] )
			.map( ( point ) => `(${ point.x }, ${ point.y })` )
			.join( ', ' );
	}
	if ( settings.type === 'hot-text' ) {
		return ( settings.tokens || [] )
			.filter( ( token ) =>
				( settings.correct || [] ).includes( token.id )
			)
			.map( ( token ) => token.text )
			.join( ', ' );
	}
	if ( settings.type === 'match-table-grid' ) {
		const columns = Object.fromEntries(
			( settings.columns || [] ).map( ( column ) => [
				column.id,
				column.label,
			] )
		);
		return ( settings.rows || [] )
			.map(
				( row ) =>
					`${ row.label } → ${ ( Array.isArray( settings.key?.[ row.id ] ) ? settings.key[ row.id ] : [ settings.key?.[ row.id ] ] ).map( ( id ) => columns[ id ] || '' ).join( ', ' ) }`
			)
			.join( ', ' );
	}
	if ( settings.type === 'labeling' ) {
		return ( settings.targets || [] )
			.map( ( target ) => `${ target.id }: ${ target.answer }` )
			.join( ', ' );
	}
	if ( settings.type === 'hotspot' ) {
		return ( settings.zones || [] )
			.map(
				( zone ) => `(${ zone.x }%, ${ zone.y }%), ±${ zone.radius }%`
			)
			.join( ', ' );
	}
	if ( settings.type === 'gameshow' ) {
		return (
			( settings.choices || [] ).find(
				( choice ) => choice.id === settings.correct
			)?.text || ''
		);
	}
	if ( settings.type === 'hangman' ) {
		return ( settings.accepted || [] ).join( ', ' );
	}
	if ( settings.type === 'interactive-video' ) {
		return ( settings.checkpoints || [] )
			.map(
				( checkpoint ) => `${ checkpoint.at }s: ${ checkpoint.answer }`
			)
			.join( ', ' );
	}
	if ( settings.type === 'numerical' ) {
		const answers = [
			...list( settings.answer ),
			...list( settings.answers ),
		].join( ' or ' );
		const tolerance = Number( settings.tolerance ) || 0;
		const range = tolerance
			? ` ± ${ tolerance }${ settings.tolerance_type === 'relative' ? ' (relative)' : '' }`
			: '';
		return `${ answers }${ range }${ settings.unit ? ` ${ settings.unit }` : '' }`;
	}
	if ( settings.type === 'dropdown-blanks' ) {
		return (
			( settings.slots || [] )
				.map(
					( slot ) =>
						`{${ slot.id }} = ${ dropdownAnswers( slot ).join( ', ' ) }`
				)
				.join( ', ' ) || '—'
		);
	}
	if ( settings.type === 'categorize' ) {
		const labels = Object.fromEntries(
			( settings.buckets || [] ).map( ( b ) => [ b.id, b.label ] )
		);
		return (
			( settings.items || [] )
				.map(
					( item ) =>
						`${ item.text } → ${ labels[ settings.key?.[ item.id ] ] ?? '' }`
				)
				.join( ', ' ) || '—'
		);
	}
	if ( settings.type === 'multi-blank' ) {
		return (
			Object.entries( settings.blanks || {} )
				.map(
					( [ id, spec ] ) =>
						`{${ id }} = ${ spec.kind === 'numerical' ? ( spec.answer ?? ( spec.answers || [] )[ 0 ] ?? '' ) : ( spec.accepted || [] ).join( ' / ' ) }`
				)
				.join( ', ' ) || '—'
		);
	}
	if ( settings.type === 'build-expression' ) {
		return ( settings.correct || [] ).join( ' ' ) || '—';
	}
	const visual = describeVisualExpected( settings );
	if ( visual !== null ) {
		return visual;
	}
	if ( settings.type === 'expression' ) {
		return (
			[ settings.answer, ...( settings.alternatives || [] ) ]
				.filter( Boolean )
				.join( '  or  ' ) || '—'
		);
	}
	const correct = ( question?.questions || [] ).filter(
		( option ) =>
			option.is_correct === true || String( option.is_correct ) === '1'
	);
	return correct.map( ( option ) => option.answer ).join( ', ' ) || '—';
}

/**
 * One row per structured part: learner response next to the expected answer or notes.
 * @param question
 */
export function structuredRows( question ) {
	const given =
		question?.given_answer && typeof question.given_answer === 'object'
			? question.given_answer
			: {};
	return ( question?.settings?.parts || [] ).map( ( part ) => {
		let expected = part.rubric || '';
		if ( part.kind === 'numerical' ) {
			expected = `${ [ ...list( part.answer ), ...list( part.answers ) ].join( ' or ' ) }${ Number( part.tolerance ) ? ` ± ${ part.tolerance }` : '' }${ part.unit ? ` ${ part.unit }` : '' }`;
		} else if ( part.kind === 'text' ) {
			expected = ( part.accepted || [] ).join( ' / ' );
		}
		if ( part.kind === 'question' ) {
			expected = [ describeExpected( part.question ), part.rubric ]
				.filter( Boolean )
				.join( '; ' );
		}
		const response = given[ part.id ];
		const state = question?.parts?.[ part.id ] || {};
		const edited = question?.part_marks?.[ part.id ];
		return {
			id: part.id,
			label: part.label || part.id,
			prompt: part.prompt || '',
			marks: part.marks ?? 1,
			given:
				response && typeof response === 'object'
					? JSON.stringify( response )
					: ( response ?? '' ),
			expected: expected || '—',
			max: state.max ?? null,
			awarded: edited !== undefined ? edited : ( state.awarded ?? null ),
			kind: part.kind,
		};
	} );
}

/**
 * Set one structured part's mark in the grading state. The question total becomes the
 * sum of part marks (edited ones, else the current award), and the edited parts are sent
 * to the server as part_marks.
 * @param attempt
 * @param questionId
 * @param partId
 * @param value
 */
export function setPartMark( attempt, questionId, partId, value ) {
	const questions = ( attempt?.report?.questions || [] ).map(
		( question ) => {
			if ( question.id !== questionId ) {
				return question;
			}
			const max = Number( question.parts?.[ partId ]?.max ?? Infinity );
			const mark = Math.max( 0, Math.min( max, Number( value ) || 0 ) );
			const partMarks = {
				...( question.part_marks || {} ),
				[ partId ]: mark,
			};
			const total = Object.entries( question.parts || {} ).reduce(
				( sum, [ id, part ] ) =>
					sum +
					Number(
						id in partMarks ? partMarks[ id ] : part.awarded || 0
					),
				0
			);
			return {
				...question,
				part_marks: partMarks,
				achive_mark: Math.round( total * 10000 ) / 10000,
			};
		}
	);
	return {
		...attempt,
		report: {
			...attempt.report,
			questions,
		},
	};
}
