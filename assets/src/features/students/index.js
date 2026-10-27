import { createStudentList } from './StudentList';
import { createStudentNameCell } from './StudentNameCell';
import { createStudentReport } from './StudentReport';
import { createCourseStudents } from './CourseStudents';
export const studentComponents = {
	StudentList: createStudentList,
	StudentNameCell: createStudentNameCell,
	StudentReport: createStudentReport,
	CourseStudents: createCourseStudents,
};
