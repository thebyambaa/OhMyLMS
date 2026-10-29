/* Opt-in, administrator-only diagnostics. No settings or content are written. */
( function () {
	const tasks = [];
	const observers = [];
	let shellReady = null;
	if (
		window.PerformanceObserver?.supportedEntryTypes.includes( 'longtask' )
	) {
		const observer = new window.PerformanceObserver( ( list ) => {
			tasks.push(
				...list.getEntries().map( ( entry ) => ( {
					start: entry.startTime,
					duration: entry.duration,
				} ) )
			);
		} );
		observer.observe( { type: 'longtask', buffered: true } );
		observers.push( observer );
	}
	function mount() {
		const panel = document.createElement( 'details' );
		panel.id = 'ohmylms-benchmark';
		const title = document.createElement( 'summary' );
		title.textContent = 'Dashboard load measurements (read-only)';
		const output = document.createElement( 'pre' );
		output.id = 'ohmylms-benchmark-results';
		panel.append( title, output );
		document.querySelector( '#wpbody-content' ).append( panel );
		const rounded = ( value ) => Math.round( value * 10 ) / 10;
		function markReady() {
			if (
				Array.from(
					document.querySelectorAll( '#wpbody-content h1' )
				).some(
					( heading ) => heading.textContent.trim() === 'Overview'
				)
			) {
				shellReady = window.performance.now();
				mutation.disconnect();
			}
		}
		const mutation = new window.MutationObserver( markReady );
		mutation.observe( document.querySelector( '#wpbody-content' ), {
			subtree: true,
			childList: true,
		} );
		markReady();
		function sample() {
			const navigation =
				window.performance.getEntriesByType( 'navigation' )[ 0 ];
			const resources = window.performance.getEntriesByType( 'resource' );
			output.textContent = JSON.stringify(
				{
					sampleWindowMs: rounded( window.performance.now() ),
					ttfbMs: rounded(
						navigation.responseStart - navigation.requestStart
					),
					responseStartMs: rounded( navigation.responseStart ),
					dnsMs: rounded(
						navigation.domainLookupEnd -
							navigation.domainLookupStart
					),
					connectMs: rounded(
						navigation.connectEnd - navigation.connectStart
					),
					domContentLoadedMs: rounded(
						navigation.domContentLoadedEventEnd
					),
					loadMs: rounded( navigation.loadEventEnd ),
					dashboardHeadingMs:
						shellReady === null ? null : rounded( shellReady ),
					paints: window.performance
						.getEntriesByType( 'paint' )
						.map( ( entry ) => ( {
							name: entry.name,
							ms: rounded( entry.startTime ),
						} ) ),
					longTaskCount: tasks.length,
					longTaskMs: rounded(
						tasks.reduce( ( sum, task ) => sum + task.duration, 0 )
					),
					slowResources: resources
						.filter( ( resource ) => resource.duration > 500 )
						.map( ( resource ) => ( {
							path: new URL( resource.name ).pathname,
							kind: resource.initiatorType,
							startMs: rounded( resource.startTime ),
							ms: rounded( resource.duration ),
						} ) ),
					scripts: resources
						.filter(
							( resource ) => resource.initiatorType === 'script'
						)
						.map( ( resource ) => ( {
							path: new URL( resource.name ).pathname,
							encodedBytes: resource.encodedBodySize,
							transferBytes: resource.transferSize,
							ms: rounded( resource.duration ),
						} ) ),
				},
				null,
				2
			);
		}
		// Include asynchronously rendered screens in a bounded window after load.
		function finish() {
			window.setTimeout( () => {
				sample();
				mutation.disconnect();
				observers.forEach( ( observer ) => observer.disconnect() );
			}, 5000 );
		}
		if ( document.readyState === 'complete' ) {
			finish();
		} else {
			window.addEventListener( 'load', finish, { once: true } );
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mount, { once: true } );
	} else {
		mount();
	}
} )();
