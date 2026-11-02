import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';

interface ExtensionBoundaryProps {
	/** Extension id reported with `ohmylms:extension-error`. */
	id: string;
	children: ReactNode;
}

/**
 * Isolates one extension: a render error replaces only that extension with an alert and emits
 * `ohmylms:extension-error` (`detail: { id, message }`), so the surrounding screen keeps working.
 */
export class ExtensionBoundary extends Component<
	ExtensionBoundaryProps,
	{ failed: boolean }
> {
	override state = { failed: false };

	static getDerivedStateFromError() {
		return { failed: true };
	}

	override componentDidCatch( error: Error ) {
		window.dispatchEvent(
			new CustomEvent( 'ohmylms:extension-error', {
				detail: { id: this.props.id, message: error.message },
			} )
		);
	}

	override render() {
		if ( ! this.state.failed ) {
			return this.props.children;
		}
		return (
			<p role="alert" className="ohmylms-app-extension-error">
				{ __( 'This extension could not be displayed.', 'ohmylms' ) }
			</p>
		);
	}
}
