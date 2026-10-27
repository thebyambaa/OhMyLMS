import { Component, createElement } from '@wordpress/element';
export class ExtensionBoundary extends Component {
	state = { failed: false };
	static getDerivedStateFromError() {
		return { failed: true };
	}
	componentDidCatch( error ) {
		window.dispatchEvent(
			new CustomEvent( 'ohmylms:extension-error', {
				detail: { id: this.props.id, message: error.message },
			} )
		);
	}
	render() {
		return this.state.failed ? (
			<p role="alert">This extension could not be displayed.</p>
		) : (
			this.props.children
		);
	}
}
