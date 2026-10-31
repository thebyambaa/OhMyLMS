import { store, getContext, withSyncEvent } from '@wordpress/interactivity';
import { emit, request, serializeForm } from 'ohmylms/interactivity';
import { createLanguage } from './language/model.mjs';

/** Learner language switcher. Markup: OhMyLMS\I18n\Switcher. */
store(
	'ohmylms/language',
	createLanguage( {
		getContext,
		withSyncEvent,
		request,
		emit,
		serializeForm,
		reload: () => window.location.reload(),
		navigate: ( url ) => window.location.assign( url ),
	} )
);
