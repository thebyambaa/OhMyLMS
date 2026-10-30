/** Types for the part of questionBlocks.mjs the typed admin app uses. */

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
