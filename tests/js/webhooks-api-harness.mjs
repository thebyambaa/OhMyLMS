/**
 * Shared test doubles for the webhooks parity tests: a scripted `apiFetch`, a response with
 * headers, a timer recorder and a small store that runs thunks like `@wordpress/data` does.
 */
import { plain } from './webhooks-api-recovered.mjs';

/**
 * A scripted `apiFetch`. Each call consumes the next step: `{ result }`, `{ error }` or a function
 * receiving the options. Calls are recorded as plain copies.
 *
 * @param {Array} script Steps.
 * @return {{fetch: Function, calls: Array}} Mock.
 */
export function createApiMock( script ) {
	const steps = [ ...script ];
	const calls = [];
	return {
		calls,
		fetch( options ) {
			calls.push( plain( options ) );
			const step = steps.shift();
			if ( ! step ) {
				return Promise.reject( new Error( 'Unexpected request' ) );
			}
			if ( 'function' === typeof step ) {
				return step( options );
			}
			return 'error' in step
				? Promise.reject( step.error )
				: Promise.resolve( step.result );
		},
	};
}

/**
 * A response with case-insensitive headers, as returned by `apiFetch` when `parse` is false.
 *
 * @param {*}      data    Value returned by `json()`.
 * @param {Object} headers Header values keyed by lower-case name.
 * @param {Error}  failure Optional error thrown by `json()`.
 * @return {Object} Response double.
 */
export function createResponse( data, headers = {}, failure = null ) {
	return {
		ok: true,
		status: 200,
		headers: {
			get: ( name ) => headers[ String( name ).toLowerCase() ] ?? null,
		},
		json: () =>
			failure ? Promise.reject( failure ) : Promise.resolve( data ),
	};
}

/**
 * A timer recorder with the `setTimeout` signature.
 *
 * @return {{setTimeout: Function, timers: Array, run: Function}} Recorder; `run` fires callbacks in order.
 */
export function createTimers() {
	const timers = [];
	return {
		timers,
		setTimeout( callback, delay ) {
			timers.push( { callback, delay } );
			return timers.length;
		},
		run() {
			for ( const timer of timers.splice( 0 ) ) {
				timer.callback();
			}
		},
	};
}

/**
 * A store holding one reducer. Thunks receive `{ dispatch, getState }`; plain actions are logged
 * and reduced.
 *
 * @param {Function} reducer Slice reducer.
 * @return {Object} Store with `dispatch`, `getState`, `log` and `loadingSteps`.
 */
export function createStore( reducer ) {
	let state = reducer( undefined, { type: '@@INIT' } );
	const log = [];
	const loadingSteps = [];
	const store = {
		log,
		loadingSteps,
		getState: () => state,
		dispatch( action ) {
			if ( 'function' === typeof action ) {
				return action( {
					dispatch: store.dispatch,
					getState: () => state,
				} );
			}
			log.push( plain( action ) );
			state = reducer( state, action );
			loadingSteps.push( state.loading );
			return action;
		},
	};
	return store;
}

/**
 * Let fire-and-forget promise chains (the bulk action's list reload) finish.
 */
export async function settle() {
	for ( let i = 0; i < 5; i++ ) {
		await new Promise( ( resolve ) => setImmediate( resolve ) );
	}
}
