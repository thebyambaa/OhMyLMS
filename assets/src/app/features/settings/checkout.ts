import { __ } from '@wordpress/i18n';
import { get, patch } from '../../lib/api';
import {
	asArray,
	asBoolean,
	asNumber,
	asRecord,
	asString,
	invalidResponse,
	isRecord,
} from './model';
import type { FieldErrors } from './types';

const BASE = '/ohmylms/v1/settings/checkout';

/** Read-only Stripe status (`null` when Stripe is not registered). */
export interface StripeStatus {
	enabled: boolean;
	mode: 'test' | 'live';
	keysConfigured: boolean;
	/** Always `null`: the plugin registers no Stripe webhook endpoint. */
	webhookEndpoint: string | null;
	confirmation: string;
	lastConfirmedOrder: { orderId: number; date: string } | null;
}

/** Checkout settings as the server returns them. */
export interface CheckoutSettings {
	gatewayOrder: string[];
	gateways: { id: string; title: string; enabled: boolean }[];
	guestCheckout: boolean;
	loginForm: boolean;
	contentProtection: boolean;
	stripe: StripeStatus | null;
	revision: string;
}

/** What the form edits. */
export interface CheckoutDraft {
	gatewayOrder: string[];
	guestCheckout: boolean;
	loginForm: boolean;
	contentProtection: boolean;
}

/** Body of a partial update. */
export interface CheckoutPatch {
	gateway_order?: string[];
	guest_checkout?: boolean;
	login_form?: boolean;
	content_protection?: boolean;
	revision?: string;
}

function parseStripe( raw: unknown ): StripeStatus | null {
	if ( null === raw ) {
		return null;
	}
	const row = asRecord( raw );
	const mode = asString( row.mode );
	if ( 'test' !== mode && 'live' !== mode ) {
		throw invalidResponse();
	}
	const last = isRecord( row.last_confirmed_order )
		? {
				orderId: asNumber( row.last_confirmed_order.order_id ),
				date: asString( row.last_confirmed_order.date ),
			}
		: null;
	return {
		enabled: asBoolean( row.enabled ),
		mode,
		keysConfigured: asBoolean( row.keys_configured ),
		webhookEndpoint:
			'string' === typeof row.webhook_endpoint
				? row.webhook_endpoint
				: null,
		confirmation: asString( row.confirmation ),
		lastConfirmedOrder: last,
	};
}

/**
 * Validate the server's checkout settings.
 *
 * @param raw Untrusted body.
 * @return Typed settings and the names that changed.
 */
export function parseCheckout( raw: unknown ): {
	settings: CheckoutSettings;
	changed: string[];
} {
	const row = asRecord( raw );
	const gatewayOrder = asArray( row.gateway_order ).map( asString );
	const gateways = asArray( row.gateways ).map( ( item ) => {
		const gateway = asRecord( item );
		return {
			id: asString( gateway.id ),
			title: asString( gateway.title ),
			enabled: asBoolean( gateway.enabled ),
		};
	} );
	return {
		settings: {
			gatewayOrder,
			gateways,
			guestCheckout: asBoolean( row.guest_checkout ),
			loginForm: asBoolean( row.login_form ),
			contentProtection: asBoolean( row.content_protection ),
			stripe: parseStripe( row.stripe_webhook ?? null ),
			revision: asString( row.revision ),
		},
		changed: Array.isArray( row.changed )
			? row.changed.map( asString )
			: [],
	};
}

export async function loadCheckout(
	signal?: AbortSignal
): Promise< CheckoutSettings > {
	return parseCheckout( await get< unknown >( BASE, { signal } ) ).settings;
}

export async function saveCheckout(
	changes: CheckoutPatch,
	signal?: AbortSignal
): Promise< { settings: CheckoutSettings; changed: string[] } > {
	return parseCheckout( await patch< unknown >( BASE, changes, { signal } ) );
}

export function checkoutDraft( settings: CheckoutSettings ): CheckoutDraft {
	return {
		gatewayOrder: [ ...settings.gatewayOrder ],
		guestCheckout: settings.guestCheckout,
		loginForm: settings.loginForm,
		contentProtection: settings.contentProtection,
	};
}

/**
 * Partial update for a draft, carrying the loaded revision.
 *
 * @param settings Stored settings.
 * @param draft    Draft.
 * @return Body; only `revision` when nothing changed.
 */
export function buildCheckoutPatch(
	settings: CheckoutSettings,
	draft: CheckoutDraft
): CheckoutPatch {
	const body: CheckoutPatch = {};
	if (
		draft.gatewayOrder.join( '\n' ) !== settings.gatewayOrder.join( '\n' )
	) {
		body.gateway_order = [ ...draft.gatewayOrder ];
	}
	if ( draft.guestCheckout !== settings.guestCheckout ) {
		body.guest_checkout = draft.guestCheckout;
	}
	if ( draft.loginForm !== settings.loginForm ) {
		body.login_form = draft.loginForm;
	}
	if ( draft.contentProtection !== settings.contentProtection ) {
		body.content_protection = draft.contentProtection;
	}
	if ( Object.keys( body ).length ) {
		body.revision = settings.revision;
	}
	return body;
}

export function checkoutDirty(
	settings: CheckoutSettings,
	draft: CheckoutDraft
): boolean {
	return Object.keys( buildCheckoutPatch( settings, draft ) ).length > 0;
}

/**
 * Validate like the server: the order lists every registered gateway once.
 *
 * @param settings Stored settings.
 * @param draft    Draft.
 * @return Field key to message.
 */
export function validateCheckout(
	settings: CheckoutSettings,
	draft: CheckoutDraft
): FieldErrors {
	const sent = [ ...draft.gatewayOrder ].sort();
	const known = [ ...settings.gatewayOrder ].sort();
	if ( sent.join( '\n' ) !== known.join( '\n' ) ) {
		return {
			gateway_order: __(
				'List every registered gateway exactly once.',
				'ohmylms'
			),
		};
	}
	return {};
}

/**
 * Move one gateway up or down.
 *
 * @param order  Current order.
 * @param id     Gateway to move.
 * @param offset -1 (up) or 1 (down).
 * @return New order; unchanged at the edges.
 */
export function moveGateway(
	order: readonly string[],
	id: string,
	offset: -1 | 1
): string[] {
	const from = order.indexOf( id );
	const to = from + offset;
	if ( from < 0 || to < 0 || to >= order.length ) {
		return [ ...order ];
	}
	const next = [ ...order ];
	next.splice( from, 1 );
	next.splice( to, 0, id );
	return next;
}

/**
 * Move a gateway to the position of another (drag and drop).
 *
 * @param order  Current order.
 * @param id     Dragged gateway.
 * @param target Gateway it was dropped on.
 * @return New order.
 */
export function dropGateway(
	order: readonly string[],
	id: string,
	target: string
): string[] {
	const from = order.indexOf( id );
	const to = order.indexOf( target );
	if ( from < 0 || to < 0 || from === to ) {
		return [ ...order ];
	}
	const next = [ ...order ];
	next.splice( from, 1 );
	next.splice( to, 0, id );
	return next;
}
