import type { HTMLAttributes, ReactElement, ReactNode } from 'react';

export interface BadgeProps extends HTMLAttributes< HTMLSpanElement > {
	tone?: 'primary' | 'success' | 'warning' | 'danger' | 'info' | string;
	children?: ReactNode;
}

/** Shared badge for statuses and counts. Runtime is Badge.jsx. */
export function Badge( props: BadgeProps ): ReactElement;
