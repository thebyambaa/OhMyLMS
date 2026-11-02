/** The one Node API the tests use to load plain CommonJS scripts (assets/js); Node's own types are not installed. */
declare module 'node:module' {
	/**
	 * A `require` relative to a module URL.
	 *
	 * @param url Module URL (`import.meta.url`).
	 * @return `require` function.
	 */
	export function createRequire( url: string ): ( id: string ) => unknown;
}
