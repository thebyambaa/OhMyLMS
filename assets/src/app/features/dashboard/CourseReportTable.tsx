import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { ListTable } from '../../../ui/ListTable';
import { useCourseReport } from './hooks';
import { courseReportPath, formatMoney, formatNumber } from './model';
import { RequestStatus } from './RequestStatus';
import type { CourseOrderBy, Currency, RangeSelection } from './types';

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

	return (
		<section aria-label={ __( 'Course report', 'ohmylms' ) }>
			<h3>{ __( 'Courses', 'ohmylms' ) }</h3>
			<RequestStatus
				loading={ list.loading }
				error={ list.error }
				label={ __( 'Loading courses…', 'ohmylms' ) }
				onRetry={ list.reload }
			/>
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'Course report', 'ohmylms' ) }
					formLabel={ __( 'Search courses', 'ohmylms' ) }
					rows={ items }
					rowKey={ ( course ) => course.id }
					emptyMessage={ __( 'No courses found.', 'ohmylms' ) }
					search={ {
						label: __( 'Search courses', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: text,
						onChange: setText,
						onSubmit: () => list.setSearch( text ),
					} }
					sort={ {
						orderby: list.query.orderby,
						order: list.query.order,
						onChange: ( orderby, order ) =>
							list.setSort( orderby as CourseOrderBy, order ),
					} }
					pagination={ {
						page: list.query.page,
						totalPages: list.data?.totalPages ?? 1,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: number of courses. */
							_n(
								'%d course found.',
								'%d courses found.',
								total,
								'ohmylms'
							),
							total
						),
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'title',
							label: __( 'Course', 'ohmylms' ),
							primary: true,
							sortable: 'asc',
							render: ( course ) => (
								<strong>
									<Link
										className="row-title"
										to={ courseReportPath( course.id ) }
									>
										{ course.title }
									</Link>
								</strong>
							),
						},
						{
							id: 'sales',
							label: __( 'Sold', 'ohmylms' ),
							sortable: 'desc',
							className: 'num',
							render: ( course ) => formatNumber( course.sales ),
						},
						{
							id: 'gross',
							label: __( 'Earnings', 'ohmylms' ),
							sortable: 'desc',
							render: ( course ) =>
								formatMoney( course.gross, currency ),
						},
						{
							id: 'refunds',
							label: __( 'Refunds', 'ohmylms' ),
							sortable: 'desc',
							render: ( course ) =>
								formatMoney( course.refunds, currency ),
						},
						{
							id: 'net',
							label: __( 'Net income', 'ohmylms' ),
							sortable: 'desc',
							render: ( course ) =>
								formatMoney( course.net, currency ),
						},
						{
							id: 'enrollments',
							label: __( 'New students', 'ohmylms' ),
							sortable: 'desc',
							className: 'num',
							render: ( course ) =>
								formatNumber( course.enrollments ),
						},
						{
							id: 'completed',
							label: __( 'Completed', 'ohmylms' ),
							className: 'num',
							render: ( course ) =>
								formatNumber( course.completed ),
						},
					] }
					rowActions={ ( course ) => [
						{
							key: 'view',
							label: __( 'View report', 'ohmylms' ),
							render: (
								<Link to={ courseReportPath( course.id ) }>
									{ __( 'View report', 'ohmylms' ) }
								</Link>
							),
						},
					] }
				/>
			) }
		</section>
	);
}
