import type { ReactElement, Ref, TextareaHTMLAttributes } from 'react';
import type { FieldPresentation } from './Field';

export type TextareaProps = TextareaHTMLAttributes< HTMLTextAreaElement > &
	FieldPresentation & { ref?: Ref< HTMLTextAreaElement > };

/** Shared textarea with its label and messages. Runtime is Textarea.jsx. */
export function Textarea( props: TextareaProps ): ReactElement;
