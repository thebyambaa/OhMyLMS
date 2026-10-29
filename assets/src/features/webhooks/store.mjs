import { createWebhooksApi } from './api.mjs';

/**
 * Webhooks state slice.
 *
 * Provides the same state, action creators, thunks and selectors as the `webhooks` section of the
 * recovered application store (factory 86169, reducer `vg`, actions `V`, selectors `H`), without
 * depending on it. Nothing here imports `@wordpress/data`: the slice is a plain reducer plus
 * creators so it can be composed into the single registered `ohmylms/store` (or registered as its
 * own store) by the caller. The vm parity test in tests/js/webhooks-api.test.mjs compares it with
 * the recovered code.
 */

/** Key of this slice in the root state, and the argument the root selectors read. */
export const WEBHOOKS_STATE_KEY = 'webhooks';

export const webhookActionTypes = Object.freeze( {
	SET_WEBHOOKS: 'SET_WEBHOOKS',
	SET_WEBHOOK: 'SET_WEBHOOK',
	ADD_WEBHOOK: 'ADD_WEBHOOK',
	UPDATE_WEBHOOK: 'UPDATE_WEBHOOK',
	DELETE_WEBHOOK: 'DELETE_WEBHOOK',
	SET_WEBHOOK_LOADING: 'SET_WEBHOOK_LOADING',
	SET_WEBHOOK_TRIGGERS: 'SET_WEBHOOK_TRIGGERS',
	SET_WEBHOOK_FORM_DATA: 'SET_WEBHOOK_FORM_DATA',
	UPDATE_WEBHOOK_FORM_DATA: 'UPDATE_WEBHOOK_FORM_DATA',
	CLEAR_WEBHOOK_FORM_DATA: 'CLEAR_WEBHOOK_FORM_DATA',
} );

const types = webhookActionTypes;

/**
 * A new, empty webhook form. A fresh object each time, so callers never share the nested
 * `data_mapping` array.
 *
 * @return {Object} Default form values.
 */
export function createDefaultWebhookFormData() {
	return {
		name: '',
		webhook_url: '',
		trigger_event: 'course_purchase',
		http_method: 'POST',
		data_type: 'json',
		status: 'active',
		description: '',
		headers: '',
		retry_attempts: 3,
		timeout: 30,
		data_mapping: [ { key: '', value: '' } ],
	};
}

export const webhooksInitialState = {
	webhooks: [],
	webhook: null,
	triggers: {},
	loading: false,
	total: 0,
	totalPages: 0,
	formData: createDefaultWebhookFormData(),
};

/**
 * Reducer for the `webhooks` slice.
 *
 * @param {Object} state  Current slice state.
 * @param {Object} action Dispatched action.
 * @return {Object} Next slice state; the same object for unknown actions.
 */
export function webhooksReducer( state = webhooksInitialState, action ) {
	switch ( action.type ) {
		case types.SET_WEBHOOKS:
			return {
				...state,
				webhooks: action.payload.data || action.payload,
				total: action.payload.total || 0,
				totalPages: action.payload.totalPages || 0,
			};
		case types.SET_WEBHOOK:
			return { ...state, webhook: action.payload };
		case types.SET_WEBHOOK_TRIGGERS:
			return { ...state, triggers: action.payload };
		case types.SET_WEBHOOK_LOADING:
			return { ...state, loading: action.payload };
		case types.ADD_WEBHOOK:
			return {
				...state,
				webhooks: [ ...state.webhooks, action.payload ],
			};
		case types.UPDATE_WEBHOOK:
			return {
				...state,
				webhooks: state.webhooks.map( ( webhook ) =>
					webhook.id === action.payload.webhookId
						? { ...webhook, ...action.payload.keyValuePair }
						: webhook
				),
			};
		case types.DELETE_WEBHOOK:
			return {
				...state,
				webhooks: state.webhooks.filter(
					( webhook ) => webhook.id !== action.payload
				),
			};
		case types.SET_WEBHOOK_FORM_DATA:
			return { ...state, formData: action.payload };
		case types.UPDATE_WEBHOOK_FORM_DATA:
			return {
				...state,
				formData: {
					...state.formData,
					[ action.payload.key ]: action.payload.value,
				},
			};
		case types.CLEAR_WEBHOOK_FORM_DATA:
			return { ...state, formData: createDefaultWebhookFormData() };
		default:
			return state;
	}
}

/*
 * Plain action creators. Names and payloads match the recovered store, including the ones the
 * features do not call directly (they are dispatched by the thunks below).
 */
export const setWebhooks = ( payload ) => ( {
	type: types.SET_WEBHOOKS,
	payload,
} );
export const setWebhook = ( payload ) => ( {
	type: types.SET_WEBHOOK,
	payload,
} );
export const setWebhookTriggers = ( payload ) => ( {
	type: types.SET_WEBHOOK_TRIGGERS,
	payload,
} );
export const setWebhookLoading = ( payload ) => ( {
	type: types.SET_WEBHOOK_LOADING,
	payload,
} );
export const addWebhook = ( payload ) => ( {
	type: types.ADD_WEBHOOK,
	payload,
} );
export const updateWebhook = ( webhookId, keyValuePair ) => ( {
	type: types.UPDATE_WEBHOOK,
	payload: { webhookId, keyValuePair },
} );
export const deleteWebhook = ( payload ) => ( {
	type: types.DELETE_WEBHOOK,
	payload,
} );
export const setWebhookFormData = ( payload ) => ( {
	type: types.SET_WEBHOOK_FORM_DATA,
	payload,
} );
export const updateWebhookFormData = ( key, value ) => ( {
	type: types.UPDATE_WEBHOOK_FORM_DATA,
	payload: { key, value },
} );
export const clearWebhookFormData = () => ( {
	type: types.CLEAR_WEBHOOK_FORM_DATA,
} );

/*
 * Root-state selectors (`state.webhooks.*`), as the recovered selectors, so they can be merged into
 * the one registered store unchanged. The recovered store also registers an action named
 * `getWebhookTriggers`; `@wordpress/data` keeps actions and selectors apart, so the names coexist.
 */
export const webhooksSelectors = {
	getWebhooks: ( state ) => state[ WEBHOOKS_STATE_KEY ].webhooks,
	getWebhook: ( state ) => state[ WEBHOOKS_STATE_KEY ].webhook,
	getWebhookTriggers: ( state ) => state[ WEBHOOKS_STATE_KEY ].triggers,
	getWebhookLoading: ( state ) => state[ WEBHOOKS_STATE_KEY ].loading,
	getWebhookTotal: ( state ) => state[ WEBHOOKS_STATE_KEY ].total,
	getWebhookTotalPages: ( state ) => state[ WEBHOOKS_STATE_KEY ].totalPages,
	selectWebhookFormData: ( state ) => state[ WEBHOOKS_STATE_KEY ].formData,
};

const NOTIFICATION_SHOW = 'SHOW_NOTIFICATION';
const NOTIFICATION_HIDE = 'HIDE_NOTIFICATION';
const NOTIFICATION_STATUSES = [ 'info', 'success', 'error' ];

/**
 * The recovered `showNotification( message, status = 'success' )` thunk: shows the message in the
 * `notification` slice and hides it after 100 ms. Used as the default notifier so the slice behaves
 * like the recovered store; it moves to the notifications module when that slice is authored.
 *
 * @param {unknown}                         message  Notification content.
 * @param {string}                          status   `info`, `success` or `error`; anything else becomes `success`.
 * @param {(...args: unknown[]) => unknown} schedule Timer function, `setTimeout` by default; tests pass a recorder.
 * @return {(...args: unknown[]) => unknown} Thunk.
 */
export function legacyShowNotification(
	message,
	status = 'success',
	schedule = setTimeout
) {
	const kind = NOTIFICATION_STATUSES.includes( status ) ? status : 'success';
	return ( { dispatch } ) => {
		dispatch( {
			type: NOTIFICATION_SHOW,
			payload: { message, status: kind },
		} );
		schedule( () => dispatch( { type: NOTIFICATION_HIDE } ), 100 );
		return Promise.resolve();
	};
}

/**
 * Notification texts for the bulk action. The server accepts `delete`, `active` and `inactive`,
 * but the recovered store keys its texts `activate` and `deactivate`, so for the two status
 * actions the notification message is `undefined`. Kept for parity; correct it together with the
 * notifications module.
 */
const BULK_MESSAGES = {
	delete: 'Selected webhooks deleted successfully',
	activate: 'Selected webhooks activated successfully',
	deactivate: 'Selected webhooks deactivated successfully',
};

/**
 * Build the thunks of the webhooks slice.
 *
 * Failures are reported as the recovered store reports them: the notification is dispatched with
 * the object `{ message, type }` as its message (the notification's own status stays `success`),
 * and the original error is rethrown. The recovered `console.error` calls are not kept.
 *
 * @param {Object}                          deps                  Dependencies.
 * @param {Object}                          deps.api              Requests from `createWebhooksApi`.
 * @param {(...args: unknown[]) => unknown} deps.showNotification `( message, status ) => thunk | action`.
 * @return {Object} The seven thunk creators keyed by their recovered names.
 */
export function createWebhookThunks( {
	api = createWebhooksApi(),
	showNotification = legacyShowNotification,
} = {} ) {
	const notify = ( dispatch, message, type ) =>
		dispatch( showNotification( { message, type } ) );

	const thunks = {
		getWebhooks:
			( params = {} ) =>
			async ( { dispatch } ) => {
				dispatch( setWebhookLoading( true ) );
				try {
					const response = await api.list( params );
					const total = response.headers.get( 'x-wp-total' );
					const totalPages =
						response.headers.get( 'x-wp-totalpages' ) || 0;
					const data = await response.json();
					dispatch(
						setWebhooks( {
							data,
							total: parseInt( total, 10 ),
							totalPages: parseInt( totalPages, 10 ),
						} )
					);
					return response;
				} catch ( error ) {
					notify( dispatch, 'Failed to load webhooks', 'error' );
					throw error;
				} finally {
					dispatch( setWebhookLoading( false ) );
				}
			},
		createWebhook:
			( data ) =>
			async ( { dispatch } ) => {
				try {
					const webhook = await api.create( data );
					dispatch( addWebhook( webhook ) );
					notify(
						dispatch,
						'Webhook created successfully',
						'success'
					);
					return webhook;
				} catch ( error ) {
					notify( dispatch, 'Failed to create webhook', 'error' );
					throw error;
				}
			},
		updateWebhookById:
			( id, data ) =>
			async ( { dispatch } ) => {
				try {
					const webhook = await api.update( id, data );
					dispatch( updateWebhook( id, webhook ) );
					notify(
						dispatch,
						'Webhook updated successfully',
						'success'
					);
					return webhook;
				} catch ( error ) {
					notify( dispatch, 'Failed to update webhook', 'error' );
					throw error;
				}
			},
		deleteWebhookById:
			( id ) =>
			async ( { dispatch } ) => {
				try {
					await api.remove( id );
					dispatch( deleteWebhook( id ) );
					notify(
						dispatch,
						'Webhook deleted successfully',
						'success'
					);
				} catch ( error ) {
					notify( dispatch, 'Failed to delete webhook', 'error' );
					throw error;
				}
			},
		bulkWebhookAction:
			( action, ids ) =>
			async ( { dispatch } ) => {
				try {
					const result = await api.bulk( action, ids );
					notify( dispatch, BULK_MESSAGES[ action ], 'success' );
					// The list reloads with default parameters, not awaited, as in the recovered store.
					Promise.resolve( dispatch( thunks.getWebhooks() ) ).catch(
						() => {}
					);
					return result;
				} catch ( error ) {
					notify(
						dispatch,
						'Failed to perform bulk action',
						'error'
					);
					throw error;
				}
			},
		getWebhookTriggers:
			() =>
			async ( { dispatch } ) => {
				const triggers = await api.triggers();
				dispatch( setWebhookTriggers( triggers ) );
				return triggers;
			},
		testWebhook:
			( data ) =>
			async ( { dispatch } ) => {
				try {
					const result = await api.test( data );
					notify(
						dispatch,
						'Test webhook sent successfully',
						'success'
					);
					return result;
				} catch ( error ) {
					notify( dispatch, 'Failed to test webhook', 'error' );
					throw error;
				}
			},
	};
	return thunks;
}

/**
 * The webhooks slice: everything a composed store needs, keyed as the recovered store registers it.
 *
 * @param {Object}                          deps                  Dependencies, see `createWebhookThunks`.
 * @param {Object}                          deps.api              Requests from `createWebhooksApi`.
 * @param {(...args: unknown[]) => unknown} deps.showNotification Notification creator; defaults to the recovered behaviour.
 * @return {{key: string, initialState: Object, reducer: (...args: unknown[]) => unknown, actions: Object, selectors: Object}} Slice.
 */
export function createWebhooksSlice( deps = {} ) {
	return {
		key: WEBHOOKS_STATE_KEY,
		initialState: webhooksInitialState,
		reducer: webhooksReducer,
		actions: {
			setWebhooks,
			setWebhook,
			setWebhookTriggers,
			setWebhookLoading,
			addWebhook,
			updateWebhook,
			deleteWebhook,
			setWebhookFormData,
			updateWebhookFormData,
			clearWebhookFormData,
			...createWebhookThunks( deps ),
		},
		selectors: webhooksSelectors,
	};
}
