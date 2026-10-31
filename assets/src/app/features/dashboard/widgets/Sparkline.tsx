import { sparklinePoints } from './pulse';

const WIDTH = 120;
const HEIGHT = 28;

/**
 * A small trend line in plain SVG. Decorative: the figures it shows are described in text next to it.
 *
 * @param props        Component props.
 * @param props.values One value per day.
 * @param props.tone   Colour class: `up` (good), `down` (bad) or neutral.
 */
export function Sparkline( {
	values,
	tone = 'neutral',
}: {
	values: readonly number[];
	tone?: 'up' | 'down' | 'neutral';
} ) {
	return (
		<svg
			className={ `ohmylms-sparkline is-${ tone }` }
			width={ WIDTH }
			height={ HEIGHT }
			viewBox={ `0 0 ${ WIDTH } ${ HEIGHT }` }
			aria-hidden="true"
			focusable="false"
		>
			<polyline
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinejoin="round"
				strokeLinecap="round"
				points={ sparklinePoints( values, WIDTH, HEIGHT ) }
			/>
		</svg>
	);
}
