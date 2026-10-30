import { createElement, createRoot } from '@wordpress/element';
import type { ReactElement } from 'react';
import {
	createRegistry,
	type ExtensionContext,
	type ExtensionKind,
	type ExtensionRegistry,
} from '../../extensions/registry.mjs';
import * as api from './api';
import { ExtensionSlot } from './ExtensionSlot';
import { ExtensionsProvider } from './context';

/** `window.ohmylms.extensions`: the registry plus rendering helpers (docs/EXTENSIONS.md, API v1). */
export interface ExtensionHost extends ExtensionRegistry {
	readonly api: typeof api;
	/** A React element rendering one slot, for use inside another React tree. */
	readonly renderSlot: (
		name: string,
		context?: ExtensionContext,
		kind?: ExtensionKind
	) => ReactElement;
	/** A React element rendering one registered editor, or null when it is missing or disabled. */
	readonly renderEditor: (
		kind: ExtensionKind,
		id: string,
		context?: ExtensionContext
	) => ReactElement | null;
	/** Render a slot into an element outside the admin app; calling it again re-renders in place. */
	readonly mount: (
		element: Element,
		name: string,
		context?: ExtensionContext,
		kind?: ExtensionKind
	) => void;
}

/**
 * Create the public extension object around a registry.
 *
 * @param registry Registry to expose; a new empty one by default.
 * @return Frozen host object.
 */
export function createExtensionHost(
	registry: ExtensionRegistry = createRegistry()
): ExtensionHost {
	const roots = new WeakMap< Element, ReturnType< typeof createRoot > >();
	const view = (
		name: string,
		context: ExtensionContext,
		kind: ExtensionKind,
		onlyId?: string
	) =>
		createElement(
			ExtensionsProvider,
			{ value: registry },
			createElement( ExtensionSlot, { name, context, kind, onlyId } )
		);
	return Object.freeze( {
		...registry,
		api,
		renderSlot: ( name, context = {}, kind = 'slot' ) =>
			view( name, context, kind ),
		renderEditor: ( kind, id, context = {} ) => {
			const entry = registry.get( kind, id );
			return entry?.enabled
				? view( id, { ...context, type: id }, kind, id )
				: null;
		},
		mount: ( element, name, context = {}, kind = 'slot' ) => {
			let root = roots.get( element );
			if ( ! root ) {
				root = createRoot( element );
				roots.set( element, root );
			}
			root.render( view( name, context, kind ) );
		},
	} satisfies ExtensionHost );
}

/**
 * Whether a value is a complete extension host.
 *
 * @param value Candidate, normally `window.ohmylms?.extensions`.
 * @return True for an API v1 host.
 */
export function isExtensionHost( value: unknown ): value is ExtensionHost {
	const candidate = value as Partial< ExtensionHost > | null | undefined;
	return (
		!! candidate &&
		candidate.version === 1 &&
		typeof candidate.register === 'function' &&
		typeof candidate.mount === 'function'
	);
}

/**
 * Publish `window.ohmylms.extensions` and announce it with `ohmylms:extensions-ready`. Extension scripts
 * load after this and register synchronously; the app loads after them.
 *
 * @param target Window to install into.
 * @return The installed host, or the one already present.
 */
export function installExtensionHost( target: Window ): ExtensionHost {
	const existing = target.ohmylms?.extensions;
	if ( isExtensionHost( existing ) ) {
		return existing;
	}
	const host = createExtensionHost();
	target.ohmylms = { ...target.ohmylms, extensions: host };
	target.dispatchEvent(
		new CustomEvent( 'ohmylms:extensions-ready', { detail: host } )
	);
	return host;
}
