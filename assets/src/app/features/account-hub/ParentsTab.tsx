import { Table } from '../../../ui/Table';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import { listParents } from './api';
import { ListState, SearchForm } from './ListControls';
import { ParentChildrenDialog } from './ParentChildrenDialog';
import type { Parent } from './types';
import { usePagedList } from './usePagedList';

/** Parents tab: parent accounts, their linked-children count and the linking dialog. */
export function ParentsTab() {
	const list = usePagedList( listParents );
	const [ open, setOpen ] = useState< Parent | null >( null );

	return (
		<>
			<SearchForm
				label={ __( 'Search parents', 'ohmylms' ) }
				busy={ list.loading }
				onSearch={ list.setSearch }
			/>
			<ListState
				loading={ list.loading }
				error={ list.error }
				total={ list.data.total }
				loadingText={ __( 'Loading parents…', 'ohmylms' ) }
				emptyText={ __( 'No parents found.', 'ohmylms' ) }
				onRetry={ list.reload }
			>
				<Table caption={ __( 'Parents', 'ohmylms' ) } striped>
					<thead>
						<tr>
							{ [
								__( 'Name', 'ohmylms' ),
								__( 'Email', 'ohmylms' ),
								__( 'Linked children', 'ohmylms' ),
								__( 'Actions', 'ohmylms' ),
							].map( ( title ) => (
								<th key={ title } scope="col">
									{ title }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ list.data.items.map( ( item ) => (
							<tr key={ item.id }>
								<th scope="row">{ item.display_name }</th>
								<td>{ item.email ?? '—' }</td>
								<td>{ item.child_count }</td>
								<td>
									<Button
										variant="link"
										onClick={ () => setOpen( item ) }
										aria-label={ sprintf(
											/* translators: %s: parent name. */
											__(
												'Manage children of %s',
												'ohmylms'
											),
											item.display_name
										) }
									>
										{ __( 'Manage children', 'ohmylms' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</Table>
				<Pagination
					page={ list.page }
					pages={ list.data.totalPages }
					busy={ list.loading }
					onChange={ list.setPage }
				/>
			</ListState>
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
