import { Table } from '../../../ui/Table';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
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
			<form
				role="search"
				aria-label={ __( 'Search students', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					list.setSearch( text );
				} }
			>
				<TextControl
					label={ __( 'Search students', 'ohmylms' ) }
					help={ __( 'Name, username or email.', 'ohmylms' ) }
					value={ text }
					onChange={ setText }
				/>
				<Button
					type="submit"
					variant="secondary"
					disabled={ list.loading }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<SelectControl
				label={ __( 'Filter by course', 'ohmylms' ) }
				value={
					null === list.query.course
						? ''
						: String( list.query.course )
				}
				options={ [
					{ value: '', label: __( 'All courses', 'ohmylms' ) },
					...( courses.data?.items ?? [] ).map( ( course ) => ( {
						value: String( course.id ),
						label: course.title,
					} ) ),
				] }
				help={
					( courses.data?.total ?? 0 ) > COURSE_OPTION_LIMIT
						? sprintf(
								/* translators: %d: number of courses listed. */
								__(
									'Only the first %d courses are listed.',
									'ohmylms'
								),
								COURSE_OPTION_LIMIT
							)
						: undefined
				}
				onChange={ ( value ) =>
					list.setCourse( value ? Number( value ) : null )
				}
			/>
			{ courses.error && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Courses could not be loaded for the filter.',
						'ohmylms'
					) }
				</Notice>
			) }
			<SelectControl
				label={ __( 'Sort by', 'ohmylms' ) }
				value={ sortValue( list.query ) }
				options={ sortChoices.map( ( { value, label } ) => ( {
					value,
					label,
				} ) ) }
				onChange={ list.setSort }
			/>
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
			{ ! list.loading &&
				! list.error &&
				( items.length ? (
					<>
						<p role="status">
							{ sprintf(
								/* translators: %d: total student count. */
								_n(
									'%d student found.',
									'%d students found.',
									total,
									'ohmylms'
								),
								total
							) }
						</p>
						<Table caption={ __( 'Students', 'ohmylms' ) } striped>
							<thead>
								<tr>
									{ [
										__( 'Name', 'ohmylms' ),
										__( 'Email', 'ohmylms' ),
										__( 'Courses', 'ohmylms' ),
										__( 'Access', 'ohmylms' ),
										__( 'Registered', 'ohmylms' ),
									].map( ( title ) => (
										<th key={ title } scope="col">
											{ title }
										</th>
									) ) }
								</tr>
							</thead>
							<tbody>
								{ items.map( ( item ) => (
									<tr key={ item.id }>
										<th scope="row">
											<Link to={ studentPath( item.id ) }>
												{ item.name }
											</Link>
										</th>
										<td>{ item.email }</td>
										<td>
											{ sprintf(
												/* translators: 1: completed courses, 2: enrolled courses. */
												__(
													'%1$d of %2$d completed',
													'ohmylms'
												),
												item.completed_courses,
												item.enrolled_courses
											) }
										</td>
										<td>
											{ item.blocked
												? __( 'Blocked', 'ohmylms' )
												: __( 'Active', 'ohmylms' ) }
										</td>
										<td>
											{ formatDate( item.registered ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</Table>
						<Pagination
							page={ list.query.page }
							pages={ list.data?.totalPages ?? 1 }
							busy={ list.loading }
							onChange={ list.setPage }
						/>
					</>
				) : (
					<p>{ __( 'No students found.', 'ohmylms' ) }</p>
				) ) }
		</>
	);
}
