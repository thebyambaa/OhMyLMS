import { createReduxStore, register } from '@wordpress/data';

export const PAPER_STORE = 'ohmylms/paper';

/**
 * @typedef {Object} PaperState
 * @property {boolean}                view    Whether the canvas shows sheets.
 * @property {boolean}                answers Whether the answer key is visible.
 * @property {Record<string, string>} types   Question type by block.
 * @property {Record<string, number>} marks   Marks by block.
 * @property {Record<string, string>} titles  Short plain-text statement by block.
 */

/** @type {PaperState} */
const DEFAULT_STATE = {
	view: false,
	answers: false,
	types: {},
	marks: {},
	titles: {},
};

/**
 * Editor-only paper view state: whether the canvas shows sheets, whether the answer key is
 * visible, and each question block's type, marks and short title, so numbering can skip unnumbered
 * statements, headers and sections can total the marks, and the quiz navigator can name questions.
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
					state.marks[ action.clientId ] === action.marks &&
					( state.titles[ action.clientId ] || '' ) === action.title
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
					titles: {
						...state.titles,
						[ action.clientId ]: action.title,
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
		 * @param {string} [title]
		 */
		setQuestion: ( clientId, questionType, marks, title = '' ) => ( {
			type: 'SET_QUESTION',
			clientId,
			questionType,
			marks,
			title,
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
		/**
		 * @param {PaperState} state
		 * @param {string}     clientId
		 */
		getQuestionTitle: ( state, clientId ) => state.titles[ clientId ] || '',
	},
} );

// The native editor bundle evaluates this module once per screen.
register( store );
