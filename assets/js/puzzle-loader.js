/** Load puzzle controls only when a puzzle appears, including fetched questions. */
( function () {
	const source = document.currentScript.src;
	const moduleUrl = new URL( '../interactivity/puzzles.mjs', source );
	moduleUrl.search = new URL( source ).search;
	let loading;
	const mount = () => {
		if (
			! document.querySelector(
				'[data-ohmylms-interactive="crossword"], [data-ohmylms-interactive="wordsearch"]'
			)
		)
			return;
		loading ||= import( moduleUrl.href )
			.then( ( { buildPuzzle } ) => {
				for ( const type of [ 'crossword', 'wordsearch' ] ) {
					window.OhMyLMSInteractive.register( type, {
						build: ( root, config ) =>
							buildPuzzle( root, type, config ),
					} );
				}
				window.OhMyLMSInteractive.enhance( document );
			} )
			.catch( () => {
				loading = null;
			} );
	};
	new MutationObserver( mount ).observe( document.documentElement, {
		childList: true,
		subtree: true,
	} );
	mount();
} )();
