import { createElement, lazy } from '@wordpress/element';
import type { ComponentType } from 'react';
import type { ExtensionRegistry } from './registry.mjs';
import {
	registerCoreQuestionEditors,
	type CoreQuestionEditorComponents,
} from '../../features/question-editor/registerCoreEditors';

/** Exports of the lazily loaded editor module that the built-in editors use. */
export const CORE_EDITOR_COMPONENTS = [
	'BankAnswerFields',
	'NumericalEditor',
	'StructuredEditor',
	'DropdownBlanksEditor',
	'CategorizeEditor',
	'MultiBlankEditor',
	'BuildExpressionEditor',
	'ExpressionEditor',
	'NumberLineEditor',
	'ShadeModelEditor',
	'CountBlocksEditor',
	'SetClockEditor',
	'MakeAmountEditor',
	'FillLevelEditor',
	'BuildChartEditor',
	'GridBuildEditor',
] as const;

type ComponentName = ( typeof CORE_EDITOR_COMPONENTS )[ number ];

/** Loads the module with every built-in editor component. */
export type EditorModuleLoader = () => Promise<
	Partial< Record< ComponentName, ComponentType< any > > >
>;

/** The built-in editors, in their own chunk (`chunks/admin-app-question-editors.*.js`). */
export const loadCoreEditorModule: EditorModuleLoader = () =>
	import(
		/* webpackChunkName: "admin-app-question-editors" */ '../../features/question-editor/coreEditorComponents'
	);

/**
 * Stand-ins for the editor components that load the module on first render. Until it has loaded
 * they suspend (render them inside `Suspense`); afterwards they render the real component directly,
 * so editors that render other editors (structured parts) do not suspend again.
 *
 * @param load Loads the editor module; called once. A failed load fails every editor until reload.
 * @return Components by export name.
 */
export function lazyEditorComponents(
	load: EditorModuleLoader
): CoreQuestionEditorComponents {
	let loaded: Awaited< ReturnType< EditorModuleLoader > > | null = null;
	let pending: ReturnType< EditorModuleLoader > | null = null;
	const loadOnce = () => {
		pending ??= load().then( ( module ) => {
			loaded = module;
			return module;
		} );
		return pending;
	};
	return Object.fromEntries(
		CORE_EDITOR_COMPONENTS.map( ( name ) => {
			const Deferred = lazy( async () => {
				const component = ( await loadOnce() )[ name ];
				if ( ! component ) {
					throw new Error(
						`Missing question editor component ${ name }.`
					);
				}
				return { default: component };
			} );
			function Editor( props: Record< string, unknown > ) {
				return createElement( loaded?.[ name ] ?? Deferred, props );
			}
			Editor.displayName = `LazyQuestionEditor(${ name })`;
			return [ name, Editor ];
		} )
	);
}

/**
 * Register the built-in question editors (numerical, structured and the interactive types) with
 * lazily loaded components. Editors an extension registered first are kept.
 *
 * @param registry Extension registry, normally the published host.
 * @param load     Loads the editor module; the `admin-app-question-editors` chunk by default.
 * @return IDs registered by this call.
 */
export function registerAppQuestionEditors(
	registry: ExtensionRegistry,
	load: EditorModuleLoader = loadCoreEditorModule
): string[] {
	return registerCoreQuestionEditors(
		registry,
		lazyEditorComponents( load )
	);
}
