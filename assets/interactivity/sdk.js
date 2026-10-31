/** OhMyLMS frontend extension API v1. Values are presentation state, never grading authority. */
export const apiVersion = 1;
const validators = new Map();
const questionMounts = new Map();
function register( registry, type, callback ) {
	if (
		! /^[a-z][a-z0-9_-]*$/.test( type ) ||
		typeof callback !== 'function'
	) {
		throw new TypeError(
			'A question type slug and callback are required.'
		);
	}
	if ( registry.has( type ) ) {
		throw new Error( `Already registered: ${ type }` );
	}
	registry.set( type, callback );
	return () => {
		if ( registry.get( type ) === callback ) {
			registry.delete( type );
		}
	};
}
export const registerAnswerValidator = ( type, callback ) =>
	register( validators, type, callback );
export const registerQuestionMount = ( type, callback ) =>
	register( questionMounts, type, callback );
export function isAnswered( root, type ) {
	if ( validators.has( type ) ) {
		return Boolean( validators.get( type )( root ) );
	}
	if (
		[ 'single-choice', 'multiple-choice', 'true-false' ].includes( type )
	) {
		return Boolean( root.querySelector( 'input:checked' ) );
	}
	if ( type === 'reorder' ) {
		return Boolean( root.querySelector( 'input[name]' ) );
	}
	if ( type === 'matching' ) {
		const fields = [ ...root.querySelectorAll( '.matching-answer-input' ) ];
		return (
			fields.length > 0 &&
			fields.every( ( field ) => field.value.trim() !== '' )
		);
	}
	const fields = [
		...root.querySelectorAll(
			'input:not([type=hidden]):not([type=radio]):not([type=checkbox]),textarea,select'
		),
	];
	return (
		fields.length > 0 &&
		fields.every( ( field ) => field.value.trim() !== '' )
	);
}
export function emit( root, name, detail = {}, cancelable = false ) {
	return root.dispatchEvent(
		new CustomEvent( `ohmylms:${ name }`, {
			bubbles: true,
			cancelable,
			detail: {
				apiVersion,
				...detail,
			},
		} )
	);
}

/**
 * Return a renderer's cleanup function, or null when no mount adapter is registered.
 * @param root
 * @param type
 * @param detail
 */
export function mountQuestion( root, type, detail ) {
	const callback = questionMounts.get( type );
	if ( ! callback ) {
		return null;
	}
	const cleanup = callback( root, detail );
	if ( cleanup === null || cleanup === void 0 ) {
		return () => {};
	}
	if ( typeof cleanup !== 'function' ) {
		throw new TypeError(
			'Question mounts must return a cleanup function.'
		);
	}
	return cleanup;
}

async function readJson( response ) {
	const text = await response.text();
	try {
		return text ? JSON.parse( text ) : null;
	} catch {
		const error = new Error( 'The server answered with invalid JSON.' );
		error.status = response.status;
		throw error;
	}
}

/**
 * Same-origin REST request with the cookie nonce (wpApiSettings style). Pass `root` and `nonce`
 * from the module's server-rendered state. Rejects with an Error carrying `code`, `status`, `data`.
 *
 * @param {string}      path             REST path, e.g. `ohmylms-commerce/v1/cart/items`.
 * @param {Object}      options          Request options.
 * @param {string}      [options.method] HTTP method.
 * @param {Object}      [options.data]   JSON body, or query arguments for GET.
 * @param {string}      options.root     REST root URL ending in `/`.
 * @param {string}      [options.nonce]  `wp_rest` nonce.
 * @param {AbortSignal} [options.signal] Abort signal.
 */
export async function apiFetch(
	path,
	{ method = 'GET', data, root, nonce, signal } = {}
) {
	let url = /^https?:/i.test( path )
		? path
		: String( root || '/wp-json/' ).replace( /\/?$/, '/' ) +
			String( path ).replace( /^\//, '' );
	const headers = { Accept: 'application/json' };
	const init = { method, credentials: 'same-origin', headers, signal };
	if ( nonce ) {
		headers[ 'X-WP-Nonce' ] = nonce;
	}
	if ( data && 'GET' === method ) {
		url +=
			( url.includes( '?' ) ? '&' : '?' ) +
			new URLSearchParams( data ).toString();
	} else if ( data !== undefined ) {
		headers[ 'Content-Type' ] = 'application/json';
		init.body = JSON.stringify( data );
	}
	const response = await fetch( url, init );
	const body = await readJson( response );
	if ( ! response.ok ) {
		const error = new Error(
			( body && body.message ) || `Request failed (${ response.status })`
		);
		error.code = body && body.code;
		error.status = response.status;
		error.data = body;
		throw error;
	}
	return body;
}

/**
 * POST to a legacy admin-ajax action (no REST equivalent) and return its JSON answer.
 *
 * @param {string}                          url  `admin-ajax.php` URL.
 * @param {FormData|URLSearchParams|Object} data Fields, including `action`.
 */
export async function ajaxPost( url, data ) {
	const body =
		data instanceof FormData || data instanceof URLSearchParams
			? data
			: new URLSearchParams( data );
	const response = await fetch( url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { Accept: 'application/json' },
		body,
	} );
	return readJson( response );
}
