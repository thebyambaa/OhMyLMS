/** Types for extendedModel.mjs, used by the typed admin app and by `npm run typecheck:js`. */

/** `[ type ID, English label ]` of every extended question type. */
export declare const EXTENDED_TYPES: [ string, string ][];
/** Card decks: ungraded, content is a list of `{ text, back }` cards. */
export declare const DECK_TYPES: string[];
/** Timed answer-pick games share the gameshow choices and key. */
export declare const CHOICE_GAME_TYPES: string[];
export declare function isExtendedType( type: string ): boolean;
export declare function isSettingsAddonType(
	type: string,
	manifest?: Record< string, any >
): boolean;
export declare function isUngradedType( type: string ): boolean;
/** Types the server only accepts with zero points (`ExtendedQuestions::UNSCORED_TYPES`). */
export declare const UNSCORED_TYPES: readonly string[];

export interface VideoSource {
	/** file, youtube, drive, onedrive, onedrive-short or invalid. */
	provider: string;
	embed: string;
	id: string;
	/** Only file and youtube report the playback time. */
	timed: boolean;
}
export declare function videoSource( input: string ): VideoSource;
/** Initial settings of a new extended question. */
export declare function extendedDefaults( type: string ): Record< string, any >;
/** The public configuration learner widgets receive. */
export declare function extendedPublicView(
	type: string,
	settings: Record< string, any >
): Record< string, any >;
export declare function graphIsValid(
	settings?: Record< string, any >
): boolean;
/** Blocking authoring problems (`image`, `video`, `cards`, `choices`, …); empty when none. */
export declare function extendedIssues(
	type: string,
	settings?: Record< string, any >
): string[];
