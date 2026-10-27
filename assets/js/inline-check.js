/**
 * Inline question checks in lessons, guest local history and the post-login claim prompt.
 *
 * Answers are graded by the server (the page never contains the key). Guests get a
 * pseudonymous credential kept in localStorage with receipts of their answers; after
 * logging in they are asked whether to attach those server-side results to the account.
 * Local data is cleared only after a successful claim. Checks never affect completion.
 */
( function () {
	'use strict';
	var config = window.ohmylmsInlineCheck || {};
	var i18n = config.i18n || {};
	var STORE = 'ohmylmsGuestPractice';

	/** Render math in content added after page load (uses the site's KaTeX auto-render when present). */
	function typeset( node ) {
		if ( ! node || typeof window.renderMathInElement !== 'function' )
			return;
		try {
			window.renderMathInElement( node, {
				delimiters: [
					{ left: '$$', right: '$$', display: true },
					{ left: '\\(', right: '\\)', display: false },
					{ left: '\\[', right: '\\]', display: true },
				],
				throwOnError: false,
			} );
		} catch ( error ) {
			/* leave the source text visible */
		}
	}
	window.ohmylmsTypeset = typeset;

	function readStore() {
		try {
			return JSON.parse( window.localStorage.getItem( STORE ) || 'null' );
		} catch ( error ) {
			return null;
		}
	}
	function writeStore( value ) {
		try {
			if ( value )
				window.localStorage.setItem( STORE, JSON.stringify( value ) );
			else window.localStorage.removeItem( STORE );
			return true;
		} catch ( error ) {
			return false;
		}
	}
	function request( path, body, guestToken ) {
		var headers = { 'Content-Type': 'application/json' };
		if ( config.nonce ) headers[ 'X-WP-Nonce' ] = config.nonce;
		if ( guestToken ) headers[ 'X-OhMyLMS-Guest' ] = guestToken;
		return fetch( config.root + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: JSON.stringify( body || {} ),
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					var error = new Error(
						( data && data.message ) || 'request failed'
					);
					error.code = data && data.code;
					error.status = response.status;
					throw error;
				}
				return data;
			} );
		} );
	}
	window.ohmylmsPracticeRequest = request;

	/** Guest credential for anonymous learners (created on first answer). */
	function guestToken() {
		if ( config.loggedIn ) return Promise.resolve( null );
		var store = readStore();
		if (
			store &&
			store.token &&
			( ! store.expires ||
				Date.parse( store.expires.replace( ' ', 'T' ) + 'Z' ) >
					Date.now() )
		)
			return Promise.resolve( store.token );
		return request( 'practice/guest', {} ).then( function ( guest ) {
			var saved = writeStore( {
				token: guest.token,
				expires: guest.expires_at,
				receipts: [],
			} );
			if ( ! saved ) notice( document.body, i18n.storage );
			return guest.token;
		} );
	}
	window.ohmylmsGuestToken = guestToken;

	function remember( result ) {
		if ( config.loggedIn ) return;
		var store = readStore();
		if ( ! store ) return;
		store.receipts = ( store.receipts || [] )
			.concat( [
				{
					receipt: result.receipt,
					question: result.question_uuid,
					correct: !! result.correct,
					at: Date.now(),
				},
			] )
			.slice( -500 );
		writeStore( store );
	}
	window.ohmylmsRememberPractice = remember;

	function notice( container, text ) {
		if ( ! text || ! container ) return;
		var element = document.createElement( 'p' );
		element.className = 'ohmylms-inline-check-notice';
		element.textContent = text;
		container.appendChild( element );
	}

	/** Mirror PHP's parsing of attempt[0][quiz_question][q][] and [q][key] names. */
	function collect( form ) {
		var list = [];
		var keyed = {};
		var isKeyed = false;
		Array.prototype.forEach.call( form.elements, function ( field ) {
			var match =
				field.name &&
				field.name.match( /\[quiz_question\]\[\d+\](.*)$/ );
			if ( ! match || field.disabled ) return;
			if (
				( field.type === 'radio' || field.type === 'checkbox' ) &&
				! field.checked
			)
				return;
			var key = match[ 1 ].match( /^\[([^\]]*)\]/ );
			if ( key && key[ 1 ] !== '' ) {
				isKeyed = true;
				keyed[ key[ 1 ] ] = field.value;
			} else list.push( field.value );
		} );
		return isKeyed ? keyed : list;
	}

	function showFeedback( form, result ) {
		var box = form.querySelector( '.ohmylms-inline-check-feedback' );
		box.innerHTML = '';
		var verdict = document.createElement( 'p' );
		verdict.className = result.correct
			? 'ohmylms-inline-correct'
			: 'ohmylms-inline-incorrect';
		verdict.textContent = result.correct ? i18n.correct : i18n.incorrect;
		box.appendChild( verdict );
		var feedback = result.feedback || {};
		( feedback.correct_options || [] ).forEach( function ( token ) {
			var input = form.querySelector( 'input[value="' + token + '"]' );
			var label = input && input.closest( 'label' );
			if ( label ) label.classList.add( 'ohmylms-inline-answer' );
		} );
		if (
			! result.correct &&
			feedback.expected &&
			feedback.expected.length
		) {
			var expected = document.createElement( 'p' );
			expected.textContent =
				i18n.answer + ' ' + feedback.expected.join( ', ' );
			box.appendChild( expected );
		}
		if ( feedback.explanation ) {
			var explanation = document.createElement( 'div' );
			explanation.className = 'ohmylms-inline-explanation';
			explanation.innerHTML = feedback.explanation;
			box.appendChild( explanation );
		}
		if ( ! config.loggedIn ) {
			var prompt = document.createElement( 'p' );
			prompt.className = 'ohmylms-inline-save';
			prompt.appendChild( document.createTextNode( i18n.save + ' ' ) );
			var login = document.createElement( 'a' );
			login.href = config.loginUrl;
			login.textContent = i18n.login;
			prompt.appendChild( login );
			if ( config.registerUrl ) {
				prompt.appendChild( document.createTextNode( ' · ' ) );
				var register = document.createElement( 'a' );
				register.href = config.registerUrl;
				register.textContent = i18n.register;
				prompt.appendChild( register );
			}
			box.appendChild( prompt );
		}
	}

	document
		.querySelectorAll( 'form.ohmylms-inline-check' )
		.forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				var button = form.querySelector(
					'.ohmylms-inline-check-submit'
				);
				if ( button ) button.disabled = true;
				guestToken()
					.then( function ( token ) {
						return request(
							'practice/inline',
							{
								token: form.dataset.token,
								response: collect( form ),
							},
							token
						);
					} )
					.then( function ( result ) {
						remember( result );
						showFeedback( form, result );
						typeset(
							form.querySelector(
								'.ohmylms-inline-check-feedback'
							)
						);
					} )
					.catch( function ( error ) {
						if ( error.status === 401 && ! config.loggedIn )
							writeStore( null );
						var box = form.querySelector(
							'.ohmylms-inline-check-feedback'
						);
						box.textContent = error.message || i18n.error;
					} )
					.finally( function () {
						if ( button ) button.disabled = false;
					} );
			} );
		} );

	/** After login: offer to attach this device's guest answers to the account. */
	function offerClaim() {
		if ( ! config.loggedIn ) return;
		var store = readStore();
		if ( ! store || ! store.token || ! ( store.receipts || [] ).length )
			return;
		var banner = document.createElement( 'div' );
		banner.className = 'ohmylms-claim-banner';
		banner.setAttribute( 'role', 'dialog' );
		banner.setAttribute( 'aria-live', 'polite' );
		banner.style.cssText =
			'position:fixed;bottom:16px;left:16px;right:16px;max-width:520px;margin:auto;background:#fff;border:1px solid #ccc;padding:16px;z-index:99999;box-shadow:0 4px 16px rgba(0,0,0,.15)';
		var text = document.createElement( 'p' );
		text.textContent = ( i18n.claim || '' ).replace(
			'%d',
			String( store.receipts.length )
		);
		var yes = document.createElement( 'button' );
		yes.type = 'button';
		yes.className = 'ohmylms-button';
		yes.textContent = i18n.claimYes;
		var no = document.createElement( 'button' );
		no.type = 'button';
		no.className = 'ohmylms-button outline';
		no.textContent = i18n.claimNo;
		no.style.marginLeft = '8px';
		banner.appendChild( text );
		banner.appendChild( yes );
		banner.appendChild( no );
		document.body.appendChild( banner );
		no.addEventListener( 'click', function () {
			banner.remove();
		} );
		yes.addEventListener( 'click', function () {
			yes.disabled = true;
			request( 'practice/claim', { guest_token: store.token } )
				.then( function () {
					writeStore( null );
					text.textContent = i18n.claimed;
				} )
				.catch( function ( error ) {
					// Already claimed or expired: the local copy is no longer useful.
					if (
						error.status === 409 ||
						error.status === 410 ||
						error.status === 404
					)
						writeStore( null );
					text.textContent = i18n.claimFailed;
				} )
				.finally( function () {
					yes.remove();
					no.textContent = 'OK';
				} );
		} );
	}
	if ( document.readyState === 'loading' )
		document.addEventListener( 'DOMContentLoaded', offerClaim );
	else offerClaim();
} )();
