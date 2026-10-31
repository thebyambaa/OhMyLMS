import {
	EXTENDED_TYPES,
	INTERACTIVE_TYPES,
	extendedDefaults,
	extendedIssues,
	hasTemplate,
	interactiveDefaults,
	interactiveIssues,
	isExtendedType,
	isInteractiveType,
	isUngradedType,
	promptText,
	questionPrompt,
	questionPromptPatch,
	statementKey,
} from '../question-editor/public.mjs';
/** Pure helpers for the question bank, skills and picker UIs. */
export const DIFFICULTIES = [ 'easy', 'standard', 'challenge' ];
export const STATUSES = [ 'draft', 'approved', 'archived' ];

/**
 * @typedef {Object} Skill
 * @property {number}   id              Skill ID.
 * @property {string}   name            Skill name.
 * @property {number}   [parent]        Parent skill ID; 0 or unknown for a root.
 * @property {string}   [code]          Short code.
 * @property {number[]} [prerequisites] Prerequisite skill IDs.
 */
/** @typedef {Skill & { children: SkillNode[] }} SkillNode */
/** @typedef {SkillNode & { depth: number }} FlatSkill */
/** @typedef {{ primary: number, supporting: number[] }} SkillRoles */
/** @typedef {Record<string, SkillRoles>} SkillMap Skill roles by question part. */
/**
 * @typedef {Object} DraftOption
 * @property {string}                                 answer          Answer text.
 * @property {boolean}                                correct         Whether it is a correct answer.
 * @property {number}                                 [thumbnail_id]  Image attachment ID.
 * @property {string}                                 [image_url]     Image URL.
 * @property {{ label?: string, image_url?: string }} [matching_data] Matching pair.
 */
/**
 * Question settings; their shape depends on the question type (see the question-editor models).
 * @typedef {Record<string, any>} QuestionSettings
 */
/**
 * @typedef {Object} QuestionDraft New question form state.
 * @property {string}           type        Question type.
 * @property {string}           name        Title.
 * @property {string}           description Prompt.
 * @property {number}           marks       Marks.
 * @property {DraftOption[]}    options     Answer options.
 * @property {QuestionSettings} settings    Type settings.
 */

/**
 * Drop empty filters so URLs and requests stay stable.
 * @param {Record<string, unknown>|null|undefined} filters Filters.
 * @return {Record<string, unknown>} Set filters.
 */
export function cleanFilters( filters ) {
	/** @type {Record<string, unknown>} */
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
 * @param {{ version?: number|string, approved_version_id?: number, approved_is_current?: boolean }|null|undefined} item Bank row.
 * @return {string} Status.
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
 * @param {Skill[]|null|undefined} skills Skills.
 * @return {SkillNode[]} Roots, sorted by name at every level.
 */
export function skillTree( skills ) {
	/** @type {Map<number, SkillNode>} */
	const byId = new Map(
		( skills || [] ).map( ( skill ) => [
			skill.id,
			{
				...skill,
				children: [],
			},
		] )
	);
	/** @type {SkillNode[]} */
	const roots = [];
	for ( const skill of byId.values() ) {
		const parent = skill.parent && byId.get( skill.parent );
		if ( parent ) {
			parent.children.push( skill );
		} else {
			roots.push( skill );
		}
	}
	/**
	 * @param {SkillNode[]} list Siblings.
	 * @return {SkillNode[]} The same list, sorted.
	 */
	const sort = ( list ) => {
		list.sort( ( left, right ) => left.name.localeCompare( right.name ) );
		list.forEach( ( skill ) => sort( skill.children ) );
		return list;
	};
	return sort( roots );
}

/**
 * Flatten a tree with depth for indented selects.
 * @param {SkillNode[]} tree    Tree.
 * @param {number}      [depth] Depth of the given nodes.
 * @return {FlatSkill[]} Skills in tree order.
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
 * @param {Skill[]|null|undefined} skills    Skills.
 * @param {number}                 skillId   Skill that would get the prerequisite.
 * @param {number}                 candidate Prerequisite.
 * @return {boolean} Whether it would create a cycle.
 */
export function createsCycle( skills, skillId, candidate ) {
	if ( skillId === candidate ) {
		return true;
	}
	/** @type {Map<number, Skill>} */
	const byId = new Map(
		( skills || [] ).map( ( skill ) => [ skill.id, skill ] )
	);
	const seen = new Set();
	const stack = [ candidate ];
	while ( stack.length ) {
		const current = /** @type {number} */ ( stack.pop() );
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
 * @param {Record<string, { primary?: unknown, supporting?: unknown[] }|null|undefined>|null|undefined} map Skill map as stored or edited.
 * @return {SkillMap} Normalized skill map.
 */
export function normalizeSkillMap( map ) {
	/** @type {SkillMap} */
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
/**
 * @param {SkillMap|null|undefined} map     Skill map.
 * @param {string}                  part    Question part.
 * @param {number|string}           skillId Primary skill, or empty for none.
 * @return {SkillMap} Updated skill map.
 */
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
/**
 * @param {SkillMap|null|undefined} map     Skill map.
 * @param {string}                  part    Question part.
 * @param {number|string}           skillId Supporting skill to add or remove.
 * @return {SkillMap} Updated skill map.
 */
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
 * @template {{ id?: unknown }} T
 * @param {T[]|null|undefined}               items       Bank rows.
 * @param {(number|string)[]|null|undefined} existingIds Questions already in the quiz.
 * @return {(T & { alreadyInQuiz: boolean })[]} Rows with `alreadyInQuiz`.
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
 * @template {{ id?: unknown }} T
 * @param {T[]|null|undefined}               current       Editor questions.
 * @param {T[]|null|undefined}               serverContent Server questions.
 * @param {(number|string)[]|null|undefined} addedIds      Newly linked question IDs.
 * @return {T[]} Editor questions and the new ones.
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
 * @param {unknown} values Numbers.
 * @return {string} Comma-separated list.
 */
export const numbersToList = ( values ) =>
	Array.isArray( values ) ? values.join( ', ' ) : '';
/**
 * @param {unknown} text Comma-separated list.
 * @return {number[]|undefined} Finite numbers, or undefined for none.
 */
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
 * @param {Record<string, unknown>[]|null|undefined} parts Parts.
 * @return {Record<string, unknown>[]} Parts and a new written part.
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
/**
 * @param {Record<string, unknown>[]|null|undefined} parts  Parts.
 * @param {number}                                   index  Part to change.
 * @param {Record<string, unknown>}                  fields Changed fields.
 * @return {Record<string, unknown>[]} Updated parts.
 */
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
/**
 * @param {Record<string, unknown>[]|null|undefined} parts Parts.
 * @param {number}                                   index Part to remove.
 * @return {Record<string, unknown>[]} Remaining parts.
 */
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
 * @param {string} [type] Question type.
 * @return {QuestionDraft} Draft.
 */
export function emptyDraft( type = 'single-choice' ) {
	/** @type {QuestionSettings} */
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
 * @param {QuestionDraft} draft Draft.
 * @param {string}        type  New question type.
 * @return {QuestionDraft} Draft of the new type.
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
 * @param {QuestionDraft} draft   Draft.
 * @param {number}        index   Option.
 * @param {boolean}       correct Whether it is correct.
 * @return {QuestionDraft} Updated draft.
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
 * @param {QuestionDraft} draft Draft.
 * @return {string[]} Problems.
 */
export function validateDraft( draft ) {
	/** @type {string[]} */
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
 * @param {QuestionDraft} draft Draft.
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
								(
									/** @type {number} */ sum,
									/** @type {Record<string, unknown>} */ part
								) => sum + ( Number( part.marks ) || 0 ),
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
