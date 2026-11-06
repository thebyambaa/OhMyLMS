import type { InputHTMLAttributes, ReactElement, Ref } from 'react';
import type { FieldPresentation } from './Field';

export type InputProps = Omit<
	InputHTMLAttributes< HTMLInputElement >,
	'size'
> &
	FieldPresentation & { ref?: Ref< HTMLInputElement > };

/** Shared text input with its label and messages. Runtime is Input.jsx. */
export function Input( props: InputProps ): ReactElement;
