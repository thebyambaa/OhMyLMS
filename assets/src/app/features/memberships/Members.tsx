import { Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ListTable } from '../../../ui/ListTable';
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
						page,
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
						onChange: setPage,
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
