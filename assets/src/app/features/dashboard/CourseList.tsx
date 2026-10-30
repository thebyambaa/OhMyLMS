import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Table } from '../../../ui/Table';
import { courseReportPath, formatMoney, formatNumber } from './model';
import type { CourseRow, Currency } from './types';

/**
 * Short list of courses (recent or popular) with a link to each course report.
 *
 * @param props          Component props.
 * @param props.caption  Table caption and heading text.
 * @param props.courses  Rows.
 * @param props.currency Site currency.
 * @param props.empty    Message when there are no rows.
 */
export function CourseList( {
	caption,
	courses,
	currency,
	empty,
}: {
	caption: string;
	courses: readonly CourseRow[];
	currency: Currency;
	empty: string;
} ) {
	if ( 0 === courses.length ) {
		return (
			<section aria-label={ caption }>
				<h3>{ caption }</h3>
				<p>{ empty }</p>
			</section>
		);
	}
	return (
		<section aria-label={ caption }>
			<h3>{ caption }</h3>
			<Table caption={ caption } striped compact>
				<thead>
					<tr>
						<th scope="col">{ __( 'Course', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Status', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'New students', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Sold', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Net income', 'ohmylms' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ courses.map( ( course ) => (
						<tr key={ course.id }>
							<th scope="row">
								<Link to={ courseReportPath( course.id ) }>
									{ course.title ||
										sprintf(
											/* translators: %d: course ID. */
											__( 'Course %d', 'ohmylms' ),
											course.id
										) }
								</Link>
							</th>
							<td>{ course.status }</td>
							<td>{ formatNumber( course.enrollments ) }</td>
							<td>
								{ sprintf(
									/* translators: %s: number of sales. */
									_n(
										'%s sale',
										'%s sales',
										course.sales,
										'ohmylms'
									),
									formatNumber( course.sales )
								) }
							</td>
							<td>{ formatMoney( course.net, currency ) }</td>
						</tr>
					) ) }
				</tbody>
			</Table>
		</section>
	);
}
