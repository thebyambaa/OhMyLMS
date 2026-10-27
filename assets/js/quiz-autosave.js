/**
 * Autosave and resume for versioned quiz attempts.
 *
 * Each changed question is sent to the server (newest-wins sequence numbers). On page
 * load, answers the server already received are restored, so a refresh or a closed tab
 * never loses work. Deadline finalization grades only these server-received answers.
 */
( function () {
	'use strict';
	var config = window.ohmylmsQuizAutosave || {};
	var i18n = config.i18n || {};
	var mounted = new WeakSet();
	function mount( root ) {
		if ( mounted.has( root ) ) return;
		if ( ! root || ! root.dataset.autosave || ! window.fetch ) return;
		var endpoint = root.dataset.autosave;
		var resumeUrl = endpoint.replace( /\/responses\/?$/, '' );
		var form = null;
		root.querySelectorAll( 'form' ).forEach( function ( candidate ) {
			if ( candidate.querySelector( '.ohmylms-quiz-form' ) )
				form = candidate;
		} );
		if ( ! form ) return;
		mounted.add( root );

		var lastSent = {};
		var timers = {};
		var pending = {};
		var closed = false;
		var status = document.createElement( 'span' );
		status.className = 'ohmylms-autosave-status';
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		var footer =
			root.querySelector( '.ohmylms-paper-progress' ) ||
			root.querySelector(
				'.ohmylms-quiz-footer .ohmylms-footer-wrapper'
			) ||
			form;
		footer.appendChild( status );

		function setStatus( text ) {
			status.textContent = text || '';
		}

		function questionIds() {
			var ids = {};
			Array.prototype.forEach.call( form.elements, function ( field ) {
				var match =
					field.name &&
					field.name.match( /\[quiz_question\]\[(\d+)\]/ );
				if ( match ) ids[ match[ 1 ] ] = true;
			} );
			return Object.keys( ids );
		}

		/** Mirror PHP's parsing of attempt[a][quiz_question][q][] and [q][key] fields. */
		function collect( questionId ) {
			var prefix = '[quiz_question][' + questionId + ']';
			var list = [];
			var keyed = {};
			var isKeyed = false;
			Array.prototype.forEach.call( form.elements, function ( field ) {
				if (
					! field.name ||
					field.name.indexOf( prefix ) === -1 ||
					field.disabled
				)
					return;
				if (
					( field.type === 'radio' || field.type === 'checkbox' ) &&
					! field.checked
				)
					return;
				var rest = field.name.slice(
					field.name.indexOf( prefix ) + prefix.length
				);
				var key = rest.match( /^\[([^\]]*)\]/ );
				if ( key && key[ 1 ] !== '' ) {
					isKeyed = true;
					keyed[ key[ 1 ] ] =
						field.tagName === 'SELECT' && field.multiple
							? Array.from(
									field.selectedOptions,
									function ( option ) {
										return option.value;
									}
								)
							: field.value;
				} else {
					list.push( field.value );
				}
			} );
			return isKeyed ? keyed : list;
		}

		function send( questionId, attempt ) {
			if ( closed ) return;
			var response = collect( questionId );
			var body = JSON.stringify( {
				question_id: Number( questionId ),
				response: response,
				sequence: Date.now(),
			} );
			setStatus( i18n.saving );
			pending[ questionId ] = true;
			fetch( endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce || '',
				},
				body: body,
			} )
				.then( function ( result ) {
					return result.json().then( function ( data ) {
						return { ok: result.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					if ( ! result.ok ) {
						if (
							result.data &&
							result.data.code === 'quiz_deadline_passed'
						) {
							closed = true;
							setStatus( i18n.closed );
							return;
						}
						throw new Error(
							( result.data && result.data.message ) ||
								'save failed'
						);
					}
					lastSent[ questionId ] = JSON.stringify( response );
					delete pending[ questionId ];
					if ( ! Object.keys( pending ).length )
						setStatus( i18n.saved );
				} )
				.catch( function () {
					setStatus( i18n.offline );
					var delay = Math.min(
						30000,
						2000 * Math.pow( 2, attempt || 0 )
					);
					clearTimeout( timers[ questionId ] );
					timers[ questionId ] = setTimeout( function () {
						send( questionId, ( attempt || 0 ) + 1 );
					}, delay );
				} );
		}

		function schedule( questionId ) {
			clearTimeout( timers[ questionId ] );
			timers[ questionId ] = setTimeout( function () {
				send( questionId, 0 );
			}, 500 );
		}

		function saveChanged() {
			questionIds().forEach( function ( questionId ) {
				if (
					JSON.stringify( collect( questionId ) ) !==
					( lastSent[ questionId ] || JSON.stringify( [] ) )
				)
					schedule( questionId );
			} );
		}

		// Capture before question widgets stop propagation of their input events.
		form.addEventListener(
			'change',
			function ( event ) {
				var match =
					event.target.name &&
					event.target.name.match( /\[quiz_question\]\[(\d+)\]/ );
				if ( match ) schedule( match[ 1 ] );
			},
			true
		);
		form.addEventListener(
			'input',
			function ( event ) {
				var match =
					event.target.name &&
					event.target.name.match( /\[quiz_question\]\[(\d+)\]/ );
				if ( match ) schedule( match[ 1 ] );
			},
			true
		);
		// Drag-and-drop questions (matching, reorder) update hidden fields without input events.
		[ 'drop', 'dragend', 'touchend' ].forEach( function ( type ) {
			root.addEventListener(
				type,
				function () {
					setTimeout( saveChanged, 60 );
				},
				true
			);
		} );
		window.addEventListener( 'online', saveChanged );
		root.addEventListener(
			'ohmylms:quiz-unmounted',
			function () {
				closed = true;
				Object.keys( timers ).forEach( function ( questionId ) {
					clearTimeout( timers[ questionId ] );
				} );
				window.removeEventListener( 'online', saveChanged );
				mounted.delete( root );
			},
			{ once: true }
		);

		/** Restore answers the server already has, then remember them as sent. */
		function restore( responses ) {
			Object.keys( responses || {} ).forEach( function ( questionId ) {
				var response =
					responses[ questionId ] && responses[ questionId ].response;
				if ( ! response ) return;
				var prefix = '[quiz_question][' + questionId + ']';
				var fields = Array.prototype.filter.call(
					form.elements,
					function ( field ) {
						return (
							field.name && field.name.indexOf( prefix ) !== -1
						);
					}
				);
				if ( Array.isArray( response ) ) {
					var texts = fields.filter( function ( field ) {
						return (
							field.type === 'text' ||
							field.tagName === 'TEXTAREA' ||
							field.type === 'number'
						);
					} );
					var reorder = root.querySelector(
						'.reorder-option[data-question-id="' + questionId + '"]'
					);
					if ( reorder ) {
						var container = reorder.parentNode;
						response.forEach( function ( token ) {
							var option = container.querySelector(
								'.reorder-option[data-option-id="' +
									token +
									'"]'
							);
							if ( option ) container.appendChild( option );
						} );
					} else if ( texts.length ) {
						texts.forEach( function ( field, index ) {
							if ( response[ index ] !== undefined )
								field.value = response[ index ];
						} );
					} else {
						fields.forEach( function ( field ) {
							if (
								( field.type === 'radio' ||
									field.type === 'checkbox' ) &&
								response.indexOf( field.value ) !== -1
							)
								field.checked = true;
						} );
					}
				} else {
					Object.keys( response ).forEach( function ( definition ) {
						var input = fields.filter( function ( field ) {
							return (
								field.name.indexOf( '[' + definition + ']' ) !==
								-1
							);
						} )[ 0 ];
						if ( ! input ) return;
						if ( input.tagName === 'SELECT' && input.multiple ) {
							var selections = Array.isArray(
								response[ definition ]
							)
								? response[ definition ]
								: [ response[ definition ] ];
							Array.from( input.options ).forEach(
								function ( option ) {
									option.selected =
										selections.indexOf( option.value ) !==
										-1;
								}
							);
						} else {
							input.value = response[ definition ];
						}
						var box = root.querySelector(
							'.option-drop-box[data-definition-id="' +
								definition +
								'"]'
						);
						var option = root.querySelector(
							'.matching-option[data-option-id="' +
								response[ definition ] +
								'"]'
						);
						if ( box && option ) {
							var clone = option.cloneNode( true );
							clone.classList.add( 'dropped' );
							clone.setAttribute( 'draggable', 'false' );
							box.innerHTML = '';
							box.appendChild( clone );
						}
					} );
				}
				lastSent[ questionId ] = JSON.stringify(
					collect( questionId )
				);
				form.dispatchEvent(
					new CustomEvent( 'ohmylms:answer-restored', {
						detail: { questionId: questionId },
					} )
				);
			} );
			if ( Object.keys( responses || {} ).length )
				setStatus( i18n.saved );
		}

		fetch( resumeUrl, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.nonce || '' },
		} )
			.then( function ( result ) {
				return result.ok ? result.json() : null;
			} )
			.then( function ( data ) {
				if ( data && ! closed ) restore( data.responses );
			} )
			.catch( function () {} );
	}
	document
		.querySelectorAll( '.ohmylms-quiz[data-attempt-engine="versioned"]' )
		.forEach( mount );
	document.addEventListener( 'ohmylms:quiz-mounted', function ( event ) {
		mount( event.target );
	} );
} )();
