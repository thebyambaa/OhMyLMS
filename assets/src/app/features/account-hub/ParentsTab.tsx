import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { listParents } from './api';
import { HubListTable } from './ListControls';
import { ParentChildrenDialog } from './ParentChildrenDialog';
import type { Parent } from './types';
import { usePagedList } from './usePagedList';

/** Parents tab: parent accounts, their linked-children count and the linking dialog. */
export function ParentsTab() {
	const list = usePagedList( listParents, true );
	const [ open, setOpen ] = useState< Parent | null >( null );

	return (
		<>
			<HubListTable
				caption={ __( 'Parents', 'ohmylms' ) }
				searchLabel={ __( 'Search parents', 'ohmylms' ) }
				list={ list }
				loadingText={ __( 'Loading parents…', 'ohmylms' ) }
				emptyText={ __( 'No parents found.', 'ohmylms' ) }
				table={ {
					rowKey: ( item ) => item.id,
					columns: [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong>{ item.display_name }</strong>
							),
						},
						{
							id: 'email',
							label: __( 'Email', 'ohmylms' ),
							render: ( item ) => item.email ?? '—',
						},
						{
							id: 'children',
							label: __( 'Linked children', 'ohmylms' ),
							className: 'num',
							render: ( item ) => item.child_count,
						},
					],
					rowActions: ( item ) => [
						{
							key: 'children',
							label: __( 'Manage children', 'ohmylms' ),
							ariaLabel: sprintf(
								/* translators: %s: parent name. */
								__( 'Manage children of %s', 'ohmylms' ),
								item.display_name
							),
							onClick: () => setOpen( item ),
						},
					],
				} }
			/>
			{ open && (
				<ParentChildrenDialog
					parent={ open }
					onClose={ () => setOpen( null ) }
					onChange={ list.reload }
				/>
			) }
		</>
	);
}
