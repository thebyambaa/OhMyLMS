import { syncSlots, syncBlanks } from './interactiveModel.mjs';
import { dropdownSettings } from './dropdownModel.mjs';

const escape = ( text ) =>
	String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
export const promptText = ( html ) =>
	String( html || '' )
		.replace( /<br\s*\/?\s*>|<\/(?:p|div|li)>/gi, '\n' )
		.replace( /<[^>]*>/g, '' )
		.replace( /&nbsp;/g, ' ' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&quot;/g, '"' )
		.replace( /&#0?39;/g, "'" )
		.replace( /&amp;/g, '&' )
		.trim();

/**
 * Statement fields retained by the existing assessment contracts.
 * @param {Object} question Question.
 * @return {string} Settings key, or an empty string for the standard prompt.
 */
export function statementKey( question ) {
	const type = question.settings?.type || question.type;
	if ( type === 'passage' ) {
		return 'passage';
	}
	return type === 'dropdown-blanks' ||
		( type === 'multi-blank' && question.settings?.layout !== 'table' )
		? 'text'
		: '';
}

/**
 * Preserve both fields of older questions in the single prompt editor.
 * @param question
 */
export function questionPrompt( question ) {
	const key = statementKey( question );
	if ( key ) {
		const statement = question.settings?.[ key ] || '';
		const introduction = question.settings?.question_code
			? question.description || ''
			: ( question.name && question.name !== 'Untitled'
					? `<p>${ escape( question.name ) }</p>`
					: '' ) + ( question.description || '' );
		return (
			introduction +
			( statement
				? `<p>${ escape( statement ).replace( /\n/g, '<br>' ) }</p>`
				: '' )
		);
	}
	if ( question.settings?.question_code ) {
		return question.description || '';
	}
	const name =
		question.name && question.name !== 'Untitled'
			? `<p>${ escape( question.name ) }</p>`
			: '';
	return name + ( question.description || '' );
}

/**
 * The database keeps an automatic identifier; the authored prompt keeps its rich text.
 * @param question
 * @param description
 */
export function questionPromptPatch( question, description ) {
	const code =
		question.settings?.question_code || `Q-${ question.id || Date.now() }`;
	const key = statementKey( question );
	if ( key ) {
		const text = promptText( description );
		const settings = {
			...question.settings,
			question_code: code,
			[ key ]: text,
		};
		if ( settings.type === 'dropdown-blanks' ) {
			settings.slots = syncSlots( text, settings.slots );
			if ( settings.dropdown_grading_version === 2 ) {
				Object.assign(
					settings,
					dropdownSettings( settings, settings.slots )
				);
			}
		} else if ( settings.type === 'multi-blank' ) {
			settings.blanks = syncBlanks( settings );
		}
		return {
			name: `Question ${ question.order_number || 1 }`,
			description: '',
			settings,
		};
	}
	return {
		name:
			question.settings?.type === 'fill-in-the-blank'
				? promptText( description )
				: `Question ${ question.order_number || 1 }`,
		description,
		settings: {
			...question.settings,
			question_code: code,
			...( question.settings?.type === 'fill-in-the-blank'
				? { blank_mode: 'drag' }
				: {} ),
		},
	};
}

/**
 * Keep the main statement when switching between inline and table blank layouts.
 * @param {Object} question Current question.
 * @param {Object} patch    Answer setup changes.
 * @return {Object} Compatible question patch.
 */
export function questionAnswerPatch( question, patch ) {
	if (
		question.settings?.type !== 'multi-blank' ||
		! patch.settings ||
		( question.settings.layout === 'table' ) ===
			( patch.settings.layout === 'table' )
	) {
		return patch;
	}
	const content = questionPrompt( question );
	const next = {
		...question,
		...patch,
		settings: {
			...patch.settings,
			blanks: { ...question.settings.blanks, ...patch.settings.blanks },
		},
	};
	if ( patch.settings.layout === 'table' ) {
		return {
			...patch,
			description: content,
			settings: { ...next.settings, text: '' },
		};
	}
	return { ...patch, ...questionPromptPatch( next, content ) };
}
