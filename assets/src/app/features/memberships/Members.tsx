import { Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Table } from '../../../ui/Table';
import { Pagination } from '../../components/Pagination';
import { listMembers } from './api';
import { usePagedList } from './usePagedList';

/**
 * Read-only, paged list of the people on a plan. Membership is changed where it is sold (checkout and
 * orders), not here.
 *
 * @param props    Component props.
 * @param props.id Plan id.
 */
export function Members( { id }: { id: number } ) {
	const [ page, setPage ] = useState( 1 );
	const list = usePagedList(
		( signal ) => listMembers( id, page, signal ),
		[ id, page ]
	);
	const date = ( value: string | null ) =>
		value ? new Date( value ).toLocaleDateString() : '-';
	return (
		<section aria-labelledby="oml-mem-members">
			<h3 id="oml-mem-members">{ __( 'Members', 'ohmylms' ) }</h3>
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading members…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && ! list.data.items.length && (
				<p>{ __( 'This plan has no members yet.', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && list.data.items.length > 0 && (
				<>
					<p role="status">
						{ sprintf(
							/* translators: %d: number of members. */
							_n(
								'%d member.',
								'%d members.',
								list.data.total,
								'ohmylms'
							),
							list.data.total
						) }
					</p>
					<Table caption={ __( 'Plan members', 'ohmylms' ) } striped>
						<thead>
							<tr>
								{ [
									__( 'Name', 'ohmylms' ),
									__( 'Email', 'ohmylms' ),
									__( 'Status', 'ohmylms' ),
									__( 'Started', 'ohmylms' ),
									__( 'Ends', 'ohmylms' ),
								].map( ( title ) => (
									<th key={ title } scope="col">
										{ title }
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ list.data.items.map( ( member ) => (
								<tr key={ member.id }>
									<th scope="row">{ member.display_name }</th>
									<td>{ member.email }</td>
									<td>{ member.status }</td>
									<td>{ date( member.start_date ) }</td>
									<td>{ date( member.end_date ) }</td>
								</tr>
							) ) }
						</tbody>
					</Table>
				</>
			) }
			<Pagination
				page={ page }
				pages={ list.data.totalPages }
				busy={ list.loading }
				onChange={ setPage }
			/>
		</section>
	);
}
