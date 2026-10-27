import { createIntegrationCard } from './IntegrationCard';
import { createZoomSettings } from './ZoomSettings';
import { createGoogleMeetSettings } from './GoogleMeetSettings';
import { createGoogleSignInSettings } from './GoogleSignInSettings';
import { createMicrosoftSignInSettings } from './MicrosoftSignInSettings';
import { createIntegrationConfig } from './IntegrationConfig';
import { createIntegrationsPage } from './IntegrationsPage';
export const integrationComponents = {
	IntegrationCard: createIntegrationCard,
	ZoomSettings: createZoomSettings,
	GoogleMeetSettings: createGoogleMeetSettings,
	GoogleSignInSettings: createGoogleSignInSettings,
	MicrosoftSignInSettings: createMicrosoftSignInSettings,
	IntegrationConfig: createIntegrationConfig,
	IntegrationsPage: createIntegrationsPage,
};
