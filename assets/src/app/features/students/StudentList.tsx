import { ListTable, ListTableSelect } from '../../../ui/ListTable';
import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import {
	COURSE_OPTION_LIMIT,
	formatDate,
	getSortChoices,
	sortValue,
	studentPath,
} from './model';
import { useCourseOptions, useStudents } from './useStudents';

/** Searchable, filterable, paged list of students. */
export function StudentList() {
	const list = useStudents();
	const courses = useCourseOptions( '' );
	const [ text, setText ] = useState( '' );
	const sortChoices = getSortChoices();
	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;

	return (
		<>
			<h2>{ __( 'Students', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Everyone with the student role or an enrolment. Open a student to see progress and manage enrolments.',
					'ohmylms'
				) }
			</p>
			{ courses.error && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Courses could not be loaded for the filter.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ ( courses.data?.total ?? 0 ) > COURSE_OPTION_LIMIT && (
				<p className="description">
					{ sprintf(
						/* translators: %d: number of courses listed. */
						__(
							'Only the first %d courses are listed.',
							'ohmylms'
						),
						COURSE_OPTION_LIMIT
					) }
				</p>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error.message }{ ' ' }
					<Button variant="link" onClick={ list.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading students…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && (
				<ListTable
					caption={ __( 'Students', 'ohmylms' ) }
					formLabel={ __( 'Search students', 'ohmylms' ) }
					rows={ items }
					rowKey={ ( item ) => item.id }
					emptyMessage={ __( 'No students found.', 'ohmylms' ) }
					search={ {
						label: __( 'Search students', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						placeholder: __( 'Name, username or email', 'ohmylms' ),
						value: text,
						onChange: setText,
					} }
					onFilter={ () => list.setSearch( text ) }
					filterLabel={ null }
					filters={
						<>
							<ListTableSelect
								label={ __( 'Filter by course', 'ohmylms' ) }
								value={
									null === list.query.course
										? ''
										: String( list.query.course )
								}
								options={ [
									{
										value: '',
										label: __( 'All courses', 'ohmylms' ),
									},
									...( courses.data?.items ?? [] ).map(
										( course ) => ( {
											value: String( course.id ),
											label: course.title,
										} )
									),
								] }
								onChange={ ( value ) =>
									list.setCourse(
										value ? Number( value ) : null
									)
								}
							/>
							<ListTableSelect
								label={ __( 'Sort by', 'ohmylms' ) }
								value={ sortValue( list.query ) }
								options={ sortChoices.map(
									( { value, label } ) => ( {
										value,
										label,
									} )
								) }
								onChange={ list.setSort }
							/>
						</>
					}
					pagination={ {
						page: list.query.page,
						totalPages: list.data?.totalPages ?? 1,
						totalItems: total,
						itemsLabel: sprintf(
							/* translators: %d: total student count. */
							_n(
								'%d student found.',
								'%d students found.',
								total,
								'ohmylms'
							),
							total
						),
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong>
									<Link
										className="row-title"
										to={ studentPath( item.id ) }
									>
										{ item.name }
									</Link>
								</strong>
							),
						},
						{
							id: 'email',
							label: __( 'Email', 'ohmylms' ),
							render: ( item ) => item.email,
						},
						{
							id: 'courses',
							label: __( 'Courses', 'ohmylms' ),
							render: ( item ) =>
								sprintf(
									/* translators: 1: completed courses, 2: enrolled courses. */
									__( '%1$d of %2$d completed', 'ohmylms' ),
									item.completed_courses,
									item.enrolled_courses
								),
						},
						{
							id: 'access',
							label: __( 'Access', 'ohmylms' ),
							render: ( item ) =>
								item.blocked
									? __( 'Blocked', 'ohmylms' )
									: __( 'Active', 'ohmylms' ),
						},
						{
							id: 'registered',
							label: __( 'Registered', 'ohmylms' ),
							render: ( item ) => formatDate( item.registered ),
						},
					] }
					rowActions={ ( item ) => [
						{
							key: 'view',
							label: __( 'View', 'ohmylms' ),
							render: (
								<Link to={ studentPath( item.id ) }>
									{ __( 'View', 'ohmylms' ) }
								</Link>
							),
						},
					] }
				/>
			) }
		</>
	);
}
