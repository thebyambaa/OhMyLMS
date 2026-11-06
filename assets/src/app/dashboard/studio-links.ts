/**
 * Link to one quiz in the Studio's quiz builder. Studio sections are hub sections, so this sets the hub
 * parameters; a plain link also works with the middle mouse button and without JavaScript.
 *
 * @param id Quiz id.
 */
export function quizUrl( id: number ) {
	const url = new URL( window.location.href );
	url.search = '';
	url.searchParams.set( 'hub_role', 'studio' );
	url.searchParams.set( 'hub_section', 'studio-quizzes' );
	url.searchParams.set( 'quiz', String( id ) );
	return url.toString();
}
