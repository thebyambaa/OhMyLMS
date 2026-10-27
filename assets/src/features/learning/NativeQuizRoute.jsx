import { createElement, useEffect, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { editorBackPath } from '../content-hub/editorNavigation.mjs';

/**
 * Resolve old bookmarks and embedded syllabus actions to the canonical native editor.
 * @param root0
 * @param root0.id
 * @param root0.chapterId
 */
export function NativeQuizRoute( { id, chapterId } ) {
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		if ( ! id ) {
			return undefined;
		}
		let active = true;
		apiFetch( { path: `/ohmylms/v1/quiz/${ Number( id ) }` } )
			.then( ( data ) => {
				if ( ! active ) {
					return;
				}
				if ( ! data.native_editor_url ) {
					throw new Error(
						__(
							'The Gutenberg editor is unavailable for this quiz.',
							'ohmylms'
						)
					);
				}
				const url = new URL(
					data.native_editor_url,
					window.location.href
				);
				url.searchParams.set(
					'ohmylms_return',
					editorBackPath(
						window.location.hash,
						chapterId
							? window.location.hash.slice( 1 ).split( '?' )[ 0 ]
							: '/content-hub/quizzes'
					)
				);
				window.location.replace( url.href );
			} )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ id, chapterId ] );
	return error ? (
		<Notice status="error" isDismissible={ false }>
			{ error }
		</Notice>
	) : (
		<Spinner />
	);
}
