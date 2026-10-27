import { createElement } from '@wordpress/element';
import { ExtensionSlot } from './ExtensionSlot';
import { ExtensionBoundary } from './ExtensionBoundary';
export function wrapScreen( Component, route, registry ) {
	function ExtendedScreen( props ) {
		const context = { ...props, route, hash: window.location.hash };
		return (
			<>
				<ExtensionSlot
					registry={ registry }
					name="admin.screen.before"
					context={ context }
				/>
				<Component { ...props } />
				<ExtensionSlot
					registry={ registry }
					name="admin.screen.after"
					context={ context }
				/>
				{ ! [ '/lesson-edit/:id', '/quiz-edit/:id' ].includes(
					route
				) && (
					<ExtensionSlot
						registry={ registry }
						kind="editor-panel"
						name={ route }
						context={ context }
					/>
				) }
				{ route === '/integrations' && (
					<ExtensionSlot
						registry={ registry }
						kind="integration"
						name="integrations"
						context={ context }
					/>
				) }
			</>
		);
	}
	ExtendedScreen.displayName = `OhMyLMS(${ Component.displayName || Component.name || route })`;
	return ExtendedScreen;
}
export function extensionPage( entry ) {
	return function ExtensionPage( props ) {
		return (
			<ExtensionBoundary id={ entry.id }>
				{ createElement( entry.render, props ) }
			</ExtensionBoundary>
		);
	};
}
