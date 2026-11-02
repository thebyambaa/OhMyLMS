import {
	Button,
	CheckboxControl,
	Notice,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import { usePagedList } from '../../hooks/usePagedList';
import { listCourses } from './api';
import type { CourseRef } from './types';

function loadCourses( page: number, search: string, signal: AbortSignal ) {
	return listCourses( search, page, signal );
}

interface Props {
	/** Visible group name, for example "Included courses". */
	legend: string;
	/** Text under the legend explaining what the selection does. */
	help: string;
	selected: CourseRef[];
	disabled: boolean;
	max: number;
	error?: string;
	errorId: string;
	onChange: ( courses: CourseRef[] ) => void;
}

/**
 * Pick courses: the selection with remove buttons, plus a searchable, paged (10 per page) list of
 * courses to add. Results are bounded by the server, never the whole catalogue.
 *
 * @param props Component props.
 */
export function CourseSelector( props: Props ) {
	const { legend, help, selected, disabled, max, error, errorId, onChange } =
		props;
	const [ query, setQuery ] = useState( '' );
	const list = usePagedList( loadCourses, { keepPrevious: true } );
	const isSelected = ( id: number ) =>
		selected.some( ( course ) => course.id === id );
	const runSearch = () => list.setSearch( query );
	const searchLabel = sprintf(
		/* translators: %s: group name such as "Included courses". */
		__( 'Search courses for %s', 'ohmylms' ),
		legend
	);
	return (
		<fieldset
			disabled={ disabled }
			aria-describedby={ error ? errorId : undefined }
		>
			<legend>{ legend }</legend>
			<p>{ help }</p>
			{ error && (
				<p id={ errorId } role="alert">
					{ error }
				</p>
			) }
			{ selected.length ? (
				<ul
					aria-label={ sprintf(
						/* translators: %s: group name such as "Included courses". */
						__( 'Selected: %s', 'ohmylms' ),
						legend
					) }
				>
					{ selected.map( ( course ) => (
						<li key={ course.id }>
							{ course.title }
							{ 'publish' !== course.status && (
								<em> { __( '(not published)', 'ohmylms' ) }</em>
							) }{ ' ' }
							<Button
								variant="link"
								isDestructive
								onClick={ () =>
									onChange(
										selected.filter(
											( item ) => item.id !== course.id
										)
									)
								}
								aria-label={ sprintf(
									/* translators: 1: course title; 2: group name. */
									__( 'Remove %1$s from %2$s', 'ohmylms' ),
									course.title,
									legend
								) }
							>
								{ __( 'Remove', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( 'No courses selected.', 'ohmylms' ) }</p>
			) }
			<div role="search" aria-label={ searchLabel }>
				<TextControl
					label={ searchLabel }
					value={ query }
					onChange={ setQuery }
					onKeyDown={ ( event ) => {
						if ( 'Enter' === event.key ) {
							event.preventDefault();
							runSearch();
						}
					} }
				/>
				<Button
					variant="secondary"
					disabled={ list.loading }
					onClick={ runSearch }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</div>
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }{ ' ' }
					<Button variant="link" onClick={ list.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.loading && (
				<p role="status">{ __( 'Loading courses…', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && ! list.error && ! list.data.items.length && (
				<p>{ __( 'No courses found.', 'ohmylms' ) }</p>
			) }
			{ ! list.loading && list.data.items.length > 0 && (
				<div
					role="group"
					aria-label={ sprintf(
						/* translators: %s: group name such as "Included courses". */
						__( 'Courses available for %s', 'ohmylms' ),
						legend
					) }
				>
					{ list.data.items.map( ( course ) => (
						<CheckboxControl
							key={ course.id }
							label={
								'publish' === course.status
									? course.title
									: sprintf(
											/* translators: %s: course title. */
											__(
												'%s (not published)',
												'ohmylms'
											),
											course.title
										)
							}
							checked={ isSelected( course.id ) }
							disabled={
								! isSelected( course.id ) &&
								selected.length >= max
							}
							onChange={ ( checked ) =>
								onChange(
									checked
										? [ ...selected, course ]
										: selected.filter(
												( item ) =>
													item.id !== course.id
											)
								)
							}
						/>
					) ) }
				</div>
			) }
			<Pagination
				page={ list.page }
				pages={ list.data.totalPages }
				busy={ list.loading }
				onChange={ list.setPage }
			/>
		</fieldset>
	);
}
