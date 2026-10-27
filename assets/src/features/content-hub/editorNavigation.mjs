import { hashQuery } from './hubRoutes.mjs';

/**
 * Keep the syllabus selection when opening its reusable content in a standalone editor.
 * @param id
 * @param node
 */
export function syllabusReturnPath( id, node ) {
	return `/content-hub/curriculum/syllabus/${ Number( id ) }?${ new URLSearchParams( { node } ) }`;
}

export function withEditorReturn( path, returnTo ) {
	return returnTo
		? `${ path }?${ new URLSearchParams( { returnTo } ) }`
		: path;
}

/**
 * Open a known built-in lesson without mounting an intermediate LMS route.
 *
 * @param {number} id       Lesson ID.
 * @param {string} returnTo LMS return route.
 * @param {string} adminUrl Current admin page URL.
 * @return {string} Native editor URL.
 */
export function nativeLessonHref(
	id,
	returnTo,
	adminUrl = window.location.href
) {
	const url = new URL( 'post.php', adminUrl );
	url.searchParams.set( 'post', Number( id ) );
	url.searchParams.set( 'action', 'edit' );
	if ( returnTo ) {
		url.searchParams.set( 'ohmylms_return', returnTo );
	}
	return url.href;
}

/**
 * Use the native URL supplied by the server, retaining custom lesson routes.
 *
 * @param {Object} lesson   Lesson data.
 * @param {string} returnTo LMS return route.
 * @return {string} Editor link.
 */
export function lessonEditorHref( lesson, returnTo ) {
	if ( lesson.native_editor_url ) {
		const url = new URL( lesson.native_editor_url );
		if ( returnTo ) {
			url.searchParams.set( 'ohmylms_return', returnTo );
		}
		return url.href;
	}
	return `#${ withEditorReturn( `/lesson-edit/${ lesson.id }`, returnTo ) }`;
}

export function editorBackPath( hash, fallback ) {
	const target = hashQuery( hash ).get( 'returnTo' );
	// Only accept a local syllabus workspace, never an arbitrary URL from the query string.
	return /^\/content-hub\/curriculum\/syllabus\/[1-9]\d*(?:\?node=[cgs](?:%3A|:)\d+(?:(?:%3A|:)\d+)?)?$/i.test(
		target || ''
	)
		? target
		: fallback;
}

/**
 * Add the LMS return route to a native post editor link, leaving any other link alone.
 *
 * @param {string} href     Editor link.
 * @param {string} returnTo LMS return route.
 * @return {string} Editor link that returns to the LMS route.
 */
export function withNativeReturn( href, returnTo ) {
	try {
		const url = new URL( href, window.location.href );
		if ( returnTo && url.pathname.endsWith( '/post.php' ) ) {
			url.searchParams.set( 'ohmylms_return', returnTo );
			return url.href;
		}
	} catch {
		// Keep the original link when it cannot be parsed.
	}
	return href;
}
