import type { ElementType, HTMLAttributes, ReactElement, ReactNode } from 'react';

export interface CardProps extends HTMLAttributes< HTMLElement > {
	flat?: boolean;
	interactive?: boolean;
	as?: ElementType;
	className?: string;
	children?: ReactNode;
}

export interface CardTitleProps extends HTMLAttributes< HTMLElement > {
	as?: ElementType;
	className?: string;
	children?: ReactNode;
}

/** Shared card surface. Runtime is Card.jsx. */
export function Card( props: CardProps ): ReactElement;
export function CardTitle( props: CardTitleProps ): ReactElement;
