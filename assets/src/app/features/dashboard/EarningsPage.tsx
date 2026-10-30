import { __, sprintf } from '@wordpress/i18n';
import { Table } from '../../../ui/Table';
import { CourseReportTable } from './CourseReportTable';
import { EarningsChart } from './EarningsChart';
import { useEarnings, useRange } from './hooks';
import { buildCards, describeRange, formatMoney, formatNumber } from './model';
import { RangeFilter } from './RangeFilter';
import { RequestStatus } from './RequestStatus';
import { SummaryCards } from './SummaryCards';
import { TransactionsTable } from './TransactionsTable';

/** The `/earnings` route: totals, chart, breakdowns, orders and the per course report. */
export default function EarningsPage() {
	const { range, setRange } = useRange();
	const request = useEarnings( range );
	const data = request.data;

	return (
		<>
			<h2>{ __( 'Earnings', 'ohmylms' ) }</h2>
			<RangeFilter value={ range } onChange={ setRange } />
			<RequestStatus
				loading={ request.loading }
				error={ request.error }
				label={ __( 'Loading earnings…', 'ohmylms' ) }
				onRetry={ request.reload }
			/>
			{ data && (
				<div aria-busy={ request.loading }>
					<p>
						{ sprintf(
							/* translators: %s: date range. */
							__(
								'Showing %s, compared with the period before.',
								'ohmylms'
							),
							describeRange( data.range )
						) }
					</p>
					<SummaryCards
						cards={ buildCards( data.summary, data.currency ) }
					/>
					<EarningsChart
						title={ __( 'Earnings', 'ohmylms' ) }
						series={ data.series }
						currency={ data.currency }
						granularity={ data.range.granularity }
					/>
					<section
						aria-label={ __( 'Orders by country', 'ohmylms' ) }
					>
						<h3>{ __( 'Orders by country', 'ohmylms' ) }</h3>
						{ data.by_country.length ? (
							<Table
								caption={ __( 'Orders by country', 'ohmylms' ) }
								compact
							>
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Country', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Orders', 'ohmylms' ) }
										</th>
										<th scope="col">
											{ __( 'Earnings', 'ohmylms' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ data.by_country.map( ( row ) => (
										<tr key={ row.country }>
											<th scope="row">{ row.country }</th>
											<td>
												{ formatNumber( row.orders ) }
											</td>
											<td>
												{ formatMoney(
													row.gross,
													data.currency
												) }
											</td>
										</tr>
									) ) }
								</tbody>
							</Table>
						) : (
							<p>
								{ __(
									'No country data in this period.',
									'ohmylms'
								) }
							</p>
						) }
					</section>
					<TransactionsTable
						range={ range }
						currency={ data.currency }
						methods={ data.by_payment_method }
					/>
					<CourseReportTable
						range={ range }
						currency={ data.currency }
					/>
				</div>
			) }
		</>
	);
}
