import { createSetupWelcome } from './SetupWelcome';
import { createSetupLevelSelection } from './SetupLevelSelection';
import { createSetupPreferences } from './SetupPreferences';
import { createSetupNiche } from './SetupNiche';
import { createCourseMigration } from './CourseMigration';
import { createScormImport } from './ScormImport';
import { createCourseImport } from './CourseImport';
import { createSetupCompletion } from './SetupCompletion';
import { createSetupWizardController } from './SetupWizardController';
import { createSetupWizard } from './SetupWizard';
import { createSetupWizardPage } from './SetupWizardPage';
export const setupComponents = {
	SetupWelcome: createSetupWelcome,
	SetupLevelSelection: createSetupLevelSelection,
	SetupPreferences: createSetupPreferences,
	SetupNiche: createSetupNiche,
	CourseMigration: createCourseMigration,
	ScormImport: createScormImport,
	CourseImport: createCourseImport,
	SetupCompletion: createSetupCompletion,
	SetupWizardController: createSetupWizardController,
	SetupWizard: createSetupWizard,
	SetupWizardPage: createSetupWizardPage,
};
