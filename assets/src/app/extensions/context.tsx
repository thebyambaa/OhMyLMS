import {
	createContext,
	useContext,
	useSyncExternalStore,
} from '@wordpress/element';
import {
	createRegistry,
	type ExtensionEntry,
	type ExtensionKind,
	type ExtensionRegistry,
} from './registry.mjs';

const ExtensionsContext =
	createContext< ExtensionRegistry >( createRegistry() );

/** Supplies the extension registry; without it components read an empty registry. */
export const ExtensionsProvider = ExtensionsContext.Provider;

/** The extension registry of the surrounding provider. */
export function useExtensionRegistry(): ExtensionRegistry {
	return useContext( ExtensionsContext );
}

/**
 * Enabled entries of one kind. Re-renders when an extension registers or unregisters.
 *
 * @param kind Extension category.
 * @return Entries in registry order.
 */
export function useExtensions( kind: ExtensionKind ): ExtensionEntry[] {
	const registry = useExtensionRegistry();
	useSyncExternalStore( registry.subscribe, registry.getRevision );
	return registry.list( kind );
}
