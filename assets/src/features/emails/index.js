import { createEmailSettingsPage } from './EmailSettingsPage';
import { createEmailTemplateList } from './EmailTemplateList';
import { createEmailButtonPosition } from './EmailButtonPosition';
import { createEmailSenderOptions } from './EmailSenderOptions';
import { createEmailPersonalization } from './EmailPersonalization';
import { createEmailEditorPage } from './EmailEditorPage';
import { createEmailEditor } from './EmailEditor';
import { createEmailEditorHeader } from './EmailEditorHeader';
import { createEmailDesktopIcon } from './EmailDesktopIcon';
import { createEmailMobileIcon } from './EmailMobileIcon';
import { createEmailPreview } from './EmailPreview';
import { createEmailFields } from './EmailFields';
export const emailComponents = {
	EmailSettingsPage: createEmailSettingsPage,
	EmailTemplateList: createEmailTemplateList,
	EmailButtonPosition: createEmailButtonPosition,
	EmailSenderOptions: createEmailSenderOptions,
	EmailPersonalization: createEmailPersonalization,
	EmailEditorPage: createEmailEditorPage,
	EmailEditor: createEmailEditor,
	EmailEditorHeader: createEmailEditorHeader,
	EmailDesktopIcon: createEmailDesktopIcon,
	EmailMobileIcon: createEmailMobileIcon,
	EmailPreview: createEmailPreview,
	EmailFields: createEmailFields,
};
