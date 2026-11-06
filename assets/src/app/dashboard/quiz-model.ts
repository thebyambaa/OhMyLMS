import { __ } from '@wordpress/i18n';

export interface Choice {
	id?: number;
	text: string;
	correct: boolean;
}

export interface Question {
	id?: number;
	prompt: string;
	type: string;
	choices: Choice[];
	hint: string;
	explanation: string;
	skill: number;
	answer?: number;
}

export interface QuizBody {
	id: number;
	title: string;
	canEdit: boolean;
	settings: { pass: number; attempts: number; shuffle: boolean };
	levels: string[];
	questions: Question[];
	skills: Array< { id: number; name: string } >;
	skillIds: number[];
}

/**
 * Map the stored question type to the one the studio edits.
 *
 * @param type Stored type.
 */
export function studioType( type: string ) {
	const map: Record< string, string > = {
		'single-choice': 'single_choice',
		'multiple-choice': 'multiple_choice',
		'short-text': 'short_answer',
		'true-false': 'true_false',
		numerical: 'numerical',
	};
	return map[ type ] || type;
}

export const TYPES = [
	[ 'single_choice', __( 'Single choice', 'ohmylms' ) ],
	[ 'multiple_choice', __( 'Multiple choice', 'ohmylms' ) ],
	[ 'numerical', __( 'Numerical', 'ohmylms' ) ],
	[ 'short_answer', __( 'Short answer', 'ohmylms' ) ],
	[ 'true_false', __( 'True or false', 'ohmylms' ) ],
] as const;

/**
 * A new empty question of one type.
 *
 * @param type Studio question type.
 */
export function blank( type: string ): Question {
	if ( type === 'true_false' ) {
		return {
			prompt: '',
			type,
			choices: [
				{ text: __( 'True', 'ohmylms' ), correct: true },
				{ text: __( 'False', 'ohmylms' ), correct: false },
			],
			hint: '',
			explanation: '',
			skill: 0,
		};
	}
	if ( type === 'numerical' || type === 'short_answer' ) {
		return {
			prompt: '',
			type,
			choices: [ { text: '', correct: true } ],
			hint: '',
			explanation: '',
			skill: 0,
			answer: 0,
		};
	}
	return {
		prompt: '',
		type,
		choices: [
			{ text: '', correct: true },
			{ text: '', correct: false },
		],
		hint: '',
		explanation: '',
		skill: 0,
	};
}

/**
 * Whether a question type shows the answer tiles (the others take one typed answer).
 *
 * @param type Studio question type.
 */
export function hasTiles( type: string ) {
	return [ 'single_choice', 'multiple_choice', 'true_false' ].includes(
		type
	);
}

/**
 * Whether the studio can edit a question type. Other types (long text, fill in the blank, matching,
 * reorder and so on) are edited in the question editor and are only shown here.
 *
 * @param type Stored question type.
 */
export function isEditable( type: string ) {
	return TYPES.some( ( row ) => row[ 0 ] === studioType( type ) );
}
