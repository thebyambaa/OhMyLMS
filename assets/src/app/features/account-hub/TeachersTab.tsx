import { Table } from '../../../ui/Table';
import { __ } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import { listTeachers } from './api';
import { ListState, SearchForm } from './ListControls';
import { usePagedList } from './usePagedList';

/** Teachers tab: accounts with the teacher role or a school teacher membership. */
export function TeachersTab() {
	const list = usePagedList( listTeachers );

	return (
		<>
			<p>
				{ __(
					'Teachers join a school through a school invitation, then a class from the class page.',
					'ohmylms'
				) }
			</p>
			<SearchForm
				label={ __( 'Search teachers', 'ohmylms' ) }
				busy={ list.loading }
				onSearch={ list.setSearch }
			/>
			<ListState
				loading={ list.loading }
				error={ list.error }
				total={ list.data.total }
				loadingText={ __( 'Loading teachers…', 'ohmylms' ) }
				emptyText={ __( 'No teachers found.', 'ohmylms' ) }
				onRetry={ list.reload }
			>
				<Table caption={ __( 'Teachers', 'ohmylms' ) } striped>
					<thead>
						<tr>
							{ [
								__( 'Name', 'ohmylms' ),
								__( 'Email', 'ohmylms' ),
								__( 'Schools', 'ohmylms' ),
								__( 'Classes', 'ohmylms' ),
							].map( ( title ) => (
								<th key={ title } scope="col">
									{ title }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ list.data.items.map( ( teacher ) => (
							<tr key={ teacher.id }>
								<th scope="row">{ teacher.display_name }</th>
								<td>{ teacher.email ?? '—' }</td>
								<td>
									{ teacher.schools.length
										? teacher.schools
												.map(
													( school ) => school.name
												)
												.join( ', ' )
										: __( 'No school', 'ohmylms' ) }
								</td>
								<td>{ teacher.class_count }</td>
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
		</>
	);
}
