import type { HTMLAttributes, ReactElement, ReactNode } from 'react';

export interface AlertProps extends HTMLAttributes< HTMLDivElement > {
	tone?: 'info' | 'success' | 'warning' | 'danger' | string;
	title?: string;
	children?: ReactNode;
}

/** Shared alert. Runtime is Alert.jsx. */
export function Alert( props: AlertProps ): ReactElement;
