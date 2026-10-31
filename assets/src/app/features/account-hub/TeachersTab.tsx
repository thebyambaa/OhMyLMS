import { __ } from '@wordpress/i18n';
import { listTeachers } from './api';
import { HubListTable } from './ListControls';
import { usePagedList } from './usePagedList';

/** Teachers tab: accounts with the teacher role or a school teacher membership. */
export function TeachersTab() {
	const list = usePagedList( listTeachers, true );

	return (
		<>
			<p>
				{ __(
					'Teachers join a school through a school invitation, then a class from the class page.',
					'ohmylms'
				) }
			</p>
			<HubListTable
				caption={ __( 'Teachers', 'ohmylms' ) }
				searchLabel={ __( 'Search teachers', 'ohmylms' ) }
				list={ list }
				loadingText={ __( 'Loading teachers…', 'ohmylms' ) }
				emptyText={ __( 'No teachers found.', 'ohmylms' ) }
				table={ {
					rowKey: ( teacher ) => teacher.id,
					columns: [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( teacher ) => (
								<strong>{ teacher.display_name }</strong>
							),
						},
						{
							id: 'email',
							label: __( 'Email', 'ohmylms' ),
							render: ( teacher ) => teacher.email ?? '—',
						},
						{
							id: 'schools',
							label: __( 'Schools', 'ohmylms' ),
							render: ( teacher ) =>
								teacher.schools.length
									? teacher.schools
											.map( ( school ) => school.name )
											.join( ', ' )
									: __( 'No school', 'ohmylms' ),
						},
						{
							id: 'classes',
							label: __( 'Classes', 'ohmylms' ),
							className: 'num',
							render: ( teacher ) => teacher.class_count,
						},
					],
				} }
			/>
		</>
	);
}
