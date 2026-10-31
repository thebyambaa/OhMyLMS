import { Notice } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ListTable } from '../../../ui/ListTable';
import { usePagedList } from '../../hooks/usePagedList';
import { listMembers } from './api';

/**
 * Read-only, paged list of the people on a plan. Membership is changed where it is sold (checkout and
 * orders), not here.
 *
 * @param props    Component props.
 * @param props.id Plan id.
 */
export function Members( { id }: { id: number } ) {
	const load = useCallback(
		( page: number, _search: string, signal: AbortSignal ) =>
			listMembers( id, page, signal ),
		[ id ]
	);
	const list = usePagedList( load, { keepPrevious: true } );
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
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'Plan members', 'ohmylms' ) }
					rows={ list.data.items }
					rowKey={ ( member ) => member.id }
					emptyMessage={ __(
						'This plan has no members yet.',
						'ohmylms'
					) }
					pagination={ {
						page: list.page,
						totalPages: list.data.totalPages,
						totalItems: list.data.total,
						itemsLabel: sprintf(
							/* translators: %d: number of members. */
							_n(
								'%d member.',
								'%d members.',
								list.data.total,
								'ohmylms'
							),
							list.data.total
						),
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( member ) => (
								<strong>{ member.display_name }</strong>
							),
						},
						{
							id: 'email',
							label: __( 'Email', 'ohmylms' ),
							render: ( member ) => member.email,
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( member ) => member.status,
						},
						{
							id: 'start',
							label: __( 'Started', 'ohmylms' ),
							render: ( member ) => date( member.start_date ),
						},
						{
							id: 'end',
							label: __( 'Ends', 'ohmylms' ),
							render: ( member ) => date( member.end_date ),
						},
					] }
				/>
			) }
		</section>
	);
}
