import { Component } from '@wordpress/element';
import type { ErrorInfo, ReactNode } from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface ErrorBoundaryProps {
	children: ReactNode;
	/** When this value changes (for example the route), the boundary clears its error. */
	resetKey?: string;
	/** Called once per caught error, for reporting. */
	onError?: ( error: Error, info: ErrorInfo ) => void;
}

interface ErrorBoundaryState {
	error: Error | null;
}

/**
 * Catches render errors (including failed lazy imports) below it and offers a retry.
 *
 * Wrap each route, not the whole app, so navigation stays usable when one feature breaks. Retry re-renders
 * the subtree; it fixes transient failures, but a failed code-split chunk needs a page reload.
 */
export class ErrorBoundary extends Component<
	ErrorBoundaryProps,
	ErrorBoundaryState
> {
	override state: ErrorBoundaryState = { error: null };

	static getDerivedStateFromError( error: Error ): ErrorBoundaryState {
		return { error };
	}

	override componentDidCatch( error: Error, info: ErrorInfo ) {
		this.props.onError?.( error, info );
	}

	override componentDidUpdate( previous: ErrorBoundaryProps ) {
		if ( this.state.error && previous.resetKey !== this.props.resetKey ) {
			this.reset();
		}
	}

	reset = () => {
		this.setState( { error: null } );
	};

	override render() {
		if ( ! this.state.error ) {
			return this.props.children;
		}
		return (
			<div role="alert" className="ohmylms-app-error">
				<h2>{ __( 'Something went wrong', 'ohmylms' ) }</h2>
				<p>{ this.state.error.message }</p>
				<Button variant="primary" onClick={ this.reset }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			</div>
		);
	}
}
