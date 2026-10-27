import { createCertificatesPage } from './CertificatesPage';
import { createCertificateList } from './CertificateList';
import { createCertificateNameCell } from './CertificateNameCell';
import { createCertificateTemplateCard } from './CertificateTemplateCard';
import { createCertificateTemplateDialog } from './CertificateTemplateDialog';
import { createCertificateEditPage } from './CertificateEditPage';
import { createCertificateEditor } from './CertificateEditor';
import { createCertificateEditorHeader } from './CertificateEditorHeader';
import { createCertificateControls } from './CertificateControls';
import { createCertificatePreview } from './CertificatePreview';
import { createCertificateCourseSelector } from './CertificateCourseSelector';
import { createCertificateTextField } from './CertificateTextField';
import { createCertificateImageField } from './CertificateImageField';
export const certificateComponents = {
	CertificatesPage: createCertificatesPage,
	CertificateList: createCertificateList,
	CertificateNameCell: createCertificateNameCell,
	CertificateTemplateCard: createCertificateTemplateCard,
	CertificateTemplateDialog: createCertificateTemplateDialog,
	CertificateEditPage: createCertificateEditPage,
	CertificateEditor: createCertificateEditor,
	CertificateEditorHeader: createCertificateEditorHeader,
	CertificateControls: createCertificateControls,
	CertificatePreview: createCertificatePreview,
	CertificateCourseSelector: createCertificateCourseSelector,
	CertificateTextField: createCertificateTextField,
	CertificateImageField: createCertificateImageField,
};
