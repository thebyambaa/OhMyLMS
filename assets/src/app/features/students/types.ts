export type SortOrder = 'asc' | 'desc';
export type StudentOrderBy =
	'name' | 'email' | 'registered' | 'courses_enrolled';
export type ProgressFilter = 'all' | 'running' | 'completed';

/** A row of `GET /students` and the body of `GET /students/{id}`. */
export interface Student {
	id: number;
	name: string;
	email: string;
	avatar_url: string;
	registered: string | null;
	last_login: string | null;
	blocked: boolean;
	enrolled_courses: number;
	completed_courses: number;
}

export interface StudentQuery {
	page: number;
	search: string;
	/** Course ID to filter by, or null for all courses. */
	course: number | null;
	orderby: StudentOrderBy;
	order: SortOrder;
}

export interface Counter {
	completed: number;
	total: number;
}

/** One enrolment of a student, with progress. */
export interface Enrollment {
	course_id: number;
	title: string;
	status: 'enrolled' | 'banned';
	progress: 'running' | 'completed';
	enrolled_at: string | null;
	completed_at: string | null;
	lessons: Counter;
	quizzes: Counter;
	assignments: Counter;
	percent_complete: number;
}

export interface EnrollmentQuery {
	page: number;
	search: string;
	progress: ProgressFilter;
}

/** A course choice for the course filter and the enrol form. */
export interface CourseOption {
	id: number;
	title: string;
	status: string;
}
