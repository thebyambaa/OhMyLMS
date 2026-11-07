import { Navigate, useLocation, useParams } from 'react-router-dom';

/**
 * Old Content Hub course links open the merged course editor: the list at `#/courses`, the outline at
 * `#/courses/:id/outline` and settings on the Basics step at `#/courses/:id`.
 */
export default function CourseRedirect() {
	const { id } = useParams();
	const { pathname } = useLocation();
	if ( ! id ) {
		return <Navigate to="/courses" replace />;
	}
	const target = pathname.endsWith( '/settings' )
		? `/courses/${ id }`
		: `/courses/${ id }/outline`;
	return <Navigate to={ target } replace />;
}
