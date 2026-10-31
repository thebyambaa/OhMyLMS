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

/*
 * Shared learner helpers. Keep this module free of imports (including relative ones): it is the
 * `ohmylms/interactivity` import-map entry, classic scripts reach it only through `ohmylms:*`
 * events, and node tests and the Playwright fixture load it as a single file.
 */

const hasDocument = () => typeof document !== 'undefined';

// ---------------------------------------------------------------------------------------------
// Server configuration

/**
 * @typedef {Object} SdkMessages
 * @property {string} error        Generic failure.
 * @property {string} network      No response (offline, DNS, CORS).
 * @property {string} invalidJson  The answer was not JSON.
 * @property {string} invalidNonce admin-ajax answered `-1`, or the REST nonce could not be renewed.
 * @property {string} aborted      The caller aborted the request.
 * @property {string} timeout      The `timeout` option elapsed.
 * @property {string} confirm      Default confirm button label.
 * @property {string} cancel       Default cancel button label.
 * @property {string} dismiss      Accessible name of toast close buttons.
 */

/**
 * @typedef {Object} SdkConfig
 * @property {string}      restRoot  REST root URL ending in `/`.
 * @property {string}      restNonce `wp_rest` nonce for the current user.
 * @property {string}      ajaxUrl   `admin-ajax.php` URL.
 * @property {SdkMessages} messages  Translated fallback messages.
 */

/** @type {SdkMessages} */
const DEFAULT_MESSAGES = {
	error: 'Something went wrong. Please try again.',
	network: 'Could not reach the server. Check your connection and try again.',
	invalidJson: 'The server answered with invalid JSON.',
	invalidNonce:
		'Your session has expired. Please reload the page and try again.',
	aborted: 'The request was cancelled.',
	timeout: 'The server took too long to answer. Please try again.',
	confirm: 'Confirm',
	cancel: 'Cancel',
	dismiss: 'Dismiss notification',
};

/** @type {SdkConfig|null} */
let config = null;

/**
 * Configuration printed by `Extensions\Chrome::sdk_data()` through WordPress's
 * `script_module_data_ohmylms/interactivity` filter (a JSON script tag), read once.
 *
 * @return {SdkConfig} Configuration.
 */
export function getConfig() {
	if ( ! config ) {
		let data = {};
		try {
			const node =
				hasDocument() &&
				document.getElementById(
					'wp-script-module-data-ohmylms/interactivity'
				);
			data = node ? JSON.parse( node.textContent || '{}' ) || {} : {};
		} catch {
			data = {};
		}
		config = {
			restRoot: String( data.restRoot || '/wp-json/' ),
			restNonce: String( data.restNonce || '' ),
			ajaxUrl: String( data.ajaxUrl || '/wp-admin/admin-ajax.php' ),
			messages: { ...DEFAULT_MESSAGES, ...( data.messages || {} ) },
		};
	}
	return config;
}

/**
 * Override configuration (tests, pages that print their own nonce). Messages merge.
 *
 * @param {Partial<SdkConfig>|null} partial Values to replace; `null` re-reads the page.
 * @return {SdkConfig} New configuration.
 */
export function setConfig( partial ) {
	if ( null === partial ) {
		config = null;
		return getConfig();
	}
	const current = getConfig();
	config = {
		...current,
		...partial,
		messages: { ...current.messages, ...( partial?.messages || {} ) },
	};
	return config;
}

// ---------------------------------------------------------------------------------------------
// Encoding

const isBlob = ( value ) =>
	typeof Blob !== 'undefined' && value instanceof Blob;

function eachField( data, prefix, add ) {
	for ( const [ key, value ] of Object.entries( data ) ) {
		addField( prefix ? `${ prefix }[${ key }]` : key, value, add );
	}
}

function addField( name, value, add ) {
	if ( value === undefined ) {
		return;
	}
	if ( Array.isArray( value ) ) {
		value.forEach( ( item, index ) => {
			if (
				item !== null &&
				typeof item === 'object' &&
				! isBlob( item )
			) {
				eachField( item, `${ name }[${ index }]`, add );
			} else {
				addField( `${ name }[]`, item, add );
			}
		} );
		return;
	}
	if ( value !== null && typeof value === 'object' && ! isBlob( value ) ) {
		eachField( value, name, add );
		return;
	}
	add( name, value === null ? '' : value );
}

const hasBlob = ( value ) =>
	isBlob( value ) ||
	( value !== null &&
		typeof value === 'object' &&
		Object.values( value ).some( hasBlob ) );

/**
 * PHP-style query parameters: arrays become `name[]`, objects `name[key]`; `undefined` is
 * skipped and `null` sent as an empty string.
 *
 * @param {Object|URLSearchParams|FormData|null} data   Fields.
 * @param {URLSearchParams}                      [into] Parameters to append to.
 * @return {URLSearchParams} Parameters.
 */
export function toSearchParams( data, into = new URLSearchParams() ) {
	if ( data instanceof URLSearchParams || isFormData( data ) ) {
		for ( const [ key, value ] of data ) {
			if ( typeof value === 'string' ) {
				into.append( key, value );
			}
		}
		return into;
	}
	eachField( data || {}, '', ( name, value ) =>
		into.append( name, String( value ) )
	);
	return into;
}

/**
 * The same encoding as `toSearchParams()`, as `FormData` so files can be sent.
 *
 * @param {Object|FormData|null} data Fields.
 * @return {FormData} Form data.
 */
export function toFormData( data ) {
	if ( isFormData( data ) ) {
		return data;
	}
	const form = new FormData();
	eachField( data || {}, '', ( name, value ) =>
		isBlob( value )
			? form.append( name, value )
			: form.append( name, String( value ) )
	);
	return form;
}

function isFormData( value ) {
	return typeof FormData !== 'undefined' && value instanceof FormData;
}

function isFormElement( value ) {
	return (
		typeof HTMLFormElement !== 'undefined' &&
		value instanceof HTMLFormElement
	);
}

/**
 * Group `[name, value]` entries into an object: `name[]` and repeated names become arrays.
 *
 * @param {Iterable<[string, unknown]>} entries Entries, e.g. a `FormData`.
 * @return {Object<string, unknown>} Object.
 */
export function entriesToObject( entries ) {
	const result = {};
	for ( const [ rawName, value ] of entries ) {
		const list = rawName.endsWith( '[]' );
		const name = list ? rawName.slice( 0, -2 ) : rawName;
		if ( list ) {
			result[ name ] = [ ...( result[ name ] || [] ), value ];
		} else if ( name in result ) {
			result[ name ] = [].concat( result[ name ], value );
		} else {
			result[ name ] = value;
		}
	}
	return result;
}

/**
 * Serialise a form the way the browser would submit it (disabled controls, unchecked boxes
 * and buttons are left out), optionally dropping or adding fields.
 *
 * @param {HTMLFormElement}              form                Form.
 * @param {Object}                       [options]           Options.
 * @param {string[]}                     [options.exclude]   Field names to drop.
 * @param {Object<string,string|Blob>}   [options.extra]     Fields to set (replacing).
 * @param {HTMLElement}                  [options.submitter] Button whose name/value is sent.
 * @param {'formdata'|'params'|'object'} [options.as]        Output type.
 * @return {FormData|URLSearchParams|Object} Serialised fields.
 */
export function serializeForm(
	form,
	{ exclude = [], extra = {}, submitter, as = 'formdata' } = {}
) {
	const data = submitter
		? new FormData( form, submitter )
		: new FormData( form );
	for ( const name of exclude ) {
		data.delete( name );
	}
	for ( const [ name, value ] of Object.entries( extra ) ) {
		data.set( name, value );
	}
	if ( 'params' === as ) {
		return toSearchParams( data );
	}
	return 'object' === as ? entriesToObject( data ) : data;
}

// ---------------------------------------------------------------------------------------------
// Requests

/**
 * @typedef {Object} RequestOptions
 * @property {string}                                          [path]       REST route below the REST root (`ohmylms/v1/…`) or an absolute URL.
 * @property {string}                                          [action]     admin-ajax action. With `path`, the fallback when the route is missing.
 * @property {string}                                          [url]        Explicit URL. With `action` it replaces `ajaxUrl`; alone it is fetched as is.
 * @property {string}                                          [method]     Defaults to GET for REST/URL without data, POST otherwise and for admin-ajax.
 * @property {Object|FormData|URLSearchParams|HTMLFormElement} [data]       Body, or query arguments for GET/HEAD.
 * @property {Object}                                          [query]      Extra query arguments.
 * @property {string|false}                                    [nonce]      REST: `X-WP-Nonce` (default: server nonce; `false` sends none). admin-ajax: posted as `nonceField`.
 * @property {string}                                          [nonceField] admin-ajax nonce field name (default `nonce`).
 * @property {string}                                          [restRoot]   REST root override.
 * @property {string}                                          [ajaxUrl]    admin-ajax URL override.
 * @property {Object<string,string>}                           [headers]    Extra headers.
 * @property {AbortSignal}                                     [signal]     Abort signal.
 * @property {number}                                          [timeout]    Milliseconds before the request is aborted.
 * @property {'json'|'text'}                                   [parse]      `text` returns the body as a string (HTML fragments).
 */

/**
 * @typedef {Object} RequestError
 * @property {string} code    `rest_*`/server code, or `http_<status>`, `ajax_error`, `invalid_nonce`,
 *                            `invalid_action`, `invalid_json`, `network_error`, `aborted`, `timeout`.
 * @property {string} message Server message, or a translated fallback.
 * @property {number} status  HTTP status (`data.status` for REST errors), 0 without a response.
 */

/**
 * @typedef {Object} RequestResult
 * @property {boolean}             ok        True for a 2xx answer that is not a WordPress error.
 * @property {number}              status    HTTP status, 0 without a response.
 * @property {unknown}             data      REST body; `data` of a `wp_send_json_*` envelope; or the whole legacy
 *                                           `{ status, message, … }` body. Error payloads too.
 * @property {string}              message   Server message (success or error), '' when there is none.
 * @property {RequestError|null}   error     Normalised error, null on success.
 * @property {unknown}             raw       Parsed body as sent (string for `parse: 'text'` and invalid JSON).
 * @property {'rest'|'ajax'|'url'} transport Which endpoint answered.
 */

const NO_BODY = [ 'GET', 'HEAD' ];

const textOf = ( value ) =>
	typeof value === 'string' || typeof value === 'number'
		? String( value )
		: '';

function messageOf( payload ) {
	if ( Array.isArray( payload ) ) {
		return messageOf( payload[ 0 ] );
	}
	if ( payload && typeof payload === 'object' ) {
		return textOf( payload.message ) || textOf( payload.messages );
	}
	return typeof payload === 'string' ? payload : '';
}

function codeOf( payload ) {
	if ( Array.isArray( payload ) ) {
		return codeOf( payload[ 0 ] );
	}
	return payload && typeof payload === 'object' ? textOf( payload.code ) : '';
}

/**
 * Turn one HTTP exchange into a `RequestResult`. Pure, so every response shape the plugin
 * uses is covered by tests/js/sdk.test.mjs.
 *
 * @param {Object}      reply           Exchange.
 * @param {string}      reply.transport `rest`, `ajax` or `url`.
 * @param {number}      [reply.status]  HTTP status.
 * @param {unknown}     [reply.body]    Parsed body (string when `invalid` or `parse` is `text`).
 * @param {boolean}     [reply.invalid] The body was not JSON.
 * @param {string}      [reply.failure] `network`, `aborted` or `timeout` when there was no response.
 * @param {string}      [reply.parse]   `json` or `text`.
 * @param {SdkMessages} [messages]      Fallback messages.
 * @return {RequestResult} Result.
 */
export function normalizeResponse( reply, messages = getConfig().messages ) {
	const { transport } = reply;
	const status = reply.status || 0;
	const httpOk = status >= 200 && status < 300;
	const body = reply.body === undefined ? null : reply.body;
	const done = ( data, message = '' ) => ( {
		ok: true,
		status,
		data,
		message,
		error: null,
		raw: body,
		transport,
	} );
	const fail = ( code, message, data = null, errorStatus = status ) => ( {
		ok: false,
		status,
		data,
		message,
		error: { code, message, status: errorStatus },
		raw: body,
		transport,
	} );
	if ( reply.failure ) {
		const code =
			'network' === reply.failure ? 'network_error' : reply.failure;
		return fail( code, messages[ reply.failure ] || messages.error );
	}
	if ( 'text' === reply.parse ) {
		return httpOk
			? done( body )
			: fail( `http_${ status }`, messages.error, body );
	}
	if ( reply.invalid ) {
		return fail( 'invalid_json', messages.invalidJson );
	}
	if ( 'rest' === transport ) {
		if ( httpOk ) {
			return done( body, messageOf( body ) );
		}
		return fail(
			codeOf( body ) || `http_${ status }`,
			messageOf( body ) || messages.error,
			body,
			Number( body?.data?.status ) || status
		);
	}
	if ( -1 === body || '-1' === body ) {
		return fail( 'invalid_nonce', messages.invalidNonce, null, 403 );
	}
	if ( 0 === body || '0' === body ) {
		return fail( 'invalid_action', messages.error, null, 400 );
	}
	if (
		body &&
		typeof body === 'object' &&
		typeof body.success === 'boolean'
	) {
		const payload = body.data === undefined ? null : body.data;
		const message = messageOf( payload );
		return body.success && httpOk
			? done( payload, message )
			: fail(
					codeOf( payload ) || 'ajax_error',
					message || messages.error,
					payload
				);
	}
	const legacyError =
		body &&
		typeof body === 'object' &&
		( 'error' === body.status || 'failure' === body.result );
	if ( legacyError || ! httpOk ) {
		return fail(
			codeOf( body ) || ( httpOk ? 'error' : `http_${ status }` ),
			messageOf( body ) || messages.error,
			body
		);
	}
	return done( body, messageOf( body ) );
}

function withQuery( url, query ) {
	if ( ! query ) {
		return url;
	}
	const params = toSearchParams( query ).toString();
	if ( ! params ) {
		return url;
	}
	return url + ( url.includes( '?' ) ? '&' : '?' ) + params;
}

function restUrl( path, root ) {
	if ( /^https?:/i.test( path ) ) {
		return path;
	}
	return (
		String( root ).replace( /\/?$/, '/' ) +
		String( path ).replace( /^\//, '' )
	);
}

function linkSignals( signal, timeout ) {
	if ( ! timeout && ! signal ) {
		return { signal: undefined, done: () => {} };
	}
	const controller = new AbortController();
	const abort = () => controller.abort( signal.reason );
	let timer = null;
	if ( signal ) {
		if ( signal.aborted ) {
			controller.abort( signal.reason );
		} else {
			signal.addEventListener( 'abort', abort, { once: true } );
		}
	}
	if ( timeout > 0 ) {
		timer = setTimeout( () => {
			const reason = new Error( 'timeout' );
			reason.name = 'TimeoutError';
			controller.abort( reason );
		}, timeout );
	}
	return {
		signal: controller.signal,
		done: () => {
			clearTimeout( timer );
			signal?.removeEventListener( 'abort', abort );
		},
	};
}

async function exchange( transport, url, init, parse, signal ) {
	let response;
	let text;
	try {
		response = await fetch( url, { ...init, signal } );
		text = await response.text();
	} catch ( error ) {
		let failure = 'network';
		if ( signal?.aborted ) {
			failure =
				'TimeoutError' === signal.reason?.name ? 'timeout' : 'aborted';
		}
		return { transport, status: response?.status || 0, failure, error };
	}
	const reply = { transport, status: response.status, parse };
	if ( 'text' === parse ) {
		return { ...reply, body: text };
	}
	try {
		return { ...reply, body: text ? JSON.parse( text ) : null };
	} catch {
		return { ...reply, body: text, invalid: true };
	}
}

function restInit( options, method, nonce ) {
	const headers = { Accept: 'application/json', ...options.headers };
	const init = { method, credentials: 'same-origin', headers };
	if ( nonce ) {
		headers[ 'X-WP-Nonce' ] = nonce;
	}
	let { data } = options;
	if ( isFormElement( data ) ) {
		data = new FormData( data );
	}
	if ( data === undefined || NO_BODY.includes( method ) ) {
		return { init, query: data };
	}
	if ( isFormData( data ) || data instanceof URLSearchParams ) {
		init.body = data;
	} else {
		headers[ 'Content-Type' ] = 'application/json';
		init.body = JSON.stringify( data );
	}
	return { init, query: undefined };
}

function ajaxInit( options, method ) {
	const init = {
		method,
		credentials: 'same-origin',
		headers: { Accept: 'application/json', ...options.headers },
	};
	let { data } = options;
	if ( isFormElement( data ) ) {
		data = new FormData( data );
	}
	const fields = {};
	if ( options.action ) {
		fields.action = options.action;
	}
	if ( options.nonce ) {
		fields[ options.nonceField || 'nonce' ] = options.nonce;
	}
	if ( NO_BODY.includes( method ) ) {
		return { init, query: [ data, fields ] };
	}
	let body;
	if ( isFormData( data ) || ( data && hasBlob( data ) ) ) {
		body = toFormData( data );
		for ( const [ name, value ] of Object.entries( fields ) ) {
			body.set( name, value );
		}
	} else {
		body = toSearchParams( data );
		for ( const [ name, value ] of Object.entries( fields ) ) {
			body.set( name, value );
		}
	}
	init.body = body;
	return { init, query: [] };
}

async function refreshRestNonce( ajaxUrl, signal ) {
	const reply = await exchange(
		'ajax',
		withQuery( ajaxUrl, { action: 'rest-nonce' } ),
		{ method: 'GET', credentials: 'same-origin' },
		'text',
		signal
	);
	const nonce = String( reply.body || '' ).trim();
	if ( 200 === reply.status && /^[a-f0-9]{10}$/i.test( nonce ) ) {
		setConfig( { restNonce: nonce } );
		return nonce;
	}
	return '';
}

/**
 * One request helper for every learner store. Never rejects for HTTP, WordPress or network
 * errors: check `result.ok` and show `result.message`. Only a missing `path`/`action`/`url`
 * throws (a programming error).
 *
 * - `path`: REST, with `X-WP-Nonce` from the server config. An expired nonce
 *   (`rest_cookie_invalid_nonce`) is renewed once through admin-ajax `rest-nonce` and retried.
 * - `action`: admin-ajax POST (`FormData` when any value is a file), `action` and the nonce
 *   field added. `wp_send_json_success/error` envelopes are unwrapped into `data`.
 * - `path` + `action`: REST first; admin-ajax when the route does not exist (404 `rest_no_route`,
 *   or a non-JSON answer from a site that blocks REST).
 * - `url`: fetch that URL and normalise the answer like admin-ajax.
 *
 * @param {RequestOptions} options Options.
 * @return {Promise<RequestResult>} Result.
 */
export async function request( options ) {
	const opts = options || {};
	if ( ! opts.path && ! opts.action && ! opts.url ) {
		throw new TypeError( 'request() needs a path, an action or a url.' );
	}
	const settings = getConfig();
	const parse = 'text' === opts.parse ? 'text' : 'json';
	const linked = linkSignals( opts.signal, Number( opts.timeout ) || 0 );
	try {
		if ( opts.path ) {
			const result = await rest( opts, settings, parse, linked.signal );
			const code = result.error?.code;
			const missing =
				404 === result.status &&
				( 'rest_no_route' === code || 'http_404' === code );
			if ( opts.action && ( missing || 'invalid_json' === code ) ) {
				return await ajax( opts, settings, parse, linked.signal );
			}
			return result;
		}
		return await ajax( opts, settings, parse, linked.signal );
	} finally {
		linked.done();
	}
}

async function rest( opts, settings, parse, signal, retried = false ) {
	const method = String(
		opts.method || ( opts.data === undefined ? 'GET' : 'POST' )
	).toUpperCase();
	const nonce =
		false === opts.nonce ? '' : opts.nonce || settings.restNonce || '';
	const { init, query } = restInit( opts, method, nonce );
	let url = restUrl( opts.path, opts.restRoot || settings.restRoot );
	url = withQuery( withQuery( url, query ), opts.query );
	const result = normalizeResponse(
		await exchange( 'rest', url, init, parse, signal ),
		settings.messages
	);
	if (
		! retried &&
		! opts.nonce &&
		nonce &&
		'rest_cookie_invalid_nonce' === result.error?.code
	) {
		const fresh = await refreshRestNonce(
			opts.ajaxUrl || settings.ajaxUrl,
			signal
		);
		if ( fresh ) {
			return rest( opts, getConfig(), parse, signal, true );
		}
	}
	return result;
}

async function ajax( opts, settings, parse, signal ) {
	const transport = opts.action ? 'ajax' : 'url';
	const url = opts.action
		? opts.url || opts.ajaxUrl || settings.ajaxUrl
		: opts.url;
	const method = String(
		opts.method ||
			( 'url' === transport && opts.data === undefined ? 'GET' : 'POST' )
	).toUpperCase();
	let target = url;
	let init;
	if ( 'url' === transport ) {
		init = {
			method,
			credentials: 'same-origin',
			headers: { Accept: 'application/json', ...opts.headers },
		};
		if ( opts.nonce ) {
			init.headers[ 'X-WP-Nonce' ] = opts.nonce;
		}
		const data = isFormElement( opts.data )
			? new FormData( opts.data )
			: opts.data;
		if ( NO_BODY.includes( method ) ) {
			target = withQuery( target, data );
		} else if ( isFormData( data ) || data instanceof URLSearchParams ) {
			init.body = data;
		} else if ( data !== undefined ) {
			init.body = hasBlob( data )
				? toFormData( data )
				: toSearchParams( data );
		}
	} else {
		const prepared = ajaxInit( opts, method );
		init = prepared.init;
		for ( const part of prepared.query ) {
			target = withQuery( target, part );
		}
	}
	target = withQuery( target, opts.query );
	return normalizeResponse(
		await exchange( transport, target, init, parse, signal ),
		settings.messages
	);
}

function abortError( result ) {
	const error = new Error( result.message );
	error.name = 'AbortError';
	error.code = result.error.code;
	return error;
}

/**
 * Same-origin REST request with the cookie nonce. Kept for the commerce modules; new code should
 * use `request()`. Resolves with the parsed body; rejects with an Error carrying `code`,
 * `status` and `data` (an `AbortError` when aborted).
 *
 * @param {string}      path             REST path, e.g. `ohmylms-commerce/v1/cart/items`.
 * @param {Object}      options          Request options.
 * @param {string}      [options.method] HTTP method.
 * @param {Object}      [options.data]   JSON body, or query arguments for GET.
 * @param {string}      [options.root]   REST root URL ending in `/` (default: server config).
 * @param {string}      [options.nonce]  `wp_rest` nonce (default: server config).
 * @param {AbortSignal} [options.signal] Abort signal.
 * @return {Promise<unknown>} Parsed body.
 */
export async function apiFetch(
	path,
	{ method = 'GET', data, root, nonce, signal } = {}
) {
	const result = await request( {
		path,
		method,
		data,
		restRoot: root,
		nonce,
		signal,
	} );
	if ( result.ok ) {
		return result.raw;
	}
	if ( [ 'aborted', 'timeout' ].includes( result.error.code ) ) {
		throw abortError( result );
	}
	const error = new Error( result.message );
	error.code = result.error.code;
	error.status = result.status;
	error.data = result.raw;
	throw error;
}

/**
 * POST to a legacy admin-ajax action and resolve with its parsed JSON answer, whatever the HTTP
 * status. Kept for the commerce modules; new code should use `request( { action } )`. Rejects
 * on invalid JSON (with `status`), network errors and aborts.
 *
 * @param {string}                          url  `admin-ajax.php` URL.
 * @param {FormData|URLSearchParams|Object} data Fields, including `action`.
 * @return {Promise<unknown>} Parsed body.
 */
export async function ajaxPost( url, data ) {
	const body =
		isFormData( data ) || data instanceof URLSearchParams
			? data
			: new URLSearchParams( data );
	const result = await request( { url, method: 'POST', data: body } );
	if ( result.error && 'invalid_json' === result.error.code ) {
		const error = new Error( result.message );
		error.status = result.status;
		throw error;
	}
	if ( result.error && 0 === result.status ) {
		throw abortError( result );
	}
	return result.raw;
}

// ---------------------------------------------------------------------------------------------
// Toasts

/** @typedef {'success'|'warning'|'danger'} ToastType */

/**
 * @typedef {Object} ToastOptions
 * @property {ToastType|'error'|'info'} [type]     `error` is `danger`, `info` is `success`. Default `success`.
 * @property {number}                   [duration] Milliseconds on screen; 0 keeps it until dismissed.
 *                                                 Default 5000, 8000 for `danger`.
 */

/**
 * @typedef {Object} ToastItem
 * @property {string}    id       Identifier.
 * @property {string}    message  Plain text.
 * @property {ToastType} type     Type.
 * @property {number}    duration Milliseconds, 0 for sticky.
 */

const TOAST_TYPES = {
	success: 'success',
	info: 'success',
	warning: 'warning',
	danger: 'danger',
	error: 'danger',
};

const ENTITIES = {
	amp: '&',
	lt: '<',
	gt: '>',
	quot: '"',
	apos: "'",
	nbsp: ' ',
	hellip: '…',
	ndash: '–',
	mdash: '—',
	lsquo: '‘',
	rsquo: '’',
	ldquo: '“',
	rdquo: '”',
};

/**
 * Text content of a server message that may contain markup or entities. Toasts never render HTML.
 *
 * @param {unknown} value Message.
 * @return {string} Plain text.
 */
export function plainText( value ) {
	if ( value === null || value === undefined ) {
		return '';
	}
	let text = String( value );
	if ( /[<&]/.test( text ) ) {
		text = text
			.replace( /<br\s*\/?>/gi, ' ' )
			.replace( /<[^>]*>/g, '' )
			.replace( /&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, ( entity, name ) => {
				if ( '#' === name[ 0 ] ) {
					const code =
						'x' === name[ 1 ].toLowerCase()
							? parseInt( name.slice( 2 ), 16 )
							: parseInt( name.slice( 1 ), 10 );
					return Number.isFinite( code ) &&
						code > 0 &&
						code < 0x110000
						? String.fromCodePoint( code )
						: entity;
				}
				return ENTITIES[ name.toLowerCase() ] ?? entity;
			} );
	}
	return text.replace( /\s+/g, ' ' ).trim();
}

/**
 * Normalise a toast request. Returns null when there is nothing to say.
 *
 * @param {string|Object} message   Message, or `{ message, type, duration, id }`.
 * @param {ToastOptions}  [options] Options.
 * @param {string}        [id]      Identifier when the input has none.
 * @return {ToastItem|null} Toast.
 */
export function toastItem( message, options = {}, id = '' ) {
	const input =
		message && typeof message === 'object'
			? message
			: { ...options, message };
	const text = plainText( input.message );
	if ( ! text ) {
		return null;
	}
	const type = TOAST_TYPES[ input.type ] || 'success';
	let duration = Number( input.duration );
	if (
		input.duration === undefined ||
		input.duration === null ||
		! Number.isFinite( duration ) ||
		duration < 0
	) {
		duration = 'danger' === type ? 8000 : 5000;
	}
	return { id: String( input.id || id ), message: text, type, duration };
}

let toastCount = 0;
const pendingToasts = [];

/**
 * Show a notice in the page's toast region (`#ohmylms-toast`, owned by `ohmylms/ui`).
 *
 * Dispatches the cancelable `ohmylms:toast` event on `document` (detail: the `ToastItem`); call
 * `preventDefault()` in a listener to render it yourself. Classic scripts can dispatch the same
 * event without importing this module. Toasts raised before `ohmylms/ui` loads are queued.
 *
 * @param {string}       message   Message; markup is stripped.
 * @param {ToastOptions} [options] Options.
 * @return {{id: string, dismiss: function(): void}|null} Handle, or null for an empty message.
 */
export function toast( message, options = {} ) {
	const item = toastItem(
		message,
		options,
		`ohmylms-toast-${ ++toastCount }`
	);
	if ( ! item || ! hasDocument() ) {
		return null;
	}
	const detail = { apiVersion, ...item, accepted: false };
	const event = new CustomEvent( 'ohmylms:toast', {
		bubbles: true,
		cancelable: true,
		detail,
	} );
	document.dispatchEvent( event );
	if ( ! event.defaultPrevented && ! detail.accepted ) {
		pendingToasts.push( item );
	}
	return { id: item.id, dismiss: () => dismissToast( item.id ) };
}

/**
 * Hide a toast (the current one when `id` is empty) or drop it from the queue.
 *
 * @param {string} [id] Toast identifier.
 */
export function dismissToast( id = '' ) {
	const index = pendingToasts.findIndex( ( item ) => item.id === id );
	if ( index > -1 ) {
		pendingToasts.splice( index, 1 );
	}
	if ( hasDocument() ) {
		document.dispatchEvent(
			new CustomEvent( 'ohmylms:toast-dismiss', {
				bubbles: true,
				detail: { apiVersion, id },
			} )
		);
	}
}

/**
 * Internal to `ohmylms/ui`: toasts raised before its listener existed.
 *
 * @return {ToastItem[]} Toasts, oldest first.
 */
export function takePendingToasts() {
	return pendingToasts.splice( 0 );
}

// ---------------------------------------------------------------------------------------------
// Accessibility and motion

/**
 * @return {boolean} The user asked for reduced motion.
 */
export function prefersReducedMotion() {
	return Boolean(
		typeof window !== 'undefined' &&
		window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches
	);
}

/**
 * Screen-reader announcement without a visible notice (result counts, saved states).
 * One visually hidden live region per politeness, shared by the page.
 *
 * @param {string}               message      Text.
 * @param {'polite'|'assertive'} [politeness] Politeness.
 */
export function announce( message, politeness = 'polite' ) {
	if ( ! hasDocument() || ! document.body ) {
		return;
	}
	const level = 'assertive' === politeness ? 'assertive' : 'polite';
	const id = `ohmylms-announce-${ level }`;
	let region = document.getElementById( id );
	if ( ! region ) {
		region = document.createElement( 'div' );
		region.id = id;
		region.className = 'screen-reader-text';
		region.setAttribute( 'aria-live', level );
		region.setAttribute( 'aria-atomic', 'true' );
		region.style.cssText =
			'position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);border:0;white-space:nowrap';
		document.body.append( region );
	}
	region.textContent = '';
	setTimeout( () => {
		region.textContent = plainText( message );
	}, 100 );
}

const FOCUSABLE = [
	'a[href]',
	'area[href]',
	'button:not([disabled])',
	'input:not([disabled]):not([type="hidden"])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'iframe',
	'audio[controls]',
	'video[controls]',
	'[contenteditable]:not([contenteditable="false"])',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

/**
 * Visible, enabled, tabbable descendants in document order.
 *
 * @param {Element|Document} container Container.
 * @return {HTMLElement[]} Elements.
 */
export function focusable( container ) {
	if ( ! container ) {
		return [];
	}
	return [ ...container.querySelectorAll( FOCUSABLE ) ].filter(
		( element ) =>
			! element.closest( '[inert],[hidden]' ) &&
			element.getAttribute( 'tabindex' ) !== '-1' &&
			( ! element.getClientRects || element.getClientRects().length > 0 )
	);
}

/**
 * Index Tab should move to inside a trap of `count` elements, or -1 to let the browser move.
 *
 * @param {number}  count   Focusable elements.
 * @param {number}  current Index of the focused element (-1 outside).
 * @param {boolean} back    Shift+Tab.
 * @return {number} Index.
 */
export function trapIndex( count, current, back ) {
	if ( count < 1 ) {
		return -1;
	}
	if ( current < 0 ) {
		return back ? count - 1 : 0;
	}
	if ( back && 0 === current ) {
		return count - 1;
	}
	if ( ! back && count - 1 === current ) {
		return 0;
	}
	return -1;
}

/**
 * Keep keyboard focus inside `container` (drawers, custom modals; native `<dialog>` modals trap
 * focus themselves). Returns a release function that restores focus to the previous element.
 *
 * @param {HTMLElement}                   container              Container.
 * @param {Object}                        [options]              Options.
 * @param {HTMLElement|string}            [options.initialFocus] Element or selector to focus first.
 * @param {boolean}                       [options.returnFocus]  Restore focus on release (default true).
 * @param {function(KeyboardEvent): void} [options.onEscape]     Escape handler.
 * @return {function(): void} Release.
 */
export function trapFocus(
	container,
	{ initialFocus, returnFocus = true, onEscape } = {}
) {
	const previous = document.activeElement;
	const onKeydown = ( event ) => {
		if ( 'Escape' === event.key && onEscape ) {
			event.preventDefault();
			onEscape( event );
			return;
		}
		if ( 'Tab' !== event.key ) {
			return;
		}
		const items = focusable( container );
		const next = trapIndex(
			items.length,
			items.indexOf( document.activeElement ),
			event.shiftKey
		);
		if ( items.length < 1 ) {
			event.preventDefault();
		} else if ( next > -1 ) {
			event.preventDefault();
			items[ next ].focus();
		}
	};
	const onFocusin = ( event ) => {
		if ( ! container.contains( event.target ) ) {
			( focusable( container )[ 0 ] || container ).focus();
		}
	};
	document.addEventListener( 'keydown', onKeydown, true );
	document.addEventListener( 'focusin', onFocusin );
	const first =
		typeof initialFocus === 'string'
			? container.querySelector( initialFocus )
			: initialFocus || focusable( container )[ 0 ];
	if ( ! first && ! container.hasAttribute( 'tabindex' ) ) {
		container.setAttribute( 'tabindex', '-1' );
	}
	( first || container ).focus( { preventScroll: true } );
	return () => {
		document.removeEventListener( 'keydown', onKeydown, true );
		document.removeEventListener( 'focusin', onFocusin );
		if ( returnFocus && previous?.focus && previous.isConnected ) {
			previous.focus( { preventScroll: true } );
		}
	};
}

let dialogCount = 0;

/**
 * Accessible confirmation on a native modal `<dialog>` (focus trapped by the browser, Escape and
 * backdrop click cancel, focus returns to the opener). Resolves false without a document.
 *
 * @param {string|Object}      options                Message, or options.
 * @param {string}             options.message        Question.
 * @param {string}             [options.title]        Heading.
 * @param {string}             [options.confirmLabel] Confirm button.
 * @param {string}             [options.cancelLabel]  Cancel button.
 * @param {'default'|'danger'} [options.tone]         `danger` focuses Cancel first.
 * @return {Promise<boolean>} True when confirmed.
 */
export function confirmDialog( options ) {
	const opts =
		typeof options === 'string' ? { message: options } : options || {};
	const messages = getConfig().messages;
	if ( ! hasDocument() ) {
		return Promise.resolve( false );
	}
	const id = `ohmylms-dialog-${ ++dialogCount }`;
	const dialog = document.createElement( 'dialog' );
	dialog.className = `ohmylms-dialog ohmylms-confirm-dialog${
		'danger' === opts.tone ? ' is-danger' : ''
	}`;
	const form = document.createElement( 'form' );
	form.method = 'dialog';
	if ( opts.title ) {
		const title = document.createElement( 'h2' );
		title.id = `${ id }-title`;
		title.className = 'ohmylms-dialog-title';
		title.textContent = plainText( opts.title );
		form.append( title );
		dialog.setAttribute( 'aria-labelledby', title.id );
	}
	const text = document.createElement( 'p' );
	text.id = `${ id }-message`;
	text.className = 'ohmylms-dialog-message';
	text.textContent = plainText( opts.message );
	form.append( text );
	dialog.setAttribute( 'aria-describedby', text.id );
	if ( ! opts.title ) {
		dialog.setAttribute( 'aria-labelledby', text.id );
	}
	const actions = document.createElement( 'div' );
	actions.className = 'ohmylms-dialog-actions';
	const button = ( value, label, className ) => {
		const element = document.createElement( 'button' );
		element.type = 'submit';
		element.value = value;
		element.className = className;
		element.textContent = label;
		actions.append( element );
		return element;
	};
	const cancel = button(
		'cancel',
		opts.cancelLabel || messages.cancel,
		'ohmylms-button ohmylms-button-secondary'
	);
	const confirm = button(
		'confirm',
		opts.confirmLabel || messages.confirm,
		'ohmylms-button'
	);
	form.append( actions );
	dialog.append( form );
	const opener = document.activeElement;
	document.body.append( dialog );
	return new Promise( ( resolve ) => {
		dialog.addEventListener(
			'close',
			() => {
				const confirmed = 'confirm' === dialog.returnValue;
				dialog.remove();
				if ( opener?.focus && opener.isConnected ) {
					opener.focus( { preventScroll: true } );
				}
				resolve( confirmed );
			},
			{ once: true }
		);
		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				dialog.close( 'cancel' );
			}
		} );
		dialog.showModal();
		( 'danger' === opts.tone ? cancel : confirm ).focus();
	} );
}

// ---------------------------------------------------------------------------------------------
// Small utilities

/**
 * Debounce with `cancel()` and `flush()`.
 *
 * @template {function(...unknown): void} F
 * @param {F}      callback Callback.
 * @param {number} wait     Milliseconds.
 * @return {F & {cancel: function(): void, flush: function(): void}} Debounced callback.
 */
export function debounce( callback, wait ) {
	let timer = null;
	let pending = null;
	const run = () => {
		timer = null;
		const args = pending;
		pending = null;
		if ( args ) {
			callback( ...args );
		}
	};
	const debounced = ( ...args ) => {
		pending = args;
		clearTimeout( timer );
		timer = setTimeout( run, wait );
	};
	debounced.cancel = () => {
		clearTimeout( timer );
		timer = null;
		pending = null;
	};
	debounced.flush = () => {
		clearTimeout( timer );
		run();
	};
	return debounced;
}

/**
 * Fire a jQuery event when jQuery is on the page, for add-ons that still listen with `.on()`.
 *
 * @param {string}           name     Event name.
 * @param {Array}            [args]   Extra handler arguments.
 * @param {EventTarget|null} [target] Target (default `document.body`).
 * @return {boolean} Whether jQuery was there to trigger it.
 */
export function jqueryTrigger( name, args = [], target = null ) {
	const jQuery = typeof window !== 'undefined' ? window.jQuery : undefined;
	if ( typeof jQuery !== 'function' || ! hasDocument() ) {
		return false;
	}
	jQuery( target || document.body ).trigger( name, args );
	return true;
}
