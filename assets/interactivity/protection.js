import { store } from '@wordpress/interactivity';
import { installProtection } from './protection/rules.js';

/** Content protection on lesson pages when the option is on. Markup: OhMyLMS\Extensions\Lesson::protection(). */
store( 'ohmylms/protection', {
	callbacks: {
		install() {
			return installProtection( { win: window, doc: document } );
		},
	},
} );
