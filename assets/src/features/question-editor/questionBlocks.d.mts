/** Types for questionBlocks.mjs, used by the typed admin app and by `npm run typecheck:js`. */

export declare const QUESTION_BLOCK_PREFIX: 'ohmylms/question-';
/** `[ type ID, English label ]` of every question type that has a block. */
export declare const QUESTION_BLOCK_TYPES: [ string, string ][];
export declare function questionBlockName( type: string ): string;
export declare function isQuestionBlock( block: { name: string } ): boolean;

interface BlockLike {
	name: string;
	innerBlocks?: BlockLike[];
	[ key: string ]: any;
}
/** Blocks without question blocks, at any depth. */
export declare function publicQuestionBlocks< T extends BlockLike >(
	blocks: T[]
): T[];

/** The canonical data of a question whose type is chosen or changed. */
export interface QuestionTypePatch {
	/** Settings: `type`, `score` and the type's working starting values. */
	readonly settings?: Record< string, unknown >;
	/** Starting answer options of option types (empty answers); empty for settings types. */
	readonly questions?: readonly Record< string, unknown >[];
	readonly name?: string;
	readonly [ key: string ]: unknown;
}

/**
 * Canonical assessment data for a question of a type: the starting settings and options.
 *
 * @param question Current question (`{}` for a new one).
 * @param type     Question type ID.
 * @param seed     First temporary option ID.
 * @return `{}` when the question already has the type.
 */
export declare function questionTypePatch(
	question: Record< string, unknown >,
	type: string,
	seed?: number
): QuestionTypePatch;

/** Learner-facing preview: no correctness, expected values or teacher-only notes. */
export interface QuestionPreviewModel {
	type: string;
	/** Title text, split at fill-in-the-blank markers (`{ blank: true }`). */
	title: { text?: string; blank?: true }[];
	body: string;
	unit: string;
	options: { id: string; answer: string; imageUrl: string; match: string }[];
	interactive: Record< string, any > | null;
	parts: {
		id: string;
		label: string;
		prompt: string;
		kind: string;
		marks: number;
	}[];
}
export declare function questionPreviewModel(
	question: Record< string, any >
): QuestionPreviewModel;
/** Missing authored content (`title`, `type`, `answers`, …); empty when none. */
export declare function questionPreviewIssues(
	question: Record< string, any >
): string[];
