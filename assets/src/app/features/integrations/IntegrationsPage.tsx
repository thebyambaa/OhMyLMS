import { ListTable, ListTableSelect } from '../../../ui/ListTable';
import { Button, Notice, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { useListQuery } from '../../components/listQuery';
import { setIntegrationEnabled } from './api';
import { useIntegrations, useOperation } from './hooks';
import {
	AUTH_PARAMS,
	authNotice,
	categoryLabel,
	categoryOptions,
	EMPTY_FILTERS,
	filterIntegrations,
} from './model';
import { SettingsDialog } from './SettingsDialog';
import type { Integration } from './types';

const ALL = EMPTY_FILTERS.category;

const ADDRESS_DEFAULTS = { s: '', category: ALL, status: 'all' };

const STATES: ReadonlyArray< {
	value: string;
	label: () => string;
	matches: ( item: Integration ) => boolean;
} > = [
	{ value: 'all', label: () => __( 'All', 'ohmylms' ), matches: () => true },
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
 * Integrations (Settings › Integrations), listed like the Plugins screen: views by state, search and
 * category filter (kept in the address), a switch per add-on and its settings as a row action.
 */
export default function IntegrationsPage() {
	const list = useIntegrations();
	const toggle = useOperation();
	const query = useListQuery( ADDRESS_DEFAULTS );
	const filters = { search: query.values.s, category: query.values.category };
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

	const matches =
		STATES.find( ( state ) => state.value === status )?.matches ??
		( () => true );
	const shown = filterIntegrations( list.items, filters ).filter( matches );

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
		<>
			<p>
				{ __(
					'Switch add-ons on or off and connect them to other services.',
					'ohmylms'
				) }
			</p>
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
				<p role="status">
					{ __( 'Loading integrations…', 'ohmylms' ) }
				</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable< Integration >
					caption={ __( 'Integrations', 'ohmylms' ) }
					heading={ __( 'Integrations list', 'ohmylms' ) }
					formLabel={ __( 'Filter integrations', 'ohmylms' ) }
					rows={ shown }
					rowKey={ ( item ) => item.id }
					rowLabel={ ( item ) => item.label }
					views={ STATES.map( ( state ) => ( {
						key: state.value,
						label: state.label(),
						current: state.value === status,
						count: list.items.filter( state.matches ).length,
						href: query.href( { status: state.value } ),
						onSelect: () => query.set( { status: state.value } ),
					} ) ) }
					search={ {
						label: __( 'Search integrations', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: filters.search,
						onChange: ( search ) =>
							query.set( { s: search }, { replace: true } ),
					} }
					filters={
						<ListTableSelect
							label={ __( 'Category', 'ohmylms' ) }
							value={ filters.category }
							options={ categoryOptions( list.items ) }
							onChange={ ( category ) =>
								query.set( { category } )
							}
						/>
					}
					filterLabel={ null }
					pagination={ {
						page: 1,
						totalPages: 1,
						totalItems: shown.length,
						itemsLabel: sprintf(
							/* translators: %d: number of integrations shown. */
							_n(
								'%d integration shown.',
								'%d integrations shown.',
								shown.length,
								'ohmylms'
							),
							shown.length
						),
						onChange: () => undefined,
					} }
					emptyMessage={
						<>
							{ __(
								'No integrations match your filters.',
								'ohmylms'
							) }{ ' ' }
							<Button
								variant="link"
								onClick={ () =>
									query.set( {
										s: '',
										category: ALL,
										status: 'all',
									} )
								}
							>
								{ __( 'Clear filters', 'ohmylms' ) }
							</Button>
						</>
					}
					columns={ [
						{
							id: 'integration',
							label: __( 'Integration', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<>
									<strong>{ item.label }</strong>
									<p>{ item.description }</p>
								</>
							),
						},
						{
							id: 'category',
							label: __( 'Category', 'ohmylms' ),
							render: ( item ) =>
								item.categories
									.map( categoryLabel )
									.join( ', ' ),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) => (
								<>
									<ToggleControl
										label={ sprintf(
											/* translators: %s: integration name. */
											__( 'Enable %s', 'ohmylms' ),
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
											{ __( 'Saving…', 'ohmylms' ) }
										</span>
									) }
								</>
							),
						},
					] }
					rowActions={ ( item ) => [
						item.configurable && {
							key: 'settings',
							label: __( 'Settings', 'ohmylms' ),
							disabled: toggle.busy,
							ariaLabel: sprintf(
								/* translators: %s: integration name. */
								__( 'Settings for %s', 'ohmylms' ),
								item.label
							),
							onClick: () => setSettingsFor( item ),
						},
					] }
				/>
			) }
			{ settingsFor && (
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
		</>
	);
}
