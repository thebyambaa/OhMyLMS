import type { ReactElement, ReactNode, SelectHTMLAttributes } from 'react';
import type { FieldPresentation } from './Field';

export type SelectProps = Omit<
	SelectHTMLAttributes< HTMLSelectElement >,
	'size'
> &
	FieldPresentation & {
		options?: Array< {
			value: string | number;
			label: ReactNode;
			disabled?: boolean;
		} >;
		placeholder?: string;
	};

/** Shared select with its label and messages. Runtime is Select.jsx. */
export function Select( props: SelectProps ): ReactElement;
