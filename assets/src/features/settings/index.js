import { createSettingsPage } from './SettingsPage';
import { createGeneralSettings } from './GeneralSettings';
import { createGeneralSettingsFields } from './GeneralSettingsFields';
import { createSettingsActionBar } from './SettingsActionBar';
import { createBrandingSettings } from './BrandingSettings';
import { createAccountPrivacySettings } from './AccountPrivacySettings';
import { createPermalinkSettings } from './PermalinkSettings';
import { createAdvancedSettings } from './AdvancedSettings';
import { createDesignSettings } from './DesignSettings';
import { createPaymentSettings } from './PaymentSettings';
import { createPaymentGatewaysSettings } from './PaymentGatewaysSettings';
import { createCurrencySettings } from './CurrencySettings';
import { createTaxSettings } from './TaxSettings';
import { createMigrationSettings } from './MigrationSettings';
import { createMigrationPlatformSelector } from './MigrationPlatformSelector';
import { createMigrationImport } from './MigrationImport';
import { createMigrationFileDropzone } from './MigrationFileDropzone';
import { createMigrationResources } from './MigrationResources';
import { createMigrationProgressModal } from './MigrationProgressModal';
export const settingsComponents = {
	SettingsPage: createSettingsPage,
	GeneralSettings: createGeneralSettings,
	GeneralSettingsFields: createGeneralSettingsFields,
	SettingsActionBar: createSettingsActionBar,
	BrandingSettings: createBrandingSettings,
	AccountPrivacySettings: createAccountPrivacySettings,
	PermalinkSettings: createPermalinkSettings,
	AdvancedSettings: createAdvancedSettings,
	DesignSettings: createDesignSettings,
	PaymentSettings: createPaymentSettings,
	PaymentGatewaysSettings: createPaymentGatewaysSettings,
	CurrencySettings: createCurrencySettings,
	TaxSettings: createTaxSettings,
	MigrationSettings: createMigrationSettings,
	MigrationPlatformSelector: createMigrationPlatformSelector,
	MigrationImport: createMigrationImport,
	MigrationFileDropzone: createMigrationFileDropzone,
	MigrationResources: createMigrationResources,
	MigrationProgressModal: createMigrationProgressModal,
};
