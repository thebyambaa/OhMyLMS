import type { HTMLAttributes, ReactElement } from 'react';

export interface ProgressProps extends HTMLAttributes< HTMLDivElement > {
	value: number;
	label: string;
	tone?: 'primary' | 'success' | string;
}

/** Shared progress bar. Runtime is Progress.jsx. */
export function Progress( props: ProgressProps ): ReactElement;
