import type { ComponentType } from 'react';

/**
 * Types shared by the question editor modules (JSDoc `import('./editorTypes').Data`).
 *
 * Question, settings, option and patch objects follow the versioned PHP assessment contracts, and
 * add-on question types extend them, so their fields are not typed here (as in questionBlocks.d.mts).
 */
export type Data = Record< string, any >;

/** Receives a partial settings or question patch. */
export type OnPatch = ( patch: Data ) => void;

/** An answer editor; props follow the public question editor API v1 (coreEditorComponents.d.ts). */
export type EditorComponent = ComponentType< any >;
