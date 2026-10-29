import { createElement } from '@wordpress/element';
import { alertClass, alertRole } from './components.mjs';

/**
 * Shared feedback panel. Use role="none" for static information without announcements.
 *
 * @param {Object}                    props           Native div props.
 * @param {string}                    props.tone      Info (default), success, warning or danger.
 * @param {string}                    props.title     Optional title.
 * @param {string}                    props.role      Status, alert or none; defaults according to tone.
 * @param {string}                    props.className Additional classes.
 * @param {import('react').ReactNode} props.children  Feedback content.
 */
export function Alert( { tone, title, role, className, children, ...rest } ) {
	return createElement(
		'div',
		{
			...rest,
			className: alertClass( { tone, className } ),
			role: alertRole( { tone, role } ),
		},
		title &&
			createElement( 'strong', { className: 'oml-alert-title' }, title ),
		createElement( 'div', { className: 'oml-alert-content' }, children )
	);
}
