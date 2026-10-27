import { createWebhookDetails } from './WebhookDetails';
import { createWebhookDataMapping } from './WebhookDataMapping';
import { createWebhookEditorModal } from './WebhookEditorModal';
import { createWebhooksPage } from './WebhooksPage';
export const webhookComponents = {
	WebhookDetails: createWebhookDetails,
	WebhookDataMapping: createWebhookDataMapping,
	WebhookEditorModal: createWebhookEditorModal,
	WebhooksPage: createWebhooksPage,
};
