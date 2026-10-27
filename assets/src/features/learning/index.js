import { createLessonEditor } from './LessonEditor';
import { createLessonContent } from './LessonContent';
import { createLessonSettings } from './LessonSettings';
import { createLearningContentForm } from './LearningContentForm';
import { createLearningMediaField } from './LearningMediaField';
import { createLearningEditorHeader } from './LearningEditorHeader';
import { createPrerequisiteSettings } from './PrerequisiteSettings';
import { createDripSettings } from './DripSettings';
import { createDownloadResources } from './DownloadResources';
import { createDeleteLearningItem } from './DeleteLearningItem';
import { createAssignmentEditor } from './AssignmentEditor';
import { createAssignmentContent } from './AssignmentContent';
import { createAssignmentSettings } from './AssignmentSettings';
import { createAssignmentList } from './AssignmentList';
import { createAssignmentNameCell } from './AssignmentNameCell';
import { createQuizList } from './QuizList';
import { createQuizNameCell } from './QuizNameCell';
import { createSessionList } from './SessionList';
import { createGoogleMeetEditor } from './GoogleMeetEditor';
import { createZoomEditor } from './ZoomEditor';
import { createAssignmentReport } from './AssignmentReport';
import { createAssignmentGrading } from './AssignmentGrading';
import { createAssignmentResultHeader } from './AssignmentResultHeader';
import { createAssignmentSubmission } from './AssignmentSubmission';
import { createAssignmentGradeForm } from './AssignmentGradeForm';
export const learningComponents = {
	LessonEditor: createLessonEditor,
	LessonContent: createLessonContent,
	LessonSettings: createLessonSettings,
	LearningContentForm: createLearningContentForm,
	LearningMediaField: createLearningMediaField,
	LearningEditorHeader: createLearningEditorHeader,
	PrerequisiteSettings: createPrerequisiteSettings,
	DripSettings: createDripSettings,
	DownloadResources: createDownloadResources,
	DeleteLearningItem: createDeleteLearningItem,
	AssignmentEditor: createAssignmentEditor,
	AssignmentContent: createAssignmentContent,
	AssignmentSettings: createAssignmentSettings,
	AssignmentList: createAssignmentList,
	AssignmentNameCell: createAssignmentNameCell,
	QuizList: createQuizList,
	QuizNameCell: createQuizNameCell,
	SessionList: createSessionList,
	GoogleMeetEditor: createGoogleMeetEditor,
	ZoomEditor: createZoomEditor,
	AssignmentReport: createAssignmentReport,
	AssignmentGrading: createAssignmentGrading,
	AssignmentResultHeader: createAssignmentResultHeader,
	AssignmentSubmission: createAssignmentSubmission,
	AssignmentGradeForm: createAssignmentGradeForm,
};
