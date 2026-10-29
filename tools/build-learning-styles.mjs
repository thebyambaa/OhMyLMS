import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as sass from 'sass';

/** Compile authored course/lesson styles as an override of the immutable recovery baseline. */
export function buildLearningStyles( root, outputRoot ) {
	const result = sass.compile(
		path.join( root, 'assets/src/frontend/scss/learning-pages.scss' ),
		{ style: 'expanded', sourceMap: true, sourceMapIncludeSources: true }
	);
	result.sourceMap.sources = result.sourceMap.sources.map(
		( source ) =>
			'ohmylms-source:///' +
			path
				.relative(
					path.join( root, 'assets/src' ),
					fileURLToPath( source )
				)
				.replaceAll( '\\', '/' )
	);
	const destination = path.join(
		outputRoot,
		'assets/css/learning-pages.css'
	);
	fs.mkdirSync( path.dirname( destination ), { recursive: true } );
	fs.writeFileSync(
		destination,
		result.css + '\n/*# sourceMappingURL=learning-pages.css.map */\n'
	);
	fs.writeFileSync(
		destination + '.map',
		JSON.stringify( result.sourceMap )
	);
}
