import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { EMPTY_WEBHOOK, copyMapping, validateInput } from './model';
import type { Webhook, WebhookInput, WebhookOptions } from './types';

interface Props {
	item: Webhook | null;
	options: WebhookOptions;
	busy: boolean;
	error: string | null;
	onClose: () => void;
	onSave: ( value: WebhookInput ) => void;
}

export function WebhookEditor( {
	item,
	options,
	busy,
	error,
	onClose,
	onSave,
}: Props ) {
	const [ value, setValue ] = useState< WebhookInput >(
		item
			? { ...item, data_mapping: copyMapping( item.data_mapping ) }
			: {
					...EMPTY_WEBHOOK,
					trigger_event: options.triggers[ 0 ]?.value || '',
				}
	);
	const [ validation, setValidation ] = useState< string | null >( null );
	const update = < K extends keyof WebhookInput >(
		key: K,
		next: WebhookInput[ K ]
	) => setValue( ( current ) => ( { ...current, [ key ]: next } ) );
	return (
		<Modal
			title={
				item
					? __( 'Edit webhook', 'ohmylms' )
					: __( 'Add webhook', 'ohmylms' )
			}
			onRequestClose={ () => {
				if ( ! busy ) {
					onClose();
				}
			} }
			shouldCloseOnClickOutside={ false }
		>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( busy ) {
						return;
					}
					const problem = validateInput( value, options );
					setValidation( problem );
					if ( ! problem ) {
						onSave( value );
					}
				} }
			>
				{ ( validation || error ) && (
					<Notice status="error" isDismissible={ false }>
						{ validation || error }
					</Notice>
				) }
				<fieldset disabled={ busy }>
					<legend>
						{ __( 'Webhook configuration', 'ohmylms' ) }
					</legend>
					<TextControl
						label={ __( 'Name', 'ohmylms' ) }
						value={ value.name }
						maxLength={ 255 }
						required
						onChange={ ( next ) => update( 'name', next ) }
					/>
					<TextControl
						label={ __( 'Endpoint URL', 'ohmylms' ) }
						type="url"
						value={ value.webhook_url }
						required
						onChange={ ( next ) => update( 'webhook_url', next ) }
						help={ __(
							'Use a publicly reachable HTTP or HTTPS endpoint. The server checks its safety before delivery.',
							'ohmylms'
						) }
					/>
					<SelectControl
						label={ __( 'Event', 'ohmylms' ) }
						value={ value.trigger_event }
						options={ options.triggers }
						onChange={ ( next ) => update( 'trigger_event', next ) }
					/>
					<SelectControl
						label={ __( 'HTTP method', 'ohmylms' ) }
						value={ value.http_method }
						options={ options.methods }
						onChange={ ( next ) => update( 'http_method', next ) }
					/>
					<SelectControl
						label={ __( 'Encoding', 'ohmylms' ) }
						value={ value.data_type }
						options={ options.data_types }
						onChange={ ( next ) => update( 'data_type', next ) }
					/>
					<SelectControl
						label={ __( 'Status', 'ohmylms' ) }
						value={ value.status }
						options={ options.statuses }
						onChange={ ( next ) =>
							update(
								'status',
								'active' === next ? 'active' : 'inactive'
							)
						}
					/>
					<p>
						{ __(
							'No mapping sends the complete event payload. Add rows to select and rename fields.',
							'ohmylms'
						) }
					</p>
					{ value.data_mapping.map( ( row, index ) => (
						<fieldset key={ index }>
							<legend>
								{ sprintf(
									/* translators: %d: mapping row number. */
									__( 'Mapping %d', 'ohmylms' ),
									index + 1
								) }
							</legend>
							<TextControl
								label={ __( 'Output field', 'ohmylms' ) }
								value={ row.key }
								onChange={ ( next ) =>
									update(
										'data_mapping',
										value.data_mapping.map( ( pair, at ) =>
											at === index
												? { ...pair, key: next }
												: pair
										)
									)
								}
							/>
							<TextControl
								label={ __( 'Source field', 'ohmylms' ) }
								value={ row.value }
								onChange={ ( next ) =>
									update(
										'data_mapping',
										value.data_mapping.map( ( pair, at ) =>
											at === index
												? { ...pair, value: next }
												: pair
										)
									)
								}
							/>
							<Button
								variant="tertiary"
								onClick={ () =>
									update(
										'data_mapping',
										value.data_mapping.filter(
											( _, at ) => index !== at
										)
									)
								}
							>
								{ sprintf(
									/* translators: %d: mapping row number. */
									__( 'Remove mapping %d', 'ohmylms' ),
									index + 1
								) }
							</Button>
						</fieldset>
					) ) }
					<Button
						variant="secondary"
						disabled={ value.data_mapping.length >= 50 }
						onClick={ () =>
							update( 'data_mapping', [
								...value.data_mapping,
								{ key: '', value: '' },
							] )
						}
					>
						{ __( 'Add mapping', 'ohmylms' ) }
					</Button>
				</fieldset>
				<p>
					<Button
						type="submit"
						variant="primary"
						disabled={ busy }
						isBusy={ busy }
					>
						{ __( 'Save webhook', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ onClose }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</p>
			</form>
		</Modal>
	);
}
