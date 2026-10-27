/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner } from '@wordpress/components';
import { editorBackPath } from '../content-hub/editorNavigation.mjs';

/**
 * Resolve legacy editor routes without changing lesson IDs or extension editors.
 *
 * @param {Object} props Component properties.
 * @return {Element} Navigation status or an extension editor.
 */
export function NativeLessonRoute( props ) {
	const {
		id,
		chapterId,
		legacyEditor: LegacyEditor,
		onLessonLoaded,
		setLocalData,
	} = props;
	const [ lesson, setLesson ] = useState( null );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		let active = true;
		apiFetch( { path: `/ohmylms/v1/lessons/${ Number( id ) }` } )
			.then( ( data ) => {
				if ( ! active ) {
					return;
				}
				setLesson( data );
				onLessonLoaded?.( data );
				setLocalData?.( { description: data.description || '' } );
				if ( data.native_editor_url ) {
					const url = new URL(
						data.native_editor_url,
						window.location.href
					);
					url.searchParams.set(
						'ohmylms_return',
						editorBackPath(
							window.location.hash,
							chapterId
								? window.location.hash.slice( 1 )
								: '/content-hub/lessons'
						)
					);
					window.location.replace( url.href );
				}
			} )
			.catch( ( failure ) => active && setError( failure.message ) );
		return () => {
			active = false;
		};
	}, [ id, chapterId, onLessonLoaded, setLocalData ] );
	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}
	if ( ! lesson ) {
		return <Spinner />;
	}
	if ( ! lesson.native_editor_url ) {
		return <LegacyEditor { ...props } />;
	}
	return <Spinner />;
}
