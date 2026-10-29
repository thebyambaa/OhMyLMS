import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';

const probeIds = [
	12278, 15468, 21186, 22563, 24295, 25946, 26701, 27268, 39706, 43052, 50127,
	52485, 53725, 54870, 55907, 57677, 58273, 6425, 65, 66655, 66718, 68119,
	69986, 71847, 77558, 79476, 81381, 83154, 2214, 91386, 94490, 36032, 4315,
	99166,
];

const probe = () =>
	new Proxy(
		{},
		{ get: ( target, key ) => ( '__esModule' === key ? true : () => null ) }
	);

/**
 * Loads the recovered controls kit (factory 38093) with its real select stack.
 *
 * The recovered select factories (46005 wrapper, 11541 async, 52836 state manager, 8546
 * select, 15777 components) run as shipped. Webpack externals missing from the extraction are
 * supplied by the host: React (41594), ReactDOM (75206) and Emotion's `jsx`, `css` and
 * `keyframes` (11456). Everything else the kit imports is a probe.
 *
 * @return {Object} Recovered exports, host React and `render( Component, props )`.
 */
export function loadRecoveredSelects() {
	const hostRequire = installBrowserStubs();
	globalThis.wp = new Proxy( {}, { get: () => ( {} ) } );
	const React = hostRequire( 'react' );
	const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
	const emotion = hostRequire( '@emotion/react' );
	const createCache = hostRequire( '@emotion/cache' );
	const overrides = {
		11456: { Y: emotion.jsx, AH: emotion.css, i7: emotion.keyframes },
		41594: React,
		75206: hostRequire( 'react-dom' ),
		51893: probe(),
		12470: { __: ( text ) => text },
	};
	for ( const id of probeIds ) {
		overrides[ id ] = probe();
	}
	const recovered = createLoader( overrides )( 38093 );

	// A fresh Emotion cache per render keeps style tags independent of earlier renders.
	// react-select numbers its DOM ids with a global instance counter, so `react-select-N-`
	// is normalised to let two renders of the same props compare equal. The recovered build
	// predates react-select 5.10's emotion labels (`A11yText`, `Svg`), which only rename the
	// hashed Emotion classes; the CSS rules beside them stay compared, so class hashes are masked.
	const render = ( Component, props ) =>
		renderToStaticMarkup(
			React.createElement(
				emotion.CacheProvider,
				{
					value: ( createCache.default || createCache )( {
						key: 'css',
					} ),
				},
				React.createElement( Component, props )
			)
		)
			.replace( /react-select-\d+-/g, 'react-select-N-' )
			.replace( /(css[ -])[a-z\d]{5,7}(-[A-Za-z\d]+)*/g, '$1H' );
	return { recovered, React, render };
}
