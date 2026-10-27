import {
	questionPrompt,
	promptText,
} from '../question-editor/questionPrompt.mjs';
import { parseInlineBlankPrompt } from '../question-editor/inlineBlanks.mjs';
import {
	extendedIssues,
	isExtendedType,
} from '../question-editor/extendedModel.mjs';
import {
	interactiveIssues,
	isInteractiveType,
} from '../question-editor/interactiveModel.mjs';

/**
 * Check numerical keys before autosaving an incomplete answer field. In a randomized question an
 * answer or tolerance may be a variable or formula such as {{a*b}}; the server checks real examples.
 *
 * @param {Object}  settings  Numerical answer settings.
 * @param {boolean} templated Whether the question has variables.
 * @return {boolean} Whether the answer key and tolerance are valid.
 */
function numericalReady( settings, templated = false ) {
	const formula = ( value ) =>
		templated &&
		typeof value === 'string' &&
		/^\s*\{\{.+\}\}\s*$/.test( value );
	const finite = ( value ) =>
		formula( value ) ||
		( [ 'number', 'string' ].includes( typeof value ) &&
			String( value ).trim() !== '' &&
			Number.isFinite( Number( value ) ) );
	const answers = settings.answers ?? [ settings.answer ];
	return (
		Array.isArray( answers ) &&
		answers.length > 0 &&
		answers.every( finite ) &&
		( settings.tolerance === undefined ||
			formula( settings.tolerance ) ||
			( finite( settings.tolerance ) &&
				Number( settings.tolerance ) >= 0 ) )
	);
}

/**
 * Avoid creating incomplete bank questions while their author is still typing.
 *
 * @param {Object|null} question Private question draft.
 * @return {boolean} Whether the draft can be saved.
 */
export function choiceReadyToSave( question ) {
	if ( ! question ) {
		return false;
	}
	const prompt = questionPrompt( question );
	const type = question.settings?.type;
	const hasPrompt = Boolean(
		promptText( prompt ) || /<img\s/i.test( prompt )
	);
	if ( type === 'match-table-grid' ) {
		const { rows = [], columns = [], key = {} } = question.settings;
		const validLabels = ( items ) =>
			items.length > 0 &&
			items.every( ( item ) => item.id && promptText( item.label ) ) &&
			new Set( items.map( ( item ) => item.id ) ).size === items.length;
		return (
			hasPrompt &&
			validLabels( rows ) &&
			validLabels( columns ) &&
			Object.keys( key ).length === rows.length &&
			rows.every( ( row ) => {
				const answer = question.settings.multiple
					? key[ row.id ]
					: [ key[ row.id ] ];
				return (
					Array.isArray( answer ) &&
					answer.length > 0 &&
					new Set( answer ).size === answer.length &&
					answer.every( ( id ) =>
						columns.some( ( column ) => column.id === id )
					)
				);
			} )
		);
	}
	if ( type === 'numerical' ) {
		return (
			hasPrompt &&
			numericalReady(
				question.settings,
				Boolean( question.settings.template?.variables?.length )
			)
		);
	}
	if ( type === 'structured' ) {
		const parts = question.settings.parts || [];
		return (
			hasPrompt &&
			parts.length > 0 &&
			new Set( parts.map( ( part ) => part.id ) ).size === parts.length &&
			parts.every(
				( part ) =>
					/^[a-z0-9_-]{1,40}$/i.test( part.id || '' ) &&
					Number.isFinite( Number( part.marks ?? 1 ) ) &&
					Number( part.marks ?? 1 ) >= 0 &&
					( ( part.kind === 'question' &&
						! [ 'structured', 'passage' ].includes(
							part.question?.settings?.type
						) &&
						choiceReadyToSave( {
							...part.question,
							name: part.prompt || 'Part',
							description: '',
							settings: {
								...part.question?.settings,
								question_code: false,
							},
						} ) ) ||
						part.kind === 'written' ||
						( part.kind === 'numerical' &&
							numericalReady(
								part,
								Boolean(
									question.settings.template?.variables
										?.length
								)
							) ) ||
						( part.kind === 'text' &&
							Array.isArray( part.accepted ) &&
							part.accepted.some( ( answer ) =>
								String( answer ).trim()
							) ) )
			)
		);
	}
	if ( isExtendedType( type ) ) {
		return hasPrompt && ! extendedIssues( type, question.settings ).length;
	}
	if ( isInteractiveType( type ) ) {
		return (
			hasPrompt && ! interactiveIssues( type, question.settings ).length
		);
	}
	if ( type === 'fill-in-the-blank' ) {
		return (
			hasPrompt &&
			( parseInlineBlankPrompt( promptText( prompt ) ).answers.length >
				0 ||
				Boolean(
					question.questions?.length &&
					question.questions.every( ( option ) =>
						promptText( option.answer )
					)
				) )
		);
	}
	if ( type === 'matching' ) {
		return (
			hasPrompt &&
			question.questions?.length >= 2 &&
			question.questions.every(
				( option ) =>
					( promptText( option.answer ) ||
						option.image_url ||
						option.thumbnail_id ) &&
					( promptText( option.matching_data?.label ) ||
						option.matching_data?.image_url ||
						option.matching_data?.image_id )
			)
		);
	}
	if (
		[ 'short-text', 'long-text', 'statement' ].includes(
			question.settings?.type
		)
	) {
		return Boolean( promptText( prompt ) || /<img\s/i.test( prompt ) );
	}
	if ( windowSafeCustomType( type ) ) {
		return hasPrompt;
	}
	return (
		Boolean( promptText( prompt ) || /<img\s/i.test( prompt ) ) &&
		question.questions?.length >= 2 &&
		question.questions.every(
			( option ) =>
				promptText( option.answer ) ||
				option.image_url ||
				option.thumbnail_id
		) &&
		( question.settings?.type === 'reorder' ||
			question.questions.some( ( option ) =>
				Boolean( Number( option.is_correct ) )
			) )
	);
}

/**
 * Attach assigned option IDs without replacing edits made during a save.
 *
 * @param {Object} submitted Draft sent to the server.
 * @param {Object} current   Latest local draft.
 * @param {Object} fresh     Refreshed bank record.
 * @return {Object} Current content with canonical identities.
 */
export function reconcileChoiceSave( submitted, current, fresh ) {
	const identities = new Map(
		( submitted.questions || [] ).map( ( option, index ) => [
			option.id ?? index,
			fresh.options?.[ index ]?.id,
		] )
	);
	return {
		...current,
		id: fresh.id,
		questions: ( current.questions || [] ).map( ( option, index ) => {
			const id = identities.get( option.id ?? index );
			return id
				? {
						...option,
						clientKey: option.clientKey ?? option.id ?? index,
						id,
						temp: false,
					}
				: option;
		} ),
	};
}

/**
 * Whether a trusted extension supplies the private answer editor.
 * @param type
 */
function windowSafeCustomType( type ) {
	return (
		typeof window !== 'undefined' &&
		Boolean( window.ohmylms?.extensions?.get?.( 'question-editor', type ) )
	);
}
