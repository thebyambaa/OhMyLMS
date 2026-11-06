import type { InputHTMLAttributes, ReactElement, ReactNode, Ref } from 'react';

export type CheckboxProps = Omit<
	InputHTMLAttributes< HTMLInputElement >,
	'type'
> & {
	label: ReactNode;
	help?: ReactNode;
	ref?: Ref< HTMLInputElement >;
};

/** Shared checkbox with its label. Runtime is Checkbox.jsx. */
export function Checkbox( props: CheckboxProps ): ReactElement;
