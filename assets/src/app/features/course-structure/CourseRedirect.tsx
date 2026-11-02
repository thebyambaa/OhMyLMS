import { Navigate, useLocation, useParams } from 'react-router-dom';

/**
 * Old Content Hub course links open the merged course editor: the list at `#/courses`, the outline at
 * `#/courses/:id/outline` and settings at `#/courses/:id/settings`.
 */
export default function CourseRedirect() {
	const { id } = useParams();
	const { pathname } = useLocation();
	if ( ! id ) {
		return <Navigate to="/courses" replace />;
	}
	const tab = pathname.endsWith( '/settings' ) ? 'settings' : 'outline';
	return <Navigate to={ `/courses/${ id }/${ tab }` } replace />;
}
