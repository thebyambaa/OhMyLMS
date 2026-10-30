import { useSyncExternalStore } from '@wordpress/element';
import type { ComponentType } from 'react';
import { ExtensionBoundary } from '../../extensions/ExtensionBoundary';
import { useExtensionRegistry } from '../../extensions/context';
import type { Lesson } from './model';

/** Props a registered `lesson-editor` entry receives (docs/EXTENSIONS.md, `registerLessonEditor`). */
export interface LessonEditorProps {
	lesson: {
		id: number;
		type: string;
		status: string;
		name: string;
		description: string;
	};
	value: { name: string; description: string };
	onChange: ( patch: { name?: string; description?: string } ) => void;
}

/**
 * The enabled `lesson-editor` entry registered for a lesson type, if any. Re-renders when extensions
 * register later.
 *
 * @param type Lesson type.
 * @return The entry or `undefined`.
 */
export function useLessonEditor( type: string ) {
	const registry = useExtensionRegistry();
	useSyncExternalStore( registry.subscribe, registry.getRevision );
	const entry = registry.get( 'lesson-editor', type );
	return entry?.enabled ? entry : undefined;
}

/**
 * Render an extension lesson editor inside its own error boundary. The extension edits the lesson name
 * (title) and description (content); the typed editor saves them with the rest of the lesson.
 *
 * @param props          Component props.
 * @param props.lesson   Lesson draft.
 * @param props.onChange Receives title and content changes.
 */
export function LessonExtensionEditor( {
	lesson,
	onChange,
}: {
	lesson: Lesson;
	onChange: ( patch: Partial< Pick< Lesson, 'title' | 'content' > > ) => void;
} ) {
	const entry = useLessonEditor( lesson.type );
	if ( ! entry ) {
		return null;
	}
	const Render = entry.render as ComponentType< LessonEditorProps >;
	return (
		<ExtensionBoundary id={ entry.id }>
			<Render
				lesson={ {
					id: lesson.id,
					type: lesson.type,
					status: lesson.status,
					name: lesson.title,
					description: lesson.content,
				} }
				value={ { name: lesson.title, description: lesson.content } }
				onChange={ ( patch ) => {
					const next: Partial< Pick< Lesson, 'title' | 'content' > > =
						{};
					if ( 'string' === typeof patch?.name ) {
						next.title = patch.name;
					}
					if ( 'string' === typeof patch?.description ) {
						next.content = patch.description;
					}
					onChange( next );
				} }
			/>
		</ExtensionBoundary>
	);
}
