/** Markup React 18's types do not know but these modules write on purpose. */
export {};

declare module 'react' {
	interface CSSProperties {
		/** Custom properties (`style={ { '--ohmylms-paper-lines': 4 } }`). */
		[ property: `--${ string }` ]: string | number | undefined;
	}
	interface HTMLAttributes< T > {
		/** React 18 passes `inert=""` through as the HTML attribute. */
		inert?: '' | undefined;
	}
}
