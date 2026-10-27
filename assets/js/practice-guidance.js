( () => {
	const refresh = async ( root ) => {
		if ( ! root || ! root.isConnected ) {
			return;
		}
		if ( root.dataset.guidanceLoading ) {
			root.dataset.guidanceAgain = 'true';
			return;
		}
		root.dataset.guidanceLoading = 'true';
		root.setAttribute( 'aria-busy', 'true' );
		const button = root.querySelector( '[data-guidance-refresh]' );
		button.disabled = true;
		try {
			const response = await fetch( root.dataset.guidanceEndpoint, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': root.dataset.guidanceNonce },
			} );
			const data = await response.json();
			if ( ! response.ok ) {
				throw new Error( data.message || root.dataset.guidanceError );
			}
			// This fragment is rendered by our context-escaped server template.
			const fragment = new DOMParser().parseFromString(
				data.html,
				'text/html'
			);
			const replacement = fragment.querySelector(
				'.oml-practice-guidance'
			);
			if ( ! replacement ) {
				throw new Error( root.dataset.guidanceError );
			}
			const focused = root.contains( window.document.activeElement );
			root.replaceWith( replacement );
			window.document
				.querySelectorAll( '[data-practice-skill]' )
				.forEach( ( label ) => {
					const skill = data.skills.find(
						( row ) =>
							row.term_id ===
							Number( label.dataset.practiceSkill )
					);
					if (
						skill &&
						Number( label.dataset.practiceCourse ) ===
							data.course_id
					) {
						label.textContent = `${ data.practice_label } · ${ skill.label }`;
					}
				} );
			if ( focused ) {
				replacement.querySelector( '[data-guidance-refresh]' ).focus();
			}
			if ( root.dataset.guidanceAgain ) {
				await refresh( replacement );
			}
		} catch ( error ) {
			root.querySelector( '[data-guidance-message]' ).textContent =
				error.message || root.dataset.guidanceError;
			button.disabled = false;
			delete root.dataset.guidanceLoading;
			root.removeAttribute( 'aria-busy' );
		}
	};
	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-guidance-refresh]' );
		if ( button ) {
			refresh( button.closest( '.oml-practice-guidance' ) );
		}
	} );
	document.addEventListener( 'ohmylms:practice-complete', ( event ) => {
		document
			.querySelectorAll( '.oml-practice-guidance' )
			.forEach( ( root ) => {
				if (
					Number( root.dataset.guidanceCourse ) ===
					event.detail.courseId
				) {
					refresh( root );
				}
			} );
	} );
} )();
