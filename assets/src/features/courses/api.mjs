const request = ( options ) => window.wp.apiFetch( options );
export const createCourse = ( data, fetch = request ) =>
	fetch( { path: '/ohmylms/v1/courses', method: 'POST', data } );
export const loadCourse = ( id, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/courses/${ id }` } );
export const updateCourse = ( id, data, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/courses/${ id }`, method: 'POST', data } );
/**
 * These REST operations are separate writes; never report full success early.
 * @param id
 * @param course
 * @param chapters
 * @param fetch
 */
export async function saveCourseWithChapters(
	id,
	course,
	chapters,
	fetch = request
) {
	const saved = await updateCourse( id, course, fetch );
	try {
		await fetch( {
			path: `/ohmylms/v1/courses/${ id }/chapters`,
			method: 'POST',
			data: chapters,
		} );
	} catch ( cause ) {
		const error = new Error(
			'Course details were saved, but chapters could not be saved. Your edits are still available; retry Save.'
		);
		error.cause = cause;
		error.partialCourse = saved;
		throw error;
	}
	return saved;
}
