import type { ReactElement, ReactNode } from 'react';

export interface ModalProps {
	open?: boolean;
	title: string;
	onRequestClose: () => void;
	closeLabel?: string;
	className?: string;
	children?: ReactNode;
}

/** Shared native modal dialog. Runtime is Modal.jsx. */
export function Modal( props: ModalProps ): ReactElement;
