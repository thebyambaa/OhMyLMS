import { Button, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Table } from '../../../ui/Table';
import { Pagination } from '../../components/Pagination';
import { useCourseReport } from './hooks';
import { courseReportPath, formatMoney, formatNumber } from './model';
import { RequestStatus } from './RequestStatus';
import { SortHeader } from './SortHeader';
import type { Currency, RangeSelection } from './types';

/**
 * Paged, sortable report of every course: sales, money and students in the range.
 *
 * @param props          Component props.
 * @param props.range    Selected range.
 * @param props.currency Site currency.
 */
export function CourseReportTable( {
	range,
	currency,
}: {
	range: RangeSelection;
	currency: Currency;
} ) {
	const list = useCourseReport( range );
	const [ text, setText ] = useState( '' );
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;
	const sort = { orderby: list.query.orderby, order: list.query.order };

	return (
		<section aria-label={ __( 'Course report', 'ohmylms' ) }>
			<h3>{ __( 'Courses', 'ohmylms' ) }</h3>
			<form
				role="search"
				aria-label={ __( 'Search courses', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setSearch( text );
				} }
			>
				<TextControl
					label={ __( 'Search courses', 'ohmylms' ) }
					value={ text }
					onChange={ setText }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<RequestStatus
				loading={ list.loading }
				error={ list.error }
				label={ __( 'Loading courses…', 'ohmylms' ) }
				onRetry={ list.reload }
			/>
			{ ! list.loading &&
				! list.error &&
				( items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: number of courses. */
								_n(
									'%d course found.',
									'%d courses found.',
									total,
									'ohmylms'
								),
								total
							) }
						</p>
						<Table
							caption={ __( 'Course report', 'ohmylms' ) }
							striped
							compact
						>
							<thead>
								<tr>
									<SortHeader
										label={ __( 'Course', 'ohmylms' ) }
										field="title"
										first="asc"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<SortHeader
										label={ __( 'Sold', 'ohmylms' ) }
										field="sales"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<SortHeader
										label={ __( 'Earnings', 'ohmylms' ) }
										field="gross"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<SortHeader
										label={ __( 'Refunds', 'ohmylms' ) }
										field="refunds"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<SortHeader
										label={ __( 'Net income', 'ohmylms' ) }
										field="net"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<SortHeader
										label={ __(
											'New students',
											'ohmylms'
										) }
										field="enrollments"
										{ ...sort }
										onSort={ list.setSort }
									/>
									<th scope="col">
										{ __( 'Completed', 'ohmylms' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ items.map( ( course ) => (
									<tr key={ course.id }>
										<th scope="row">
											<Link
												to={ courseReportPath(
													course.id
												) }
											>
												{ course.title }
											</Link>
										</th>
										<td>
											{ formatNumber( course.sales ) }
										</td>
										<td>
											{ formatMoney(
												course.gross,
												currency
											) }
										</td>
										<td>
											{ formatMoney(
												course.refunds,
												currency
											) }
										</td>
										<td>
											{ formatMoney(
												course.net,
												currency
											) }
										</td>
										<td>
											{ formatNumber(
												course.enrollments
											) }
										</td>
										<td>
											{ formatNumber( course.completed ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ list.query.page }
							pages={ list.data?.totalPages ?? 1 }
							busy={ list.loading }
							onChange={ list.setPage }
						/>
					</>
				) : (
					<p>{ __( 'No courses found.', 'ohmylms' ) }</p>
				) ) }
		</section>
	);
}
