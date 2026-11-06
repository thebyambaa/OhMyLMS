/**
 * Front-end dashboard entry (webpack `admin-app-dashboard`). Enqueued only on /dashboard.
 */
import { createRoot } from '@wordpress/element';
import { App } from './App';
import './dashboard.css';

const node = document.getElementById( 'ohmylms-front-dashboard' );
if ( node ) {
	createRoot( node ).render( <App /> );
}
