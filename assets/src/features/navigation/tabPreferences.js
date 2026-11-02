import apiFetch from '@wordpress/api-fetch';
import { normalizeLayout } from './tabLayout.mjs';

/** @typedef {import('./tabLayout.mjs').TabLayout} TabLayout */

/**
 * @typedef {Object} TabPreferencesState
 * @property {TabLayout}                    layout Current layout.
 * @property {'saved' | 'saving' | 'error'} status Save status.
 */

/**
 * @typedef {Object} TabPreferencesStore
 * @property {() => TabPreferencesState}                                          read      Current state.
 * @property {( layout: TabLayout ) => void}                                      save      Save a layout.
 * @property {( nextIds: string[] ) => void}                                      include   Add tab ids.
 * @property {( listener: ( state: TabPreferencesState ) => void ) => () => void} subscribe Listen; returns the unsubscriber.
 */

// Shared across hash-route remounts. Serialize writes so an older request cannot overwrite a newer drag.
/** @type {Map<string, TabPreferencesStore>} */
const stores = new Map();
let writeQueue = Promise.resolve();
/**
 * @param {string}   scope Preference scope.
 * @param {string[]} ids   Tab ids.
 * @return {TabPreferencesStore} The shared store of the scope.
 */
export function tabPreferences( scope, ids ) {
	if ( ! stores.has( scope ) ) {
		let knownIds = ids;
		/** @type {Set<( state: TabPreferencesState ) => void>} */
		const listeners = new Set();
		/** @type {TabPreferencesState} */
		let state = {
			layout: normalizeLayout(
				window.ohmylmsTabPreferences?.[ scope ],
				ids
			),
			status: 'saved',
		};
		let revision = 0;
		const emit = () =>
			listeners.forEach( ( listener ) => listener( state ) );
		/** @param {TabLayout} layout */
		const save = ( layout ) => {
			const current = ++revision;
			state = {
				layout: normalizeLayout( layout, knownIds ),
				status: 'saving',
			};
			const payload = state.layout;
			emit();
			writeQueue = writeQueue
				.catch( () => {} )
				.then( () =>
					current === revision
						? apiFetch( {
								path: `/ohmylms/v1/tab-preferences/${ scope }`,
								method: 'PUT',
								data: payload,
							} )
						: undefined
				)
				.then( () => {
					if ( current === revision ) {
						state = { ...state, status: 'saved' };
						emit();
					}
				} )
				.catch( () => {
					if ( current === revision ) {
						state = { ...state, status: 'error' };
						emit();
					}
				} );
		};
		stores.set( scope, {
			read: () => state,
			save,
			include( nextIds ) {
				knownIds = [ ...new Set( [ ...knownIds, ...nextIds ] ) ];
			},
			subscribe( listener ) {
				listeners.add( listener );
				return () => listeners.delete( listener );
			},
		} );
	}
	const store = /** @type {TabPreferencesStore} */ ( stores.get( scope ) );
	store.include( ids );
	return store;
}
