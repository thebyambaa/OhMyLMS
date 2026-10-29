import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useParams, useSearchParams } from 'react-router-dom';
import { parseStudentId } from './model';
import { StudentList } from './StudentList';
import { StudentProfile } from './StudentProfile';

/**
 * The `/students` route: the list, or one student's profile when `?student=<id>` (or a `:id` route
 * segment, if the shell provides one) names a student.
 */
export default function StudentsPage() {
	const params = useParams();
	const [ search ] = useSearchParams();
	const raw = params.id ?? search.get( 'student' );
	const id = parseStudentId( raw );

	if ( null !== id ) {
		return <StudentProfile key={ id } studentId={ id } />;
	}
	return (
		<>
			{ raw && (
				<Notice status="error" isDismissible={ false }>
					{ __( 'That is not a valid student.', 'ohmylms' ) }
				</Notice>
			) }
			<StudentList />
		</>
	);
}
