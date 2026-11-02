import { createReduxStore, register } from '@wordpress/data';

export const PAPER_STORE = 'ohmylms/paper';

/**
 * @typedef {Object} PaperState
 * @property {boolean}                view    Whether the canvas shows sheets.
 * @property {boolean}                answers Whether the answer key is visible.
 * @property {Record<string, string>} types   Question type by block.
 * @property {Record<string, number>} marks   Marks by block.
 */

/** @type {PaperState} */
const DEFAULT_STATE = { view: false, answers: false, types: {}, marks: {} };

/**
 * Editor-only paper view state: whether the canvas shows sheets, whether the answer key is
 * visible, and each question block's type and marks, so numbering can skip unnumbered statements
 * and headers and sections can total the marks.
 * Nothing here is saved with the exam.
 */
const store = createReduxStore( PAPER_STORE, {
	reducer( state = DEFAULT_STATE, action ) {
		switch ( action.type ) {
			case 'SET_VIEW':
				return { ...state, view: action.view };
			case 'SET_ANSWERS':
				return { ...state, answers: action.answers };
			case 'SET_QUESTION':
				if (
					state.types[ action.clientId ] === action.questionType &&
					state.marks[ action.clientId ] === action.marks
				) {
					return state;
				}
				return {
					...state,
					types: {
						...state.types,
						[ action.clientId ]: action.questionType,
					},
					marks: {
						...state.marks,
						[ action.clientId ]: action.marks,
					},
				};
			default:
				return state;
		}
	},
	actions: {
		/** @param {unknown} view */
		setView: ( view ) => ( { type: 'SET_VIEW', view: Boolean( view ) } ),
		/** @param {unknown} answers */
		setAnswers: ( answers ) => ( {
			type: 'SET_ANSWERS',
			answers: Boolean( answers ),
		} ),
		/**
		 * @param {string} clientId
		 * @param {string} questionType
		 * @param {number} marks
		 */
		setQuestion: ( clientId, questionType, marks ) => ( {
			type: 'SET_QUESTION',
			clientId,
			questionType,
			marks,
		} ),
	},
	selectors: {
		/** @param {PaperState} state */
		isPaperView: ( state ) => state.view,
		/** @param {PaperState} state */
		showsAnswers: ( state ) => state.answers,
		/**
		 * @param {PaperState} state
		 * @param {string}     clientId
		 */
		getQuestionType: ( state, clientId ) => state.types[ clientId ] || '',
		/**
		 * @param {PaperState} state
		 * @param {string}     clientId
		 */
		getQuestionMarks: ( state, clientId ) => state.marks[ clientId ] || 0,
	},
} );

// The native editor bundle evaluates this module once per screen.
register( store );
