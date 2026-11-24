import { Badge } from '../../../ui/Badge';
import {
	Button,
	Notice,
	SearchControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import {
	createPortal,
	lazy,
	Suspense,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { useListQuery } from '../../components/listQuery';
import { useOperation } from '../../hooks/useOperation';
import { setIntegrationEnabled } from './api';
import { useIntegrations } from './hooks';
import {
	AUTH_PARAMS,
	authNotice,
	categoryLabel,
	categoryOptions,
	EMPTY_FILTERS,
	filterIntegrations,
	FORM_ADDON_IDS,
} from './model';
import { SettingsDialog } from './SettingsDialog';
import type { Integration } from './types';
import './IntegrationsPage.css';

// The xAPI, AI tutor and MCP forms are large, so they load only when a popup opens.
const AddonSettingsDialog = lazy(
	() =>
		import(
			/* webpackChunkName: "admin-app-addon-settings" */ './AddonSettingsDialog'
		)
);

const ALL = EMPTY_FILTERS.category;

const ADDRESS_DEFAULTS = { s: '', category: ALL, status: 'all' };

const STATES: ReadonlyArray< {
	value: string;
	label: () => string;
	matches: ( item: Integration ) => boolean;
} > = [
	{
		value: 'all',
		label: () => __( 'Any status', 'ohmylms' ),
		matches: () => true,
	},
	{
		value: 'enabled',
		label: () => __( 'Enabled', 'ohmylms' ),
		matches: ( item ) => item.enabled,
	},
	{
		value: 'disabled',
		label: () => __( 'Disabled', 'ohmylms' ),
		matches: ( item ) => ! item.enabled,
	},
];

/**
 * The state shown on an add-on card.
 *
 * @param props      Component props.
 * @param props.item Add-on shown on the card.
 */
function AddonStatus( { item }: { item: Integration } ) {
	if ( item.enabled ) {
		return <Badge tone="success">{ __( 'Enabled', 'ohmylms' ) }</Badge>;
	}
	if ( item.available ) {
		return <Badge>{ __( 'Disabled', 'ohmylms' ) }</Badge>;
	}
	return <Badge tone="warning">{ __( 'Unavailable', 'ohmylms' ) }</Badge>;
}

/** Add-on catalogue with addressable category tabs and filters. */
export default function IntegrationsPage() {
	const list = useIntegrations();
	const toggle = useOperation( { errorShape: 'object' } );
	const query = useListQuery( ADDRESS_DEFAULTS );
	const categories = categoryOptions( list.items );
	const category = categories.some(
		( option ) => option.value === query.values.category
	)
		? query.values.category
		: ALL;
	const filters = { search: query.values.s, category };
	const status =
		STATES.find( ( state ) => state.value === query.values.status )
			?.value ?? 'all';
	const [ settingsFor, setSettingsFor ] = useState< Integration | null >(
		null
	);
	const [ pending, setPending ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ needsReload, setNeedsReload ] = useState( false );
	const [ params, setParams ] = useSearchParams();
	const auth = authNotice( params.get( 'auth' ), params.get( 'message' ) );

	// `?configure=xapi` (old bookmarks and links) opens that add-on's settings popup.
	const configure = params.get( 'configure' );
	useEffect( () => {
		const target = list.items.find( ( item ) => item.id === configure );
		if ( ! configure || ! target ) {
			return;
		}
		setSettingsFor( target );
		setParams(
			( current ) => {
				const next = new URLSearchParams( current );
				next.delete( 'configure' );
				return next;
			},
			{ replace: true }
		);
	}, [ configure, list.items, setParams ] );

	const matches =
		STATES.find( ( state ) => state.value === status )?.matches ??
		( () => true );
	const shown = filterIntegrations( list.items, filters ).filter( matches );

	const categorySlot = document.getElementById( 'ohmylms-addon-categories' );
	const categoryTabs = categories.map( ( { value, label } ) => {
		const active = value === category;
		return (
			<a
				key={ value }
				className={ `nav-tab${ active ? ' nav-tab-active' : '' }` }
				aria-current={ active ? 'page' : undefined }
				href={ query.href( { category: value } ) }
				onClick={ ( event ) => {
					if (
						0 === event.button &&
						! (
							event.metaKey ||
							event.ctrlKey ||
							event.shiftKey ||
							event.altKey
						)
					) {
						event.preventDefault();
						query.set( { category: value } );
					}
				} }
			>
				{ value === ALL ? __( 'All add-ons', 'ohmylms' ) : label }
			</a>
		);
	} );

	function dismissAuth() {
		setParams(
			( current ) => {
				const next = new URLSearchParams( current );
				AUTH_PARAMS.forEach( ( name ) => next.delete( name ) );
				return next;
			},
			{ replace: true }
		);
	}

	function change( item: Integration, enabled: boolean ) {
		setMessage( null );
		setPending( item.id );
		void toggle
			.run( async ( signal ) => {
				const updated = await setIntegrationEnabled(
					item.id,
					enabled,
					signal
				);
				if ( signal.aborted ) {
					return;
				}
				list.replace( updated );
				if ( updated.requires_reload ) {
					setNeedsReload( true );
				}
				setMessage(
					enabled
						? sprintf(
								/* translators: %s: integration name. */
								__( '%s enabled.', 'ohmylms' ),
								updated.label
							)
						: sprintf(
								/* translators: %s: integration name. */
								__( '%s disabled.', 'ohmylms' ),
								updated.label
							)
				);
			} )
			.finally( () => setPending( null ) );
	}

	return (
		<div className="oml-addons">
			{ ! list.loading &&
				! list.error &&
				( categorySlot ? (
					createPortal( categoryTabs, categorySlot )
				) : (
					<nav
						className="nav-tab-wrapper oml-addon-tabs"
						aria-label={ __( 'Add-on types', 'ohmylms' ) }
					>
						{ categoryTabs }
					</nav>
				) ) }
			{ auth && (
				<Notice status={ auth.status } onRemove={ dismissAuth }>
					{ auth.text }
				</Notice>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ needsReload && (
				<Notice
					status="warning"
					isDismissible={ false }
					actions={ [
						{
							label: __( 'Reload page', 'ohmylms' ),
							onClick: () => window.location.reload(),
						},
					] }
				>
					{ __(
						'Reload the page to update the admin menu.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ toggle.error && (
				<Notice status="error" onRemove={ toggle.clearError }>
					{ toggle.error.message }
				</Notice>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error.message }{ ' ' }
					{ ! list.error.permission && (
						<Button variant="link" onClick={ list.reload }>
							{ __( 'Try again', 'ohmylms' ) }
						</Button>
					) }
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading add-ons.', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && (
				<>
					<div className="oml-addon-toolbar">
						<SearchControl
							label={ __( 'Search add-ons', 'ohmylms' ) }
							placeholder={ __( 'Search add-ons', 'ohmylms' ) }
							value={ filters.search }
							onChange={ ( search ) =>
								query.set( { s: search }, { replace: true } )
							}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __( 'Status', 'ohmylms' ) }
							hideLabelFromVision
							value={ status }
							options={ STATES.map( ( state ) => ( {
								value: state.value,
								label: state.label(),
							} ) ) }
							onChange={ ( next ) =>
								query.set( { status: next } )
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<p className="oml-addon-result-count" role="status">
							{ sprintf(
								/* translators: %d: number of matching add-ons. */
								_n(
									'%d add-on',
									'%d add-ons',
									shown.length,
									'ohmylms'
								),
								shown.length
							) }
						</p>
					</div>
					{ shown.length ? (
						<ul
							className="oml-addon-grid"
							aria-label={ __( 'Add-ons', 'ohmylms' ) }
						>
							{ shown.map( ( item ) => (
								<li className="oml-addon-card" key={ item.id }>
									<div className="oml-addon-card-header">
										{ item.icon && (
											<img
												src={ item.icon }
												alt=""
												width="36"
												height="36"
											/>
										) }
										<AddonStatus item={ item } />
									</div>
									<h2>{ item.label }</h2>
									{ category === ALL &&
										item.categories.length > 0 && (
											<p className="oml-addon-category">
												{ item.categories
													.map( categoryLabel )
													.join( ', ' ) }
											</p>
										) }
									<p className="oml-addon-description">
										{ item.description }
									</p>
									<div className="oml-addon-actions">
										<div className="oml-addon-toggle">
											<ToggleControl
												label={ sprintf(
													/* translators: %s: add-on name. */
													__(
														'Enable %s',
														'ohmylms'
													),
													item.label
												) }
												checked={ item.enabled }
												disabled={
													toggle.busy ||
													( ! item.available &&
														! item.enabled )
												}
												help={
													( item.available
														? item.dependency
														: item.unavailable_reason ) ??
													undefined
												}
												onChange={ ( next ) =>
													change( item, next )
												}
												__nextHasNoMarginBottom
											/>
											{ pending === item.id && (
												<span role="status">
													{ __(
														'Saving…',
														'ohmylms'
													) }
												</span>
											) }
										</div>
										{ ( item.configurable ||
											FORM_ADDON_IDS.includes(
												item.id
											) ) && (
											<Button
												variant="secondary"
												disabled={ toggle.busy }
												aria-label={ sprintf(
													/* translators: %s: add-on name. */
													__(
														'Settings for %s',
														'ohmylms'
													),
													item.label
												) }
												onClick={ () =>
													setSettingsFor( item )
												}
											>
												{ __( 'Settings', 'ohmylms' ) }
											</Button>
										) }
									</div>
								</li>
							) ) }
						</ul>
					) : (
						<div className="oml-addon-empty">
							<p>
								{ __(
									'No add-ons match your filters.',
									'ohmylms'
								) }
							</p>
							<Button
								variant="secondary"
								onClick={ () => query.set( ADDRESS_DEFAULTS ) }
							>
								{ __( 'Clear filters', 'ohmylms' ) }
							</Button>
						</div>
					) }
				</>
			) }
			{ settingsFor && FORM_ADDON_IDS.includes( settingsFor.id ) && (
				<Suspense fallback={ null }>
					<AddonSettingsDialog
						integration={ settingsFor }
						onClose={ () => setSettingsFor( null ) }
					/>
				</Suspense>
			) }
			{ settingsFor && ! FORM_ADDON_IDS.includes( settingsFor.id ) && (
				<SettingsDialog
					integration={ settingsFor }
					onClose={ () => setSettingsFor( null ) }
					onSaved={ ( item ) => {
						setSettingsFor( null );
						setMessage(
							sprintf(
								/* translators: %s: integration name. */
								__( '%s settings saved.', 'ohmylms' ),
								item.label
							)
						);
					} }
				/>
			) }
		</div>
	);
}
