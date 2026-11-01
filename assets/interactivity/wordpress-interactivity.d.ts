/**
 * Types for the WordPress script modules the learner modules import. WordPress core provides them
 * through the import map; they are not npm dependencies, so `tsconfig.json` reads these instead.
 * Only the API used here is declared. Store state is printed by PHP and merged at runtime, so it is
 * untyped (`any`) until a module declares its own `@typedef`.
 */
declare module '@wordpress/interactivity' {
	export interface StoreOptions {
		lock?: boolean | string;
	}
	export function store< T extends object = any >(
		namespace: string,
		storePart?: T,
		options?: StoreOptions
	): any;
	export function getContext< T = any >( namespace?: string ): T;
	export function getElement(): {
		ref: HTMLElement | null;
		attributes: Record< string, unknown >;
	};
	export function getConfig( namespace?: string ): Record< string, any >;
	export function withSyncEvent< F extends ( ...args: any[] ) => any >(
		callback: F
	): F;
	export function withScope< F extends ( ...args: any[] ) => any >(
		callback: F
	): F;
}

declare module '@wordpress/interactivity-router' {
	export const actions: {
		navigate(
			href: string,
			options?: {
				force?: boolean;
				replace?: boolean;
				timeout?: number;
				loadingAnimation?: boolean;
				screenReaderAnnouncement?: boolean;
			}
		): Promise< void >;
		prefetch(
			href: string,
			options?: { force?: boolean }
		): Promise< void >;
	};
	export const state: { url: string };
}
