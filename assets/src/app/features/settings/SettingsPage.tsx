import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { AccountForm } from './AccountForm';
import { AdvancedForm } from './AdvancedForm';
import { AiTutorForm } from './AiTutorForm';
import { AtRiskForm } from './AtRiskForm';
import { BrandingForm } from './BrandingForm';
import { CourseArchiveForm } from './CourseArchiveForm';
import { CurrencyForm } from './CurrencyForm';
import { DesignSystemForm } from './DesignSystemForm';
import { GeneralForm } from './GeneralForm';
import { useSettingsGroup } from './hooks';
import { HubIconsForm } from './HubIconsForm';
import { McpForm } from './McpForm';
import { MigrationForm } from './MigrationForm';
import { tabFromParam } from './model';
import { PaymentGatewaysForm } from './PaymentGatewaysForm';
import { PermalinkForm } from './PermalinkForm';
import { QuizDesignForm } from './QuizDesignForm';
import { TaxForm } from './TaxForm';
import { useServerNavBadges } from '../../layout/serverNavBadges';
import { Tabs, useTabPanelProps, type TabItem } from './Tabs';
import {
	EXTRA_TAB_IDS,
	GROUP_IDS,
	type ExtraTabId,
	type GroupId,
	type TabId,
} from './types';
import { useUnsavedChangesGuard } from './useUnsavedChangesGuard';
import { XapiForm } from './XapiForm';

const LABELS: Record< TabId, string > = {
	general: __( 'General', 'ohmylms' ),
	branding: __( 'Branding', 'ohmylms' ),
	'account-and-privacy': __( 'Account and privacy', 'ohmylms' ),
	permalink: __( 'Permalinks', 'ohmylms' ),
	advanced: __( 'Advanced', 'ohmylms' ),
	currency: __( 'Currency', 'ohmylms' ),
	tax: __( 'Tax', 'ohmylms' ),
	'payment-gateways': __( 'Payment gateways', 'ohmylms' ),
	'design-system': __( 'Design system', 'ohmylms' ),
	'hub-icons': __( 'Hub icons', 'ohmylms' ),
	xapi: __( 'xAPI', 'ohmylms' ),
	'ai-tutor': __( 'AI tutor', 'ohmylms' ),
	mcp: __( 'MCP', 'ohmylms' ),
	'course-archive': __( 'Course archive', 'ohmylms' ),
	'quiz-design': __( 'Quiz design', 'ohmylms' ),
	migration: __( 'Migration', 'ohmylms' ),
	'at-risk': __( 'At-risk learners', 'ohmylms' ),
};

const TAB_IDS: readonly TabId[] = [ ...GROUP_IDS, ...EXTRA_TAB_IDS ];

/**
 * Settings screen at `#/settings`. The selected tab lives in the `tab` query parameter
 * (`#/settings?tab=currency`), so a tab can be linked and survives a reload. Each tab loads the
 * first time it is opened, keeps its own draft while other tabs are shown and saves independently.
 * Registry groups use `useSettingsGroup`; the other tabs own their state and report unsaved edits.
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

	const [ extraDirty, setExtraDirty ] = useState<
		Partial< Record< ExtraTabId, boolean > >
	>( {} );
	const [ opened, setOpened ] = useState< ReadonlySet< TabId > >(
		() => new Set( [ selected ] )
	);
	useEffect( () => {
		setOpened( ( current ) =>
			current.has( selected )
				? current
				: new Set( [ ...current, selected ] )
		);
	}, [ selected ] );

	const reportDirty = useCallback(
		( id: ExtraTabId, dirty: boolean ) =>
			setExtraDirty( ( current ) =>
				Boolean( current[ id ] ) === dirty
					? current
					: { ...current, [ id ]: dirty }
			),
		[]
	);
	const reporters = useMemo(
		() =>
			Object.fromEntries(
				EXTRA_TAB_IDS.map( ( id ) => [
					id,
					( dirty: boolean ) => reportDirty( id, dirty ),
				] )
			) as Record< ExtraTabId, ( dirty: boolean ) => void >,
		[ reportDirty ]
	);

	const dirty: Record< TabId, boolean > = {
		general: general.dirty,
		branding: branding.dirty,
		'account-and-privacy': account.dirty,
		permalink: permalink.dirty,
		advanced: advanced.dirty,
		currency: currency.dirty,
		tax: tax.dirty,
		'payment-gateways': Boolean( extraDirty[ 'payment-gateways' ] ),
		'design-system': Boolean( extraDirty[ 'design-system' ] ),
		'hub-icons': Boolean( extraDirty[ 'hub-icons' ] ),
		xapi: Boolean( extraDirty.xapi ),
		'ai-tutor': Boolean( extraDirty[ 'ai-tutor' ] ),
		mcp: false,
		'course-archive': Boolean( extraDirty[ 'course-archive' ] ),
		'quiz-design': Boolean( extraDirty[ 'quiz-design' ] ),
		migration: false,
		'at-risk': Boolean( extraDirty[ 'at-risk' ] ),
	};
	const guard = useUnsavedChangesGuard(
		Object.values( dirty ).some( Boolean )
	);

	const tabs: TabItem[] = TAB_IDS.map( ( id ) => ( {
		id,
		label: LABELS[ id ],
		badge: dirty[ id ] ? __( '(unsaved)', 'ohmylms' ) : undefined,
	} ) );
	useServerNavBadges(
		'section',
		Object.fromEntries( tabs.map( ( tab ) => [ tab.id, tab.badge ] ) )
	);
	const panelProps = useTabPanelProps();

	const panels: Record< TabId, JSX.Element > = {
		general: <GeneralForm state={ general } />,
		branding: <BrandingForm state={ branding } />,
		'account-and-privacy': <AccountForm state={ account } />,
		permalink: <PermalinkForm state={ permalink } />,
		advanced: <AdvancedForm state={ advanced } />,
		currency: <CurrencyForm state={ currency } />,
		tax: <TaxForm state={ tax } />,
		'payment-gateways': (
			<PaymentGatewaysForm
				active={ 'payment-gateways' === selected }
				onDirty={ reporters[ 'payment-gateways' ] }
			/>
		),
		'design-system': (
			<DesignSystemForm
				active={ 'design-system' === selected }
				onDirty={ reporters[ 'design-system' ] }
			/>
		),
		'hub-icons': (
			<HubIconsForm
				active={ 'hub-icons' === selected }
				onDirty={ reporters[ 'hub-icons' ] }
			/>
		),
		xapi: (
			<XapiForm
				active={ 'xapi' === selected }
				onDirty={ reporters.xapi }
			/>
		),
		'ai-tutor': (
			<AiTutorForm
				active={ 'ai-tutor' === selected }
				onDirty={ reporters[ 'ai-tutor' ] }
			/>
		),
		mcp: <McpForm active={ 'mcp' === selected } />,
		'course-archive': (
			<CourseArchiveForm
				active={ 'course-archive' === selected }
				onDirty={ reporters[ 'course-archive' ] }
			/>
		),
		'quiz-design': (
			<QuizDesignForm
				active={ 'quiz-design' === selected }
				onDirty={ reporters[ 'quiz-design' ] }
			/>
		),
		migration: <MigrationForm active={ 'migration' === selected } />,
		'at-risk': (
			<AtRiskForm
				active={ 'at-risk' === selected }
				onDirty={ reporters[ 'at-risk' ] }
			/>
		),
	};
	const groupVisited: Record< GroupId, boolean > = {
		general: null !== general.saved || general.loading,
		branding: null !== branding.saved || branding.loading,
		'account-and-privacy': null !== account.saved || account.loading,
		permalink: null !== permalink.saved || permalink.loading,
		advanced: null !== advanced.saved || advanced.loading,
		currency: null !== currency.saved || currency.loading,
		tax: null !== tax.saved || tax.loading,
	};
	const visited = ( id: TabId ): boolean =>
		( GROUP_IDS as readonly TabId[] ).includes( id )
			? groupVisited[ id as GroupId ]
			: opened.has( id );

	return (
		<>
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
			{ TAB_IDS.map( ( id ) => (
				<div key={ id } { ...panelProps( id, selected ) }>
					{ ( id === selected || visited( id ) ) && panels[ id ] }
				</div>
			) ) }
		</>
	);
}
