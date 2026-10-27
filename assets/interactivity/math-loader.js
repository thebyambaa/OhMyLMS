/** Detect dynamic math content; leave native fields usable if the local runtime fails. */
( function () {
	let attempted = false;
	const scan = () => {
		if (
			document.readyState === 'loading' ||
			attempted ||
			! window.ohmylmsMath?.enabled ||
			window.OhMyLMSMath
		) {
			return;
		}
		if (
			! document.querySelector( 'input.ohmylms-expression-input' ) &&
			! document.body.textContent.includes( '[[ohmylms-math:latex:' ) &&
			! [
				...document.querySelectorAll(
					'[data-ohmylms-latex],.ohmylms-structured-question'
				),
			].some( ( region ) => /[$\\]/.test( region.textContent ) )
		) {
			return;
		}
		attempted = true;
		if (
			document.querySelector(
				'script[src="' + window.ohmylmsMath.runtime + '"]'
			)
		) {
			observer.disconnect();
			return;
		}
		const style = document.createElement( 'link' );
		style.rel = 'stylesheet';
		style.href = window.ohmylmsMath.style;
		document.head.append( style );
		const script = document.createElement( 'script' );
		script.src = window.ohmylmsMath.runtime;
		script.onload = () => observer.disconnect();
		document.head.append( script );
	};
	const observer = new MutationObserver( scan );
	observer.observe( document.body, { childList: true, subtree: true } );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', scan, { once: true } );
	} else {
		scan();
	}
	window.addEventListener( 'pagehide', () => observer.disconnect(), {
		once: true,
	} );
} )();
