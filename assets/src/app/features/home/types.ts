/** A post type registered on the site, as shown by the demo feature. */
export interface ContentType {
	readonly slug: string;
	readonly name: string;
	readonly description: string;
}

/** Serializable error kept in the store (never store class instances in state). */
export interface LoadError {
	readonly code: string;
	readonly message: string;
	readonly status: number;
}

/** Lifecycle of the list: `idle` before the first request, then `loading`, then `ready` or `error`. */
export type LoadStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface HomeState {
	readonly status: LoadStatus;
	readonly items: readonly ContentType[];
	readonly error: LoadError | null;
}
