import { __ } from '@wordpress/i18n';
import { levelRange } from './MasteryModel';
import type { MasteryLegendEntry } from './MasteryModel';

/**
 * Legend of the mastery levels: each level's pattern swatch, its name and its range in words, so the
 * heatmap never relies on colour alone.
 *
 * @param props        Component props.
 * @param props.levels Levels, highest first.
 */
export function MasteryLegend( {
	levels,
}: {
	levels: readonly MasteryLegendEntry[];
} ) {
	return (
		<ul
			className="ohmylms-mastery-legend"
			aria-label={ __( 'Mastery levels', 'ohmylms' ) }
		>
			{ levels.map( ( level, index ) => (
				<li key={ level.id } className="ohmylms-mastery-legend__item">
					<span
						className={ `ohmylms-mastery-swatch is-${ level.id }` }
						aria-hidden="true"
					/>
					<span>
						<strong>{ level.label }</strong>{ ' ' }
						<span className="ohmylms-mastery-legend__range">
							{ levelRange( levels, index ) }
						</span>
					</span>
				</li>
			) ) }
		</ul>
	);
}
