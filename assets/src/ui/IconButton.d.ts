import type { ReactElement } from 'react';
import type { ButtonProps } from './Button';

export type IconButtonProps = Omit< ButtonProps, 'aria-label' > & {
	/** Accessible name and tooltip. */
	label: string;
};

/** Icon-only button. Runtime is IconButton.jsx. */
export function IconButton( props: IconButtonProps ): ReactElement;
