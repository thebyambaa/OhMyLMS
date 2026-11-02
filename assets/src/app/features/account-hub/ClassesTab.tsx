import { useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { useListQuery } from '../../components/listQuery';
import { statusViews } from '../../components/listViews';
import { listClasses, type ClassFilter } from './api';
import { HubListTable } from './ListControls';
import { classStatusLabel } from './model';
import { usePagedList } from '../../hooks/usePagedList';

const STATUS_FILTER = { status: 'all' };

/**
 * Classes of every school in scope. Classes are created from their school, which owns the academic
 * years; this tab finds and opens them.
 */
export function ClassesTab() {
	const query = useListQuery( STATUS_FILTER );
	const status = query.values.status as ClassFilter;
	const load = useCallback(
		( page: number, search: string, signal: AbortSignal ) =>
			listClasses( page, search, status, undefined, signal ),
		[ status ]
	);
	const list = usePagedList( load, { inAddress: true } );

	return (
		<>
			<p>
				{ __(
					'To add a class, open its school and choose an academic year.',
					'ohmylms'
				) }
			</p>
			<HubListTable
				caption={ __( 'Classes', 'ohmylms' ) }
				searchLabel={ __( 'Search classes', 'ohmylms' ) }
				list={ list }
				loadingText={ __( 'Loading classes…', 'ohmylms' ) }
				emptyText={ __( 'No classes found.', 'ohmylms' ) }
				views={ statusViews(
					[
						{ value: 'all', label: __( 'All', 'ohmylms' ) },
						{ value: 'active', label: __( 'Active', 'ohmylms' ) },
						{
							value: 'archived',
							label: __( 'Archived', 'ohmylms' ),
						},
					],
					status,
					list.data.total,
					( value ) => query.set( { status: value } ),
					( value ) => query.href( { status: value } )
				) }
				table={ {
					rowKey: ( item ) => item.id,
					columns: [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong>
									<Link
										className="row-title"
										to={ `/account-hub/classes/${ item.id }` }
									>
										{ item.name }
									</Link>
								</strong>
							),
						},
						{
							id: 'school',
							label: __( 'School', 'ohmylms' ),
							render: ( item ) =>
								item.school_name ??
								__( 'Independent', 'ohmylms' ),
						},
						{
							id: 'year',
							label: __( 'Academic year', 'ohmylms' ),
							render: ( item ) => item.academic_year_label ?? '—',
						},
						{
							id: 'subject',
							label: __( 'Subject', 'ohmylms' ),
							render: ( item ) => item.subject || '—',
						},
						{
							id: 'grade',
							label: __( 'Grade', 'ohmylms' ),
							render: ( item ) => item.grade || '—',
						},
						{
							id: 'students',
							label: __( 'Students', 'ohmylms' ),
							className: 'num',
							render: ( item ) => item.student_count,
						},
						{
							id: 'teachers',
							label: __( 'Teachers', 'ohmylms' ),
							className: 'num',
							render: ( item ) => item.teacher_count,
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) => classStatusLabel( item.status ),
						},
					],
					rowActions: ( item ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							render: (
								<Link
									to={ `/account-hub/classes/${ item.id }` }
								>
									{ __( 'Edit', 'ohmylms' ) }
								</Link>
							),
						},
					],
				} }
			/>
		</>
	);
}
