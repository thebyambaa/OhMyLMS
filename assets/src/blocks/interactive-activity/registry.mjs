import { mountChart } from './chart.mjs';
import { mountClock } from './clock.mjs';
import { mountConverter } from './converter.mjs';
import { mountFractions } from './fractions.mjs';
import { mountFunctionGraph } from './function-graph.mjs';
import { mountGeometry } from './geometry.mjs';
import { mountNumberLine } from './number-line.mjs';

/**
 * Every activity type the page and the editor can mount. A new type registers here, in
 * `activities.mjs` (its configuration), in `includes/Blocks/InteractiveActivityBlock.php` and in the
 * editor settings.
 */
export const MOUNTERS = {
	'function-graph': mountFunctionGraph,
	'number-line': mountNumberLine,
	fractions: mountFractions,
	clock: mountClock,
	converter: mountConverter,
	chart: mountChart,
	geometry: mountGeometry,
};
