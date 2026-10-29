import type { ReactElement, TableHTMLAttributes } from 'react';

export interface TableProps extends TableHTMLAttributes< HTMLTableElement > {
	caption: string;
	striped?: boolean;
	compact?: boolean;
}

/** Shared native table and named keyboard-scrollable region. Runtime is Table.jsx. */
export function Table( props: TableProps ): ReactElement;
