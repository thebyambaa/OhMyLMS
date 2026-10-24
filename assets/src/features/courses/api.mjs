const request = ( options ) => window.wp.apiFetch( options );
export const createCourse = ( data, fetch = request ) =>
	fetch( { path: '/ohmylms/v1/courses', method: 'POST', data } );
export const loadCourse = ( id, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/courses/${ id }` } );
export const updateCourse = ( id, data, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/courses/${ id }`, method: 'POST', data } );
