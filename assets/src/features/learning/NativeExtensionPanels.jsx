import { createElement, useEffect, useState } from '@wordpress/element';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ExtensionBoundary } from '../../app/extensions/ExtensionBoundary';

/**
 * Keep registered quiz panels available in the native editor with an explicit native context.
 *
 * @param {Object} props        Component properties.
 * @param {number} props.quizId Quiz ID.
 * @return {Element} Registered panels.
 */
export function NativeExtensionPanels( { quizId } ) {
	const registry = window.ohmylms?.extensions;
	const [ revision, setRevision ] = useState( 0 );
	useEffect(
		() =>
			registry?.subscribe( () => setRevision( ( value ) => value + 1 ) ),
		[ registry ]
	);
	const entries =
		registry
			?.list( 'editor-panel' )
			.filter(
				( entry ) =>
					entry.slot === '/quiz-edit/:id' &&
					entry.id !== 'assessment-settings'
			) || [];
	return (
		<div data-extension-revision={ revision }>
			{ entries.map( ( entry ) => (
				<PluginDocumentSettingPanel
					key={ entry.id }
					name={ `ohmylms-extension-${ entry.id }` }
					title={ entry.label }
					initialOpen={ false }
				>
					<ExtensionBoundary id={ entry.id }>
						{ createElement( entry.render, {
							quizId,
							native: true,
							hash: `#/quiz-edit/${ quizId }`,
						} ) }
					</ExtensionBoundary>
				</PluginDocumentSettingPanel>
			) ) }
		</div>
	);
}
