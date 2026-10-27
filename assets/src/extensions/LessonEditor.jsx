import { createElement } from '@wordpress/element';
import { ExtensionBoundary } from './ExtensionBoundary';
export function LessonEditor( { entry, editor } ) {
	return (
		<ExtensionBoundary id={ entry.id }>
			{ createElement( entry.render, {
				lesson: editor.lesson,
				value: {
					name: editor.lesson?.name || '',
					description: editor.lesson?.description || '',
				},
				onChange: ( patch ) => {
					if ( Object.hasOwn( patch, 'name' ) ) {
						editor.handleInputChange( patch.name );
					}
					if ( Object.hasOwn( patch, 'description' ) ) {
						editor.handleEditorContentChange( patch.description );
					}
				},
			} ) }
		</ExtensionBoundary>
	);
}
