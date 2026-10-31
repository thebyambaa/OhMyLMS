import { Button, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	buildCheckoutPatch,
	checkoutDirty,
	checkoutDraft,
	dropGateway,
	loadCheckout,
	moveGateway,
	saveCheckout,
	validateCheckout,
	type CheckoutDraft,
	type CheckoutSettings,
	type StripeStatus,
} from './checkout';
import { ToggleField } from './fields';
import { FormFrame } from './FormFrame';
import { useResource } from './useResource';

/**
 * Checkout settings above the gateway forms: the order gateways are offered in at checkout, guest
 * checkout, the checkout login form, content protection, and the read-only Stripe status.
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function CheckoutPanel( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< CheckoutSettings, CheckoutDraft >(
		{
			load: loadCheckout,
			toDraft: checkoutDraft,
			isDirty: checkoutDirty,
			validate: ( draft, stored ) => validateCheckout( stored, draft ),
			save: async ( stored, draft, signal ) => {
				const result = await saveCheckout(
					buildCheckoutPatch( stored, draft ),
					signal
				);
				return { snapshot: result.settings, changed: result.changed };
			},
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );
	const [ dragged, setDragged ] = useState< string | null >( null );

	const stored = state.snapshot;
	const draft = state.draft;
	const titles = new Map(
		( stored?.gateways ?? [] ).map( ( gateway ) => [ gateway.id, gateway ] )
	);
	const setOrder = ( order: string[] ) =>
		state.update(
			( current ) => ( { ...current, gatewayOrder: order } ),
			'gateway_order'
		);

	return (
		<FormFrame
			title={ __( 'Checkout', 'ohmylms' ) }
			description={ __(
				'How the checkout page offers payment methods and who may buy.',
				'ohmylms'
			) }
			state={ state }
			extra={
				state.saveError && ! state.saveError.permission ? (
					<Button variant="link" onClick={ state.reload }>
						{ __( 'Reload settings', 'ohmylms' ) }
					</Button>
				) : null
			}
		>
			{ stored && draft && (
				<>
					<h4 id="oml-gateway-order">
						{ __( 'Payment method order', 'ohmylms' ) }
					</h4>
					<p id="oml-gateway-order-help">
						{ __(
							'Checkout lists the payment methods in this order and selects the first one offered. Drag a row or use the buttons.',
							'ohmylms'
						) }
					</p>
					{ state.errors.gateway_order && (
						<Notice status="error" isDismissible={ false }>
							{ state.errors.gateway_order }
						</Notice>
					) }
					<ol
						className="oml-gateway-order"
						aria-labelledby="oml-gateway-order"
						aria-describedby="oml-gateway-order-help"
					>
						{ draft.gatewayOrder.map( ( id, index ) => {
							const gateway = titles.get( id );
							const title = gateway?.title ?? id;
							return (
								<li
									key={ id }
									draggable
									onDragStart={ () => setDragged( id ) }
									onDragEnd={ () => setDragged( null ) }
									onDragOver={ ( event ) =>
										event.preventDefault()
									}
									onDrop={ ( event ) => {
										event.preventDefault();
										if ( dragged ) {
											setOrder(
												dropGateway(
													draft.gatewayOrder,
													dragged,
													id
												)
											);
										}
										setDragged( null );
									} }
								>
									<strong>{ title }</strong>{ ' ' }
									<span>
										{ gateway?.enabled
											? __( '(offered)', 'ohmylms' )
											: __( '(off)', 'ohmylms' ) }
									</span>{ ' ' }
									<Button
										variant="tertiary"
										size="small"
										disabled={ 0 === index }
										accessibleWhenDisabled
										aria-label={ sprintf(
											/* translators: %s: payment method name. */
											__( 'Move %s up', 'ohmylms' ),
											title
										) }
										onClick={ () =>
											setOrder(
												moveGateway(
													draft.gatewayOrder,
													id,
													-1
												)
											)
										}
									>
										{ __( 'Up', 'ohmylms' ) }
									</Button>
									<Button
										variant="tertiary"
										size="small"
										disabled={
											index ===
											draft.gatewayOrder.length - 1
										}
										accessibleWhenDisabled
										aria-label={ sprintf(
											/* translators: %s: payment method name. */
											__( 'Move %s down', 'ohmylms' ),
											title
										) }
										onClick={ () =>
											setOrder(
												moveGateway(
													draft.gatewayOrder,
													id,
													1
												)
											)
										}
									>
										{ __( 'Down', 'ohmylms' ) }
									</Button>
								</li>
							);
						} ) }
					</ol>
					<ToggleField
						label={ __( 'Guest checkout', 'ohmylms' ) }
						help={ __(
							'Visitors can buy with only an email address. An account is created for that address at checkout and its owner is emailed a link to set a password. When off, visitors must log in first.',
							'ohmylms'
						) }
						checked={ draft.guestCheckout }
						onChange={ ( guestCheckout ) =>
							state.update(
								( current ) => ( {
									...current,
									guestCheckout,
								} ),
								'guest_checkout'
							)
						}
					/>
					<ToggleField
						label={ __( 'Login form on checkout', 'ohmylms' ) }
						help={ __(
							'Show "Already have an account? Log in" and the login form on the checkout page.',
							'ohmylms'
						) }
						checked={ draft.loginForm }
						onChange={ ( loginForm ) =>
							state.update(
								( current ) => ( { ...current, loginForm } ),
								'login_form'
							)
						}
					/>
					<ToggleField
						label={ __( 'Content protection', 'ohmylms' ) }
						help={ __(
							'On lesson, quiz and assignment pages, block right-click, copying and blur the page when it loses focus. This deters casual copying; it cannot stop screenshots. Same switch as Integrations › Content protection.',
							'ohmylms'
						) }
						checked={ draft.contentProtection }
						onChange={ ( contentProtection ) =>
							state.update(
								( current ) => ( {
									...current,
									contentProtection,
								} ),
								'content_protection'
							)
						}
					/>
					{ ! draft.guestCheckout && ! draft.loginForm && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'With guest checkout and the login form both off, visitors see only a link to the WordPress login page.',
								'ohmylms'
							) }
						</Notice>
					) }
					{ stored.stripe && (
						<StripeStatusView status={ stored.stripe } />
					) }
				</>
			) }
		</FormFrame>
	);
}

function StripeStatusView( { status }: { status: StripeStatus } ) {
	return (
		<>
			<h4>{ __( 'Stripe webhook', 'ohmylms' ) }</h4>
			<p>
				{ __(
					'Read only. Stripe payments are confirmed on the checkout page and when the buyer returns from Stripe; the plugin registers no webhook endpoint, so there is nothing to add in the Stripe dashboard.',
					'ohmylms'
				) }
			</p>
			<table className="widefat striped oml-stripe-status">
				<tbody>
					<tr>
						<th scope="row">{ __( 'Gateway', 'ohmylms' ) }</th>
						<td>
							{ status.enabled
								? __( 'Offered at checkout', 'ohmylms' )
								: __( 'Off', 'ohmylms' ) }
						</td>
					</tr>
					<tr>
						<th scope="row">{ __( 'Mode', 'ohmylms' ) }</th>
						<td>
							{ 'test' === status.mode
								? __( 'Test (sandbox)', 'ohmylms' )
								: __( 'Live', 'ohmylms' ) }
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Keys for this mode', 'ohmylms' ) }
						</th>
						<td>
							{ status.keysConfigured
								? __( 'Saved', 'ohmylms' )
								: __( 'Missing', 'ohmylms' ) }
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Webhook endpoint', 'ohmylms' ) }
						</th>
						<td>
							{ status.webhookEndpoint ??
								__( 'None registered', 'ohmylms' ) }
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Last confirmed payment', 'ohmylms' ) }
						</th>
						<td>
							{ status.lastConfirmedOrder
								? sprintf(
										/* translators: 1: order number, 2: date. */
										__( 'Order #%1$d on %2$s', 'ohmylms' ),
										status.lastConfirmedOrder.orderId,
										status.lastConfirmedOrder.date.replace(
											'T',
											' '
										)
									)
								: __( 'None yet', 'ohmylms' ) }
						</td>
					</tr>
				</tbody>
			</table>
		</>
	);
}
