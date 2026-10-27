import apiFetch from '@wordpress/api-fetch';
import { normalizeLayout } from './tabLayout.mjs';

// Shared across hash-route remounts. Serialize writes so an older request cannot overwrite a newer drag.
const stores = new Map();
let writeQueue = Promise.resolve();
export function tabPreferences( scope, ids ) {
	if ( ! stores.has( scope ) ) {
		let knownIds = ids;
		const listeners = new Set();
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
	stores.get( scope ).include( ids );
	return stores.get( scope );
}
