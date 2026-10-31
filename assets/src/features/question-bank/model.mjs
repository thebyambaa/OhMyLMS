import {
	EXTENDED_TYPES,
	isExtendedType,
	isUngradedType,
	extendedDefaults,
	extendedIssues,
} from '../question-editor/extendedModel.mjs';
/** Pure helpers for the question bank, skills and picker UIs. */
import {
	promptText,
	questionPrompt,
	questionPromptPatch,
	statementKey,
} from '../question-editor/questionPrompt.mjs';
import {
	INTERACTIVE_TYPES,
	interactiveDefaults,
	interactiveIssues,
	isInteractiveType,
} from '../question-editor/interactiveModel.mjs';
import { hasTemplate } from '../question-editor/templateModel.mjs';
export const DIFFICULTIES = [ 'easy', 'standard', 'challenge' ];
export const STATUSES = [ 'draft', 'approved', 'archived' ];

/**
 * Drop empty filters so URLs and requests stay stable.
 * @param filters
 */
export function cleanFilters( filters ) {
	const result = {};
	for ( const [ key, value ] of Object.entries( filters || {} ) ) {
		if (
			value !== '' &&
			value !== null &&
			value !== undefined &&
			value !== false
		) {
			result[ key ] = value;
		}
	}
	return result;
}

/**
 * One-line status for a bank row.
 * @param item
 */
export function versionLabel( item ) {
	if ( ! item?.version ) {
		return '';
	}
	if ( ! item.approved_version_id ) {
		return `v${ item.version } · draft`;
	}
	return item.approved_is_current
		? `v${ item.version } · approved`
		: `v${ item.version } · newer than approved`;
}

/**
 * Build a parent/child tree from a flat skill list; unknown parents become roots.
 * @param skills
 */
export function skillTree( skills ) {
	const byId = new Map(
		( skills || [] ).map( ( skill ) => [
			skill.id,
			{
				...skill,
				children: [],
			},
		] )
	);
	const roots = [];
	for ( const skill of byId.values() ) {
		const parent = skill.parent && byId.get( skill.parent );
		if ( parent ) {
			parent.children.push( skill );
		} else {
			roots.push( skill );
		}
	}
	const sort = ( list ) => {
		list.sort( ( left, right ) => left.name.localeCompare( right.name ) );
		list.forEach( ( skill ) => sort( skill.children ) );
		return list;
	};
	return sort( roots );
}

/**
 * Flatten a tree with depth for indented selects.
 * @param tree
 * @param depth
 */
export function flattenTree( tree, depth = 0 ) {
	return tree.flatMap( ( skill ) => [
		{
			...skill,
			depth,
		},
		...flattenTree( skill.children, depth + 1 ),
	] );
}

/**
 * Would making `candidate` a prerequisite of `skillId` create a cycle?
 * Mirrors the server check so the UI can disable invalid choices.
 * @param skills
 * @param skillId
 * @param candidate
 */
export function createsCycle( skills, skillId, candidate ) {
	if ( skillId === candidate ) {
		return true;
	}
	const byId = new Map(
		( skills || [] ).map( ( skill ) => [ skill.id, skill ] )
	);
	const seen = new Set();
	const stack = [ candidate ];
	while ( stack.length ) {
		const current = stack.pop();
		if ( current === skillId ) {
			return true;
		}
		if ( seen.has( current ) ) {
			continue;
		}
		seen.add( current );
		stack.push( ...( byId.get( current )?.prerequisites || [] ) );
	}
	return false;
}

/**
 * Normalize a part => roles skill map, removing empty parts and duplicate supporting skills.
 * @param map
 */
export function normalizeSkillMap( map ) {
	const result = {};
	for ( const [ part, roles ] of Object.entries( map || {} ) ) {
		const primary = Number( roles?.primary ) || 0;
		const supporting = [
			...new Set( ( roles?.supporting || [] ).map( Number ) ),
		].filter( ( id ) => id > 0 && id !== primary );
		if ( primary || supporting.length ) {
			result[ part ] = {
				primary,
				supporting,
			};
		}
	}
	return result;
}
export function setPrimarySkill( map, part, skillId ) {
	const current = map?.[ part ] || {
		primary: 0,
		supporting: [],
	};
	return normalizeSkillMap( {
		...map,
		[ part ]: {
			...current,
			primary: Number( skillId ) || 0,
		},
	} );
}
export function toggleSupportingSkill( map, part, skillId ) {
	const current = map?.[ part ] || {
		primary: 0,
		supporting: [],
	};
	const id = Number( skillId );
	const supporting = current.supporting.includes( id )
		? current.supporting.filter( ( value ) => value !== id )
		: [ ...current.supporting, id ];
	return normalizeSkillMap( {
		...map,
		[ part ]: {
			...current,
			supporting,
		},
	} );
}

/**
 * Questions already in the quiz cannot be added again.
 * @param items
 * @param existingIds
 */
export function selectableResults( items, existingIds ) {
	const existing = new Set( ( existingIds || [] ).map( Number ) );
	return ( items || [] ).map( ( item ) => ( {
		...item,
		alreadyInQuiz: existing.has( Number( item.id ) ),
	} ) );
}

/**
 * Append newly linked server questions to the editor list without touching local edits.
 * @param current
 * @param serverContent
 * @param addedIds
 */
export function appendLinkedQuestions( current, serverContent, addedIds ) {
	const present = new Set(
		( current || [] ).map( ( question ) => Number( question.id ) )
	);
	const added = new Set( ( addedIds || [] ).map( Number ) );
	const extra = ( serverContent || [] ).filter(
		( question ) =>
			added.has( Number( question.id ) ) &&
			! present.has( Number( question.id ) )
	);
	return [ ...( current || [] ), ...extra ];
}

/**
 * Numerical editor helpers.
 * @param values
 */
export const numbersToList = ( values ) =>
	Array.isArray( values ) ? values.join( ', ' ) : '';
export function listToNumbers( text ) {
	const numbers = String( text || '' )
		.split( ',' )
		.map( ( item ) => item.trim() )
		.filter( ( item ) => item !== '' && Number.isFinite( Number( item ) ) )
		.map( Number );
	return numbers.length ? numbers : undefined;
}

/**
 * Structured-part helpers; part IDs are stable once created.
 * @param parts
 */
export function addPart( parts ) {
	const used = new Set( ( parts || [] ).map( ( part ) => part.id ) );
	let index = ( parts || [] ).length;
	let id = String.fromCharCode( 97 + ( index % 26 ) );
	while ( used.has( id ) ) {
		id = `p${ ++index }`;
	}
	return [
		...( parts || [] ),
		{
			id,
			label: `(${ id })`,
			kind: 'written',
			marks: 1,
			prompt: '',
		},
	];
}
export function updatePart( parts, index, fields ) {
	return ( parts || [] ).map( ( part, position ) =>
		position === index
			? {
					...part,
					...fields,
				}
			: part
	);
}
export function removePart( parts, index ) {
	return ( parts || [] ).filter( ( _, position ) => position !== index );
}

/** Types the bank's New question form can write; others are authored inside a quiz. */
export const NEW_QUESTION_TYPES = [
	'single-choice',
	'multiple-choice',
	'true-false',
	'short-text',
	'long-text',
	'fill-in-the-blank',
	'statement',
	'reorder',
	'matching',
	'numerical',
	'structured',
	...INTERACTIVE_TYPES,
	...EXTENDED_TYPES.map( ( [ type ] ) => type ),
];
const CHOICE_TYPES = [ 'single-choice', 'multiple-choice' ];

/**
 * A blank draft for the New question form.
 * @param type
 */
export function emptyDraft( type = 'single-choice' ) {
	let settings = interactiveDefaults( type );
	if ( type === 'structured' ) {
		settings = {
			parts: addPart( [] ),
		};
	} else if ( isExtendedType( type ) ) {
		settings = extendedDefaults( type );
	}
	return {
		type,
		name: '',
		description: '',
		marks: isUngradedType( type ) ? 0 : 1,
		options: ( () => {
			if ( type === 'true-false' ) {
				return [
					{
						answer: 'True',
						correct: true,
					},
					{
						answer: 'False',
						correct: false,
					},
				];
			}
			if ( [ ...CHOICE_TYPES, 'reorder', 'matching' ].includes( type ) ) {
				return [
					{
						answer: '',
						correct: true,
					},
					{
						answer: '',
						correct: false,
					},
				];
			}
			return [];
		} )(),
		settings,
	};
}

/**
 * Change the draft's type, keeping the shared fields.
 * @param draft
 * @param type
 */
export function changeDraftType( draft, type ) {
	const blank = emptyDraft( type );
	const keepOptions =
		CHOICE_TYPES.includes( type ) && CHOICE_TYPES.includes( draft.type );
	return {
		...blank,
		name: ( () => {
			if ( draft.settings?.question_code ) {
				if ( type === 'fill-in-the-blank' ) {
					return promptText(
						statementKey( draft )
							? questionPrompt( draft )
							: draft.description
					);
				}
				return 'Question 1';
			}
			return draft.name;
		} )(),
		description: statementKey( draft )
			? questionPrompt( draft )
			: draft.description,
		marks: isUngradedType( type ) ? 0 : draft.marks,
		settings: {
			...blank.settings,
			...( draft.settings?.question_code
				? {
						question_code: draft.settings.question_code,
					}
				: {} ),
		},
		options: keepOptions ? draft.options : blank.options,
		...( statementKey( draft )
			? questionPromptPatch(
					{
						...draft,
						settings: {
							...blank.settings,
							type,
							question_code: draft.settings?.question_code,
						},
					},
					questionPrompt( draft )
				)
			: {} ),
	};
}

/**
 * Mark one option correct (single choice, true/false) or toggle it (multiple choice).
 * @param draft
 * @param index
 * @param correct
 */
export function setOptionCorrect( draft, index, correct ) {
	const single = draft.type !== 'multiple-choice';
	return {
		...draft,
		options: draft.options.map( ( option, position ) => ( {
			...option,
			correct: ( () => {
				if ( position === index ) {
					return correct;
				}
				if ( single ) {
					return false;
				}
				return option.correct;
			} )(),
		} ) ),
	};
}

/**
 * Problems that block saving, as message keys for the form.
 * @param draft
 */
export function validateDraft( draft ) {
	const problems = [];
	if ( ! String( draft.name || '' ).trim() ) {
		problems.push( 'name' );
	} else if (
		draft.settings?.question_code &&
		! promptText( questionPrompt( draft ) )
	) {
		problems.push( 'name' );
	}
	if ( ! ( Number( draft.marks ) >= 0 ) ) {
		problems.push( 'marks' );
	}
	if ( CHOICE_TYPES.includes( draft.type ) || draft.type === 'true-false' ) {
		if ( draft.options.length < 2 ) {
			problems.push( 'options-count' );
		}
		if (
			draft.options.some(
				( option ) => ! String( option.answer || '' ).trim()
			)
		) {
			problems.push( 'options-empty' );
		}
		const correct = draft.options.filter(
			( option ) => option.correct
		).length;
		if ( draft.type === 'multiple-choice' ? correct < 1 : correct !== 1 ) {
			problems.push( 'options-correct' );
		}
	}
	if ( draft.type === 'numerical' && ! hasTemplate( draft.settings ) ) {
		const answers = [
			draft.settings.answer,
			...( draft.settings.answers || [] ),
		].filter(
			( value ) => value !== undefined && value !== null && value !== ''
		);
		if (
			! answers.length ||
			answers.some( ( value ) => ! Number.isFinite( Number( value ) ) )
		) {
			problems.push( 'numerical-answer' );
		}
	}
	if (
		[ 'structured', 'passage' ].includes( draft.type ) &&
		! ( draft.settings.parts || [] ).length
	) {
		problems.push( 'parts' );
	}
	if (
		isInteractiveType( draft.type ) &&
		interactiveIssues( draft.type, draft.settings ).length
	) {
		problems.push( 'interactive' );
	}
	if ( [ 'matching', 'reorder' ].includes( draft.type ) ) {
		if ( draft.options.length < 2 ) {
			problems.push( 'options-count' );
		}
		if (
			draft.options.some(
				( option ) =>
					! String( option.answer || '' ).trim() ||
					( draft.type === 'matching' &&
						! option.matching_data?.label?.trim() )
			)
		) {
			problems.push( 'options-empty' );
		}
	}
	if (
		isExtendedType( draft.type ) &&
		extendedIssues( draft.type, draft.settings ).length
	) {
		problems.push( 'extended' );
	}
	return problems;
}

/**
 * Question payload for a bank question (no quiz); `createBankQuestion()` sends it to POST /questions.
 * @param draft
 */
export function draftToPayload( draft ) {
	const options = [
		...CHOICE_TYPES,
		'true-false',
		'reorder',
		'matching',
		'fill-in-the-blank',
	].includes( draft.type );
	return {
		name: String( draft.name ).trim(),
		description: draft.description || '',
		settings: {
			...draft.settings,
			type: draft.type,
			required: !! draft.settings.required,
			// A structured question is worth the sum of its parts.
			score: {
				enabled: true,
				value:
					draft.type === 'structured'
						? ( draft.settings.parts || [] ).reduce(
								( sum, part ) =>
									sum + ( Number( part.marks ) || 0 ),
								0
							)
						: Number( draft.marks ) || 0,
			},
		},
		questions: options
			? draft.options.map( ( option, index ) => ( {
					...( option.thumbnail_id
						? {
								thumbnail_id: option.thumbnail_id,
								image_url: option.image_url,
							}
						: {} ),
					...( option.matching_data
						? {
								matching_data: option.matching_data,
							}
						: {} ),
					answer: String( option.answer ).trim(),
					is_correct: option.correct ? 1 : 0,
					order_number: index + 1,
				} ) )
			: [],
	};
}
