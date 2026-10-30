import { useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Table } from '../../../ui/Table';
import {
	buildChart,
	describeSeries,
	formatBucket,
	formatCompact,
	formatMoney,
} from './model';
import type { Currency, Granularity, SeriesPoint } from './types';

/**
 * Earnings per day or month as stacked bars (net at the bottom, refunds on top, together the
 * gross), drawn as inline SVG. The graphic has a text description and a complete data table, which
 * is the accessible equivalent of the chart.
 *
 * @param props             Component props.
 * @param props.series      Chart points.
 * @param props.currency    Site currency.
 * @param props.granularity Bucket size.
 * @param props.title       Visible title.
 */
export function EarningsChart( {
	series,
	currency,
	granularity,
	title,
}: {
	series: readonly SeriesPoint[];
	currency: Currency;
	granularity: Granularity;
	title: string;
} ) {
	const id = useId();
	const description = describeSeries( series, currency, granularity );
	const hasData = series.some( ( point ) => point.gross > 0 );
	const chart = buildChart( series );
	const last = series.length - 1;
	const labelled = [
		...new Set( [ 0, Math.floor( last / 2 ), last ] ),
	].filter( ( index ) => index >= 0 );

	return (
		<figure
			aria-labelledby={ `${ id }-title` }
			style={ { margin: '0 0 16px' } }
		>
			<figcaption id={ `${ id }-title` }>
				<strong>{ title }</strong>
			</figcaption>
			{ ! hasData && <p>{ description }</p> }
			{ hasData && (
				<>
					<svg
						role="img"
						aria-labelledby={ `${ id }-svg-title ${ id }-svg-desc` }
						viewBox={ `0 0 ${ chart.width } ${ chart.height }` }
						style={ {
							width: '100%',
							maxWidth: 720,
							height: 'auto',
						} }
					>
						<title id={ `${ id }-svg-title` }>{ title }</title>
						<desc id={ `${ id }-svg-desc` }>{ description }</desc>
						{ chart.ticks.map( ( tick ) => (
							<g key={ tick.y }>
								<line
									x1={ chart.left }
									x2={ chart.right }
									y1={ tick.y }
									y2={ tick.y }
									stroke="currentColor"
									strokeOpacity="0.15"
								/>
								<text
									x={ chart.left - 6 }
									y={ tick.y + 4 }
									textAnchor="end"
									fontSize="11"
									fill="currentColor"
								>
									{ formatCompact( tick.value ) }
								</text>
							</g>
						) ) }
						{ chart.bars.map( ( bar ) => (
							<g key={ bar.key }>
								<rect
									x={ bar.x }
									y={ bar.netY }
									width={ bar.width }
									height={ bar.netHeight }
									fill="#2271b1"
								/>
								<rect
									x={ bar.x }
									y={ bar.refundY }
									width={ bar.width }
									height={ bar.refundHeight }
									fill="#d63638"
								/>
							</g>
						) ) }
						{ labelled.map( ( index ) => {
							const bar = chart.bars[ index ];
							const point = series[ index ];
							return bar && point ? (
								<text
									key={ point.date }
									x={ bar.x + bar.width / 2 }
									y={ chart.height - 8 }
									textAnchor="middle"
									fontSize="11"
									fill="currentColor"
								>
									{ formatBucket( point.date, granularity ) }
								</text>
							) : null;
						} ) }
					</svg>
					<p aria-hidden="true">
						<span style={ { color: '#2271b1' } }>■</span>{ ' ' }
						{ __( 'Net income', 'ohmylms' ) }{ ' ' }
						<span style={ { color: '#d63638' } }>■</span>{ ' ' }
						{ __( 'Refunds', 'ohmylms' ) }
					</p>
				</>
			) }
			{ series.length > 0 && (
				<details>
					<summary>{ __( 'Show data table', 'ohmylms' ) }</summary>
					<Table caption={ title } compact striped>
						<thead>
							<tr>
								<th scope="col">
									{ 'month' === granularity
										? __( 'Month', 'ohmylms' )
										: __( 'Day', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Orders', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Earnings', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Refunds', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Net income', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ series.map( ( point ) => (
								<tr key={ point.date }>
									<th scope="row">
										{ formatBucket(
											point.date,
											granularity
										) }
									</th>
									<td>{ point.orders }</td>
									<td>
										{ formatMoney( point.gross, currency ) }
									</td>
									<td>
										{ formatMoney(
											point.refunds,
											currency
										) }
									</td>
									<td>
										{ formatMoney( point.net, currency ) }
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
				</details>
			) }
		</figure>
	);
}
