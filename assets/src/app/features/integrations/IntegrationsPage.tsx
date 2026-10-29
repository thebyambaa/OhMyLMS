import { Table } from '../../../ui/Table';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { setIntegrationEnabled } from './api';
import { useIntegrations, useOperation } from './hooks';
import {
	categoryLabel,
	categoryOptions,
	EMPTY_FILTERS,
	filterIntegrations,
} from './model';
import { SettingsDialog } from './SettingsDialog';
import type { Integration } from './types';

export default function IntegrationsPage() {
	const list = useIntegrations();
	const toggle = useOperation();
	const [ filters, setFilters ] = useState( EMPTY_FILTERS );
	const [ settingsFor, setSettingsFor ] = useState< Integration | null >(
		null
	);
	const [ pending, setPending ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ needsReload, setNeedsReload ] = useState( false );

	const visible = filterIntegrations( list.items, filters );

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
			<h2>{ __( 'Integrations', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Switch add-ons on or off and connect them to other services.',
					'ohmylms'
				) }
			</p>
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
				<>
					<div
						role="search"
						aria-label={ __( 'Filter integrations', 'ohmylms' ) }
					>
						<TextControl
							label={ __( 'Search integrations', 'ohmylms' ) }
							type="search"
							value={ filters.search }
							onChange={ ( search ) =>
								setFilters( ( current ) => ( {
									...current,
									search,
								} ) )
							}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __( 'Category', 'ohmylms' ) }
							value={ filters.category }
							options={ categoryOptions( list.items ) }
							onChange={ ( category ) =>
								setFilters( ( current ) => ( {
									...current,
									category,
								} ) )
							}
							__nextHasNoMarginBottom
						/>
					</div>
					<p role="status">
						{ sprintf(
							/* translators: %d: number of integrations shown. */
							_n(
								'%d integration shown.',
								'%d integrations shown.',
								visible.length,
								'ohmylms'
							),
							visible.length
						) }
					</p>
					{ visible.length ? (
						<Table
							caption={ __( 'Integrations', 'ohmylms' ) }
							striped
						>
							<thead>
								<tr>
									{ [
										__( 'Integration', 'ohmylms' ),
										__( 'Category', 'ohmylms' ),
										__( 'Status', 'ohmylms' ),
										__( 'Settings', 'ohmylms' ),
									].map( ( title ) => (
										<th key={ title } scope="col">
											{ title }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ visible.map( ( item ) => (
									<tr key={ item.id }>
										<th scope="row">
											{ item.label }
											<br />
											<span>{ item.description }</span>
										</th>
										<td>
											{ item.categories
												.map( categoryLabel )
												.join( ', ' ) }
										</td>
										<td>
											<ToggleControl
												label={ sprintf(
													/* translators: %s: integration name. */
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
										</td>
										<td>
											{ item.configurable && (
												<Button
													variant="secondary"
													disabled={ toggle.busy }
													onClick={ () =>
														setSettingsFor( item )
													}
													aria-label={ sprintf(
														/* translators: %s: integration name. */
														__(
															'Settings for %s',
															'ohmylms'
														),
														item.label
													) }
												>
													{ __(
														'Settings',
														'ohmylms'
													) }
												</Button>
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
					) : (
						<p>
							{ __(
								'No integrations match your filters.',
								'ohmylms'
							) }{ ' ' }
							<Button
								variant="link"
								onClick={ () => setFilters( EMPTY_FILTERS ) }
							>
								{ __( 'Clear filters', 'ohmylms' ) }
							</Button>
						</p>
					) }
				</>
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
