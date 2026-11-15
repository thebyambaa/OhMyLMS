/**
 * Remembers which course builder a quiz or assignment was opened from, so its editor can lead back to
 * that course instead of the content list. Kept per browser tab; restricted storage simply forgets.
 */
const KEY = 'ohmylms:course-return';

/**
 * Notes that `content` is being opened from the outline of `course`.
 *
 * @param course  Course id.
 * @param type    Content type, such as `quiz`.
 * @param content Content post id.
 */
export function rememberCourse(
	course: number,
	type: string,
	content: number
) {
	try {
		window.sessionStorage.setItem(
			KEY,
			JSON.stringify( { course, type, content } )
		);
	} catch {
		// Without storage the editor keeps its own back link.
	}
}

/**
 * Course whose outline this content was opened from in this tab, if any.
 *
 * @param type    Content type, such as `quiz`.
 * @param content Content post id.
 * @return Course id, or null.
 */
export function courseReturn( type: string, content: number ): number | null {
	try {
		const saved: unknown = JSON.parse(
			window.sessionStorage.getItem( KEY ) ?? 'null'
		);
		if ( ! saved || 'object' !== typeof saved ) {
			return null;
		}
		const row = saved as Record< string, unknown >;
		return row.type === type &&
			row.content === content &&
			Number.isSafeInteger( row.course ) &&
			Number( row.course ) > 0
			? Number( row.course )
			: null;
	} catch {
		return null;
	}
}
