import type {
	AnchorHTMLAttributes,
	ButtonHTMLAttributes,
	ReactElement,
	ReactNode,
} from 'react';

type Shared = {
	variant?: 'primary' | 'secondary' | 'tertiary' | 'danger' | string;
	size?: 'sm' | 'md' | 'lg' | string;
	block?: boolean;
	className?: string;
	disabled?: boolean;
	children?: ReactNode;
};

export type ButtonProps =
	| ( Shared &
			ButtonHTMLAttributes< HTMLButtonElement > & {
				href?: undefined;
			} )
	| ( Shared &
			AnchorHTMLAttributes< HTMLAnchorElement > & {
				href: string;
			} );

/** Shared button. Runtime is Button.jsx. */
export function Button( props: ButtonProps ): ReactElement;
