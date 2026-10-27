import {
	isExtendedType,
	isSettingsAddonType,
	extendedIssues,
} from '../question-editor/extendedModel.mjs';
export { moveOption } from '../question-editor/model.mjs';
import { parseInlineBlankPrompt } from '../question-editor/inlineBlanks.mjs';
import {
	promptText,
	questionPrompt,
} from '../question-editor/questionPrompt.mjs';
/**
 * Keep saved option IDs and extension settings; remove only temporary UI IDs.
 * A temporary (unsaved or duplicated) question never sends option IDs: copied
 * option rows belong to the original question and must be created anew.
 * @param quiz
 * @param questions
 */
export function prepareQuizPayload( quiz, questions ) {
	const clean = ( item ) => {
		const copy = {
			...item,
		};
		if ( copy.temp ) {
			delete copy.temp;
			delete copy.id;
		}
		return copy;
	};
	const payload = {
		...quiz,
		content: questions.map( ( question ) => {
			const cleaned = clean( question );
			if ( question.temp ) {
				delete cleaned.modified;
			} else if ( question.modified ) {
				cleaned.base_modified = question.modified;
			}
			delete cleaned.modified;
			delete cleaned.usage;
			return {
				...cleaned,
				questions: ( question.questions || [] ).map( ( option ) => {
					const copy = clean( option );
					if ( question.temp ) {
						delete copy.id;
					}
					return copy;
				} ),
			};
		} ),
	};
	delete payload.modified;
	if ( quiz?.modified ) {
		payload.base_modified = quiz.modified;
	}
	return payload;
}
export function canLeaveQuestion( question, validate ) {
	if (
		isExtendedType( question?.settings?.type ) ||
		isSettingsAddonType( question?.settings?.type )
	) {
		return (
			!! ( question.settings.question_code
				? promptText( questionPrompt( question ) )
				: question.name?.trim() ) &&
			! extendedIssues( question.settings.type, question.settings )
				.length &&
			Number( question.settings.score?.value ?? 0 ) >= 0
		);
	}
	if (
		question?.settings?.type === 'fill-in-the-blank' &&
		parseInlineBlankPrompt( question.name ).answers.length
	) {
		const score = Number( question.settings.score?.value ?? 1 );
		return Number.isFinite( score ) && score >= 0;
	}
	return ! question?.settings?.type || validate( question ).isValid;
}
/**
 * A new form card is an independent draft, including independent answer rows.
 * @param source
 * @param id
 */
export function newQuestionCard( source = {}, id = Date.now() ) {
	return {
		id,
		temp: true,
		name: source.name || '',
		description: source.description || '',
		settings: structuredClone(
			source.settings || {
				type: 'single-choice',
				score: {
					enabled: true,
					value: 1,
				},
				required: false,
			}
		),
		questions: (
			source.questions || [
				{
					answer: '',
					is_correct: true,
				},
				{
					answer: '',
					is_correct: false,
				},
			]
		).map( ( option, index ) => {
			const copy = structuredClone( option );
			delete copy.id;
			delete copy.question_id;
			return {
				...copy,
				id: id + index + 1,
				temp: true,
				order_number: index + 1,
			};
		} ),
		...( source.image_src
			? {
					image_src: source.image_src,
					thumbnail_id: source.thumbnail_id,
				}
			: {} ),
		...( source.video_src
			? {
					video_src: source.video_src,
				}
			: {} ),
	};
}
const same = ( left, right ) =>
	JSON.stringify( left ) === JSON.stringify( right );

/**
 * Compare editable content without server timestamps or duplicated quiz content.
 * @param quiz
 * @param questions
 */
export function quizEditSnapshot( quiz, questions ) {
	const stable = ( value ) => {
		if ( Array.isArray( value ) ) {
			return value.map( stable );
		}
		if ( ! value || typeof value !== 'object' ) {
			return value;
		}
		return Object.fromEntries(
			Object.keys( value )
				.sort()
				.map( ( key ) => [ key, stable( value[ key ] ) ] )
		);
	};
	const { content, modified, saved_ids: _savedIds, ...fields } = quiz || {};
	return JSON.stringify(
		stable( {
			quiz: fields,
			questions: questions.map(
				( { modified: _modifiedLocal, usage, ...question } ) => question
			),
		} )
	);
}

/**
 * Quiz fields edited while a save was pending win over the server response.
 * @param saved
 * @param submitted
 * @param current
 */
export function mergeSavedQuiz( saved, submitted, current ) {
	const result = {
		...saved,
	};
	delete result.content;
	delete result.saved_ids;
	for ( const key of Object.keys( current || {} ) ) {
		if ( key === 'modified' ) {
			continue;
		}
		if ( ! same( current[ key ], submitted?.[ key ] ) ) {
			result[ key ] = current[ key ];
		}
	}
	return result;
}

/**
 * Combine the saved question list with edits made while the save was in flight.
 *
 * @param {Array} saved     Questions returned by the server (real IDs).
 * @param {Array} submitted The store's questions when the save began (may hold temp IDs).
 * @param {Array} current   The store's questions now.
 * @param {Array} savedIds  Server IDs for each submitted question, in submitted order.
 */
export function mergeSavedQuestions(
	saved,
	submitted,
	current,
	savedIds = []
) {
	const realId = new Map();
	submitted.forEach( ( question, index ) => {
		if (
			( ( _nullableValue ) =>
				_nullableValue !== null && _nullableValue !== void 0 )(
				savedIds[ index ]
			)
		) {
			realId.set( question.id, Number( savedIds[ index ] ) );
		}
	} );
	const byId = new Map(
		( saved || [] ).map( ( question ) => [
			Number( question.id ),
			question,
		] )
	);
	const result = [];
	for ( const question of current || [] ) {
		const id = ( () => {
			if ( realId.has( question.id ) ) {
				return realId.get( question.id );
			}
			if ( question.temp ) {
				return null;
			}
			return Number( question.id );
		} )();
		const fresh = id !== null && id !== void 0 ? byId.get( id ) : undefined;
		const before = submitted.find( ( item ) => item.id === question.id );
		if ( ! fresh ) {
			// Added after the save began, or not returned: keep the local edit.
			result.push( question );
		} else if ( before && same( before, question ) ) {
			result.push( fresh );
		} else {
			const merged = {
				...question,
				id: fresh.id,
				modified: fresh.modified,
			};
			delete merged.temp;
			result.push( merged );
		}
	}
	return result;
}

/**
 * The question a failed batch save refers to, so the editor can select it.
 * @param error
 * @param submitted
 */
export function failedQuestion( error, submitted ) {
	const first = error?.data?.errors?.[ 0 ];
	if ( ! first || ! Number.isInteger( first.index ) ) {
		return null;
	}
	return submitted[ first.index ] || null;
}
