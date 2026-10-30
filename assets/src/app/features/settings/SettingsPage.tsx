import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { AccountForm } from './AccountForm';
import { AdvancedForm } from './AdvancedForm';
import { BrandingForm } from './BrandingForm';
import { CurrencyForm } from './CurrencyForm';
import { GeneralForm } from './GeneralForm';
import { useSettingsGroup } from './hooks';
import { tabFromParam } from './model';
import { PermalinkForm } from './PermalinkForm';
import { TaxForm } from './TaxForm';
import { Tabs, tabId, tabPanelId, type TabItem } from './Tabs';
import type { GroupId } from './types';
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard';

const LABELS: Record< GroupId, string > = {
	general: __( 'General', 'ohmylms' ),
	branding: __( 'Branding', 'ohmylms' ),
	'account-and-privacy': __( 'Account and privacy', 'ohmylms' ),
	permalink: __( 'Permalinks', 'ohmylms' ),
	advanced: __( 'Advanced', 'ohmylms' ),
	currency: __( 'Currency', 'ohmylms' ),
	tax: __( 'Tax', 'ohmylms' ),
};

/**
 * Settings screen at `#/settings`. The selected tab lives in the `tab` query parameter
 * (`#/settings?tab=currency`), so a tab can be linked and survives a reload. Each tab loads the
 * first time it is opened, keeps its own draft while other tabs are shown and saves independently.
 */
export default function SettingsPage() {
	const [ params, setParams ] = useSearchParams();
	const selected = tabFromParam( params.get( 'tab' ) );

	const general = useSettingsGroup( 'general', 'general' === selected );
	const branding = useSettingsGroup( 'branding', 'branding' === selected );
	const account = useSettingsGroup(
		'account-and-privacy',
		'account-and-privacy' === selected
	);
	const permalink = useSettingsGroup( 'permalink', 'permalink' === selected );
	const advanced = useSettingsGroup( 'advanced', 'advanced' === selected );
	const currency = useSettingsGroup( 'currency', 'currency' === selected );
	const tax = useSettingsGroup( 'tax', 'tax' === selected );

	const dirty: Record< GroupId, boolean > = {
		general: general.dirty,
		branding: branding.dirty,
		'account-and-privacy': account.dirty,
		permalink: permalink.dirty,
		advanced: advanced.dirty,
		currency: currency.dirty,
		tax: tax.dirty,
	};
	const guard = useUnsavedChangesGuard(
		Object.values( dirty ).some( Boolean )
	);

	const tabs: TabItem[] = ( Object.keys( LABELS ) as GroupId[] ).map(
		( id ) => ( {
			id,
			label: LABELS[ id ],
			badge: dirty[ id ] ? __( '(unsaved)', 'ohmylms' ) : undefined,
		} )
	);

	const panels: Record< GroupId, JSX.Element > = {
		general: <GeneralForm state={ general } />,
		branding: <BrandingForm state={ branding } />,
		'account-and-privacy': <AccountForm state={ account } />,
		permalink: <PermalinkForm state={ permalink } />,
		advanced: <AdvancedForm state={ advanced } />,
		currency: <CurrencyForm state={ currency } />,
		tax: <TaxForm state={ tax } />,
	};
	const visited: Record< GroupId, boolean > = {
		general: null !== general.saved || general.loading,
		branding: null !== branding.saved || branding.loading,
		'account-and-privacy': null !== account.saved || account.loading,
		permalink: null !== permalink.saved || permalink.loading,
		advanced: null !== advanced.saved || advanced.loading,
		currency: null !== currency.saved || currency.loading,
		tax: null !== tax.saved || tax.loading,
	};

	return (
		<>
			<h2>{ __( 'Settings', 'ohmylms' ) }</h2>
			{ guard.pendingPath && (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							'You have unsaved settings. Leave this page and discard them?',
							'ohmylms'
						) }
					</p>
					<Button
						variant="primary"
						isDestructive
						onClick={ guard.leave }
					>
						{ __( 'Discard and leave', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button variant="secondary" onClick={ guard.stay }>
						{ __( 'Stay on this page', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			<Tabs
				label={ __( 'Settings sections', 'ohmylms' ) }
				tabs={ tabs }
				selected={ selected }
				onSelect={ ( id ) =>
					setParams( { tab: id }, { replace: true } )
				}
			/>
			{ ( Object.keys( LABELS ) as GroupId[] ).map( ( id ) => (
				<div
					key={ id }
					role="tabpanel"
					id={ tabPanelId( id ) }
					aria-labelledby={ tabId( id ) }
					hidden={ id !== selected }
				>
					{ ( id === selected || visited[ id ] ) && panels[ id ] }
				</div>
			) ) }
		</>
	);
}
