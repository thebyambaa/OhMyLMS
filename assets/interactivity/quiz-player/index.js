import {
	store,
	getContext,
	getElement,
	withSyncEvent,
	withScope,
} from '@wordpress/interactivity';
import { isAnswered, emit } from 'ohmylms/interactivity';
import { createQuizPlayer } from './player.js';
import { sendInline } from './inline.js';

const inlineRuntime = {
	request: ( ...args ) => fetch( ...args ),
	encode: ( form ) => new URLSearchParams( new FormData( form ) ),
	refresh: async () => {
		const router = await import( '@wordpress/interactivity-router' );
		await router.actions.navigate( window.location.href, {
			force: true,
			replace: true,
			loadingAnimation: false,
		} );
	},
};
const { actions } = store(
	'ohmylms/quiz',
	createQuizPlayer( {
		getContext,
		getElement,
		withSyncEvent,
		withScope,
		isAnswered,
		emit,
		expire: () => actions.expire(),
		inlineSubmit: ( form, operation ) =>
			actions.inlineSubmit( form, operation ),
	} )
);
store( 'ohmylms/quiz', {
	actions: {
		startInline: withSyncEvent( function* ( event ) {
			event.preventDefault();
			const context = getContext();
			if ( context.submitting ) {
				return;
			}
			context.submitting = true;
			yield sendInline( context, event.target, 'start', inlineRuntime );
		} ),
		*inlineSubmit( form, operation ) {
			yield sendInline( getContext(), form, operation, inlineRuntime );
		},
	},
} );
