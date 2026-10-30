import type { ComponentType } from 'react';
import type { ExtensionRegistry } from '../../app/extensions/registry.mjs';

/** IDs of the built-in question types that have an editor component. Runtime is registerCoreEditors.js. */
export declare const CORE_QUESTION_EDITOR_IDS: readonly string[];

/** Editor components by export name (`NumericalEditor`, …, `BankAnswerFields`). */
export type CoreQuestionEditorComponents = Readonly<
	Record< string, ComponentType< any > >
>;

/**
 * Register the built-in question editors that are not registered yet.
 *
 * @return IDs registered by this call.
 */
export declare function registerCoreQuestionEditors(
	registry: Pick< ExtensionRegistry, 'registerQuestionEditor' > &
		Partial< Pick< ExtensionRegistry, 'get' > >,
	components: CoreQuestionEditorComponents
): string[];
