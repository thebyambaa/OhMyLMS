import { del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	COURSE_OPTION_LIMIT,
	buildEnrollmentQuery,
	buildStudentQuery,
	parseCourseOption,
	parseEnrollment,
	parseStudent,
	parseUnenrolled,
	positiveId,
} from './model';
import type { EnrollmentQuery, StudentQuery } from './types';

const BASE = '/ohmylms/v1/students';
const studentRoute = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;

export function listStudents( query: StudentQuery, signal?: AbortSignal ) {
	return getCollection( BASE, parseStudent, {
		signal,
		query: buildStudentQuery( query ),
	} );
}
export async function getStudent( id: number, signal?: AbortSignal ) {
	return parseStudent(
		await get< unknown >( studentRoute( id ), { signal } )
	);
}
export async function setBlocked(
	id: number,
	blocked: boolean,
	signal?: AbortSignal
) {
	return parseStudent(
		await patch< unknown >( studentRoute( id ), { blocked }, { signal } )
	);
}
export function listCourses( search: string, signal?: AbortSignal ) {
	const term = search.trim();
	return getCollection( `${ BASE }/courses`, parseCourseOption, {
		signal,
		query: {
			page: 1,
			per_page: COURSE_OPTION_LIMIT,
			...( term ? { search: term } : {} ),
		},
	} );
}
export function listEnrollments(
	studentId: number,
	query: EnrollmentQuery,
	signal?: AbortSignal
) {
	return getCollection(
		`${ studentRoute( studentId ) }/enrollments`,
		parseEnrollment,
		{ signal, query: buildEnrollmentQuery( query ) }
	);
}
export async function enroll(
	studentId: number,
	courseId: number,
	signal?: AbortSignal
) {
	return parseEnrollment(
		await post< unknown >(
			`${ studentRoute( studentId ) }/enrollments`,
			{ course_id: positiveId( courseId ) },
			{ signal }
		)
	);
}
export async function unenroll(
	studentId: number,
	courseId: number,
	signal?: AbortSignal
) {
	return parseUnenrolled(
		await del< unknown >(
			`${ studentRoute( studentId ) }/enrollments/${ positiveId(
				courseId
			) }`,
			{ signal }
		)
	);
}
