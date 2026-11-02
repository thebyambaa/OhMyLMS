import type { HTMLAttributes, ReactElement, ReactNode } from 'react';

export interface EmptyStateProps extends HTMLAttributes< HTMLDivElement > {
	title: string;
	description?: string;
	children?: ReactNode;
}

/** Empty-content guidance. Runtime is EmptyState.jsx. */
export function EmptyState( props: EmptyStateProps ): ReactElement;
