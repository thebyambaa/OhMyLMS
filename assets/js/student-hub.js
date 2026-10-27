/* global MutationObserver */
( () => {
	const hub = document.querySelector( '.ohmylms-student-hub' );
	if ( hub ) {
		// Remember section names only; course, class and invoice IDs stay out of storage.
		if ( hub.dataset.role === 'learner' ) {
			const destination = new URL( window.location.href );
			const locationKey = `ohmylms-hub-location:${ destination.origin }${ destination.pathname }`;
			try {
				const previous = JSON.parse(
					window.localStorage.getItem( locationKey ) || 'null'
				);
				const visible = Array.from(
					hub.querySelectorAll(
						'.oml-hub-nav a, .oml-hub-rail-foot > a'
					)
				).some(
					( link ) =>
						new URL( link.href ).searchParams.get(
							'hub_section'
						) === previous?.section
				);
				if (
					! destination.searchParams.has( 'hub_section' ) &&
					! destination.searchParams.has( 'ohmylms_invite' ) &&
					visible &&
					previous.section !== hub.dataset.section
				) {
					destination.searchParams.set(
						'hub_section',
						previous.section
					);
					if ( /^[a-z-]+$/.test( previous.view || '' ) ) {
						destination.searchParams.set(
							'hub_view',
							previous.view
						);
					}
					window.location.replace( destination.href );
					return;
				}
				window.localStorage.setItem(
					locationKey,
					JSON.stringify( {
						section: hub.dataset.section,
						view:
							hub.dataset.view === 'invoice'
								? 'orders'
								: hub.dataset.view,
					} )
				);
			} catch {
				// A blocked or invalid store leaves the server-selected landing page usable.
			}
		}
		const themes = [ 'system', 'light', 'dark' ];
		const storeKey = 'ohmylms-hub-theme';
		const buttons = hub.querySelectorAll( '[data-hub-theme-set]' );
		const applyTheme = ( theme ) => {
			hub.dataset.theme = theme;
			buttons.forEach( ( button ) =>
				button.setAttribute(
					'aria-pressed',
					button.dataset.hubThemeSet === theme ? 'true' : 'false'
				)
			);
		};
		let saved = 'light';
		try {
			const stored = window.localStorage.getItem( storeKey );
			saved = themes.includes( stored ) ? stored : saved;
		} catch {
			// Storage can be unavailable; the preference then lasts for this visit.
		}
		applyTheme( saved );
		buttons.forEach( ( button ) =>
			button.addEventListener( 'click', () => {
				applyTheme( button.dataset.hubThemeSet );
				try {
					window.localStorage.setItem(
						storeKey,
						button.dataset.hubThemeSet
					);
				} catch {
					// Not persisted when storage is blocked.
				}
			} )
		);
		const opener = hub.querySelector( '[data-hub-rail-open]' );
		const rail = hub.querySelector( '#oml-hub-rail' );
		const workspace = hub.querySelector( '.oml-hub-main' );
		const mobileRail = window.matchMedia( '(max-width: 800px)' );
		const setRail = ( open ) => {
			hub.classList.toggle( 'is-rail-open', open );
			opener?.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			if ( rail ) {
				rail.inert = mobileRail.matches && ! open;
				if ( rail.inert ) {
					rail.setAttribute( 'aria-hidden', 'true' );
				} else {
					rail.removeAttribute( 'aria-hidden' );
				}
			}
			if ( workspace ) {
				workspace.inert = mobileRail.matches && open;
			}
		};
		setRail( false );
		mobileRail.addEventListener( 'change', () => setRail( false ) );
		opener?.addEventListener( 'click', () => {
			setRail( true );
			rail?.querySelector( '[data-hub-rail-close]' )?.focus();
		} );
		hub.querySelector( '[data-hub-rail-close]' )?.addEventListener(
			'click',
			() => {
				setRail( false );
				opener?.focus();
			}
		);
		document.addEventListener( 'keydown', ( event ) => {
			if (
				event.key === 'Tab' &&
				mobileRail.matches &&
				hub.classList.contains( 'is-rail-open' )
			) {
				const controls = Array.from(
					rail?.querySelectorAll(
						'a[href], button:not([disabled]), summary, [tabindex="0"]'
					) || []
				).filter( ( control ) => control.getClientRects().length );
				const first = controls[ 0 ];
				const last = controls[ controls.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last?.focus();
				} else if (
					! event.shiftKey &&
					document.activeElement === last
				) {
					event.preventDefault();
					first?.focus();
				}
			}
			if (
				event.key === 'Escape' &&
				hub.classList.contains( 'is-rail-open' )
			) {
				setRail( false );
				opener?.focus();
			}
		} );
	}
	document.querySelectorAll( '[data-hub-sortable]' ).forEach( ( list ) => {
		let dragged;
		const announce = () => {
			const status = document.querySelector( '[data-hub-sort-status]' );
			if ( status ) {
				status.textContent = status.dataset.message;
			}
		};
		list.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-move]' );
			if ( ! button ) {
				return;
			}
			const row = button.closest( '[data-sort-row]' );
			const target =
				button.dataset.move === 'up'
					? row.previousElementSibling
					: row.nextElementSibling;
			if ( target ) {
				if ( button.dataset.move === 'up' ) {
					target.before( row );
				} else {
					target.after( row );
				}
				button.focus();
				announce();
			}
		} );
		list.addEventListener( 'dragstart', ( event ) => {
			dragged = event.target.closest( '[data-sort-row]' );
			if ( dragged ) {
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData( 'text/plain', 'reorder' );
				dragged.classList.add( 'is-dragging' );
			}
		} );
		list.addEventListener( 'dragover', ( event ) => {
			const row = event.target.closest( '[data-sort-row]' );
			if ( ! dragged || ! row || row === dragged ) {
				return;
			}
			event.preventDefault();
			const bounds = row.getBoundingClientRect();
			if ( event.clientY < bounds.top + bounds.height / 2 ) {
				row.before( dragged );
			} else {
				row.after( dragged );
			}
		} );
		list.addEventListener( 'drop', ( event ) => {
			if ( dragged ) {
				event.preventDefault();
				announce();
			}
		} );
		list.addEventListener( 'dragend', () => {
			dragged?.classList.remove( 'is-dragging' );
			dragged = null;
		} );
	} );
	const themePicker = document.querySelector(
		'.oml-hub-personalize [data-dashboard-theme]'
	);
	const cards = document.querySelector( '[data-hub-theme]' );
	if ( themePicker && cards ) {
		new MutationObserver( () => {
			cards.dataset.hubTheme = themePicker.dataset.dashboardTheme;
		} ).observe( themePicker, {
			attributes: true,
			attributeFilter: [ 'data-dashboard-theme' ],
		} );
	}
	document.querySelectorAll( '[data-preview-size]' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const preview = document.querySelector( '[data-hub-preview]' );
			if ( preview ) {
				preview.style.width =
					button.dataset.previewSize === 'mobile' ? '390px' : '100%';
			}
		} );
	} );
	document.querySelectorAll( '[data-hub-dirty]' ).forEach( ( form ) => {
		const save = form.querySelector( '[data-hub-save]' );
		const snapshot = () =>
			JSON.stringify(
				Array.from( form.elements )
					.filter(
						( field ) => field.name && field.type !== 'hidden'
					)
					.map( ( field ) =>
						field.type === 'checkbox' || field.type === 'radio'
							? field.checked
							: field.value
					)
			);
		const initial = snapshot();
		const update = () => {
			if ( save ) {
				save.disabled = snapshot() === initial;
			}
		};
		form.addEventListener( 'input', update );
		form.addEventListener( 'change', update );
		window.addEventListener( 'pageshow', update );
	} );
	document.querySelectorAll( '[data-hub-invitation]' ).forEach( ( form ) => {
		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			const input = form.querySelector( '[name="invitation"]' );
			const button = form.querySelector( '[type="submit"]' );
			const status = form.querySelector( '[data-invitation-status]' );
			let token = input.value.trim();
			try {
				token =
					new URL( token ).searchParams.get( 'ohmylms_invite' ) || '';
			} catch {
				// A bare invitation code is accepted by the same existing service.
			}
			button.disabled = true;
			status.textContent = '';
			try {
				const response = await fetch( form.dataset.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': form.dataset.nonce,
					},
					body: JSON.stringify( { token } ),
				} );
				const result = await response.json();
				if ( ! response.ok ) {
					throw new Error( result.message || form.dataset.error );
				}
				status.textContent = form.dataset.success;
				input.value = '';
			} catch ( error ) {
				status.textContent = error.message || form.dataset.error;
			} finally {
				button.disabled = false;
			}
		} );
	} );
	// Keep one real form: without JavaScript both steps and Save remain usable.
	document.querySelectorAll( '[data-family-choices]' ).forEach( ( form ) => {
		const editor = form.closest( '[data-family-editor]' );
		const steps = Array.from(
			form.querySelectorAll( '[data-family-step]' )
		);
		const status = form.querySelector( '[data-family-step-status]' );
		const grade = form.querySelector( '[name="grade"]' );
		const next = form.querySelector( '[data-family-next]' );
		let current = 0;
		const show = ( index, focus = false ) => {
			current = index;
			steps.forEach( ( step, position ) => {
				step.hidden = position !== index;
			} );
			status.textContent =
				index === 0 ? form.dataset.stepOne : form.dataset.stepTwo;
			if ( focus ) {
				steps[ index ].querySelector( 'h3' ).focus();
			}
		};
		const reset = () => {
			form.reset();
			show( 0 );
		};
		const cancel = () => {
			reset();
			editor.open = false;
			editor.querySelector( 'summary' ).focus();
		};
		form.querySelectorAll(
			'[data-family-next], [data-family-back], [data-family-cancel], [data-family-step-status]'
		).forEach( ( control ) => {
			control.hidden = false;
		} );
		next.addEventListener( 'click', () => {
			if ( grade.reportValidity() ) {
				show( 1, true );
			}
		} );
		form.querySelector( '[data-family-back]' ).addEventListener(
			'click',
			() => show( 0, true )
		);
		form.querySelector( '[data-family-cancel]' ).addEventListener(
			'click',
			cancel
		);
		form.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				event.stopPropagation();
				cancel();
			}
			if ( event.key === 'Enter' && event.target === grade ) {
				event.preventDefault();
				next.click();
			}
		} );
		form.addEventListener( 'submit', ( event ) => {
			if ( current === 0 ) {
				event.preventDefault();
				next.click();
			}
		} );
		editor.addEventListener( 'toggle', () => {
			if ( ! editor.open ) {
				reset();
			}
		} );
		show( 0 );
	} );
} )();
