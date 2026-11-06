import type { ReactElement, ReactNode } from 'react';

/** Props every labelled control (Input, Select, Textarea) shares. */
export interface FieldPresentation {
	id?: string;
	label?: ReactNode;
	help?: ReactNode;
	error?: ReactNode;
	invalid?: boolean;
	size?: 'sm' | 'md' | 'lg' | string;
	fieldClassName?: string;
	className?: string;
	required?: boolean;
}

export interface FieldProps {
	id: string;
	label?: ReactNode;
	help?: ReactNode;
	error?: ReactNode;
	required?: boolean;
	fieldClassName?: string;
	children?: ReactNode;
}

/** Label, help and error around one control. Runtime is Field.jsx. */
export function Field( props: FieldProps ): ReactElement;
