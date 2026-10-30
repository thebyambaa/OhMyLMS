import { Table } from '../../../ui/Table';
import { SelectControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { listClasses, type ClassFilter } from './api';
import { ListState, SearchForm } from './ListControls';
import { classStatusLabel } from './model';
import { usePagedList } from './usePagedList';

/**
 * Classes of every school in scope. Classes are created from their school, which owns the academic
 * years; this tab finds and opens them.
 */
export function ClassesTab() {
	const [ status, setStatus ] = useState< ClassFilter >( 'all' );
	const load = useCallback(
		( page: number, search: string, signal: AbortSignal ) =>
			listClasses( page, search, status, undefined, signal ),
		[ status ]
	);
	const list = usePagedList( load );

	return (
		<>
			<p>
				{ __(
					'To add a class, open its school and choose an academic year.',
					'ohmylms'
				) }
			</p>
			<SearchForm
				label={ __( 'Search classes', 'ohmylms' ) }
				busy={ list.loading }
				onSearch={ list.setSearch }
			>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ status }
					options={ [
						{ value: 'all', label: __( 'All', 'ohmylms' ) },
						{ value: 'active', label: __( 'Active', 'ohmylms' ) },
						{
							value: 'archived',
							label: __( 'Archived', 'ohmylms' ),
						},
					] }
					onChange={ ( value ) => {
						setStatus( value as ClassFilter );
						list.setPage( 1 );
					} }
				/>
			</SearchForm>
			<ListState
				loading={ list.loading }
				error={ list.error }
				total={ list.data.total }
				loadingText={ __( 'Loading classes…', 'ohmylms' ) }
				emptyText={ __( 'No classes found.', 'ohmylms' ) }
				onRetry={ list.reload }
			>
				<Table caption={ __( 'Classes', 'ohmylms' ) } striped>
					<thead>
						<tr>
							{ [
								__( 'Name', 'ohmylms' ),
								__( 'School', 'ohmylms' ),
								__( 'Academic year', 'ohmylms' ),
								__( 'Subject', 'ohmylms' ),
								__( 'Grade', 'ohmylms' ),
								__( 'Students', 'ohmylms' ),
								__( 'Teachers', 'ohmylms' ),
								__( 'Status', 'ohmylms' ),
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
								<th scope="row">
									<Link
										to={ `/account-hub/classes/${ item.id }` }
									>
										{ item.name }
									</Link>
								</th>
								<td>
									{ item.school_name ??
										__( 'Independent', 'ohmylms' ) }
								</td>
								<td>{ item.academic_year_label ?? '—' }</td>
								<td>{ item.subject || '—' }</td>
								<td>{ item.grade || '—' }</td>
								<td>{ item.student_count }</td>
								<td>{ item.teacher_count }</td>
								<td>{ classStatusLabel( item.status ) }</td>
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
