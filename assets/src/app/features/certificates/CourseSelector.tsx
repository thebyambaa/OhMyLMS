import {
	Button,
	CheckboxControl,
	Notice,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { listCourses } from './api';
import type { CourseChoice, CourseRef } from './types';

interface Props {
	selected: CourseRef[];
	disabled: boolean;
	max: number;
	error?: string;
	onChange: ( courses: CourseRef[] ) => void;
}

// Assign courses to a template: assigned courses with remove buttons, plus a searchable, paged list.
export function CourseSelector( {
	selected,
	disabled,
	max,
	error,
	onChange,
}: Props ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState< Collection< CourseChoice > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ failure, setFailure ] = useState< string | null >( null );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setFailure( null );
		listCourses( search, page, controller.signal ).then(
			( next ) => {
				if ( ! controller.signal.aborted ) {
					setData( next );
					setLoading( false );
				}
			},
			( problem: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setFailure( normalizeApiError( problem ).message );
					setLoading( false );
				}
			}
		);
		return () => controller.abort();
	}, [ search, page ] );
	const isSelected = ( id: number ) =>
		selected.some( ( course ) => course.id === id );
	const runSearch = () => {
		setPage( 1 );
		setSearch( query );
	};
	return (
		<fieldset disabled={ disabled }>
			<legend>{ __( 'Courses', 'ohmylms' ) }</legend>
			<p>
				{ __(
					'A course uses one template. Assigning a course here moves it from any other template.',
					'ohmylms'
				) }
			</p>
			{ error && <p role="alert">{ error }</p> }
			{ selected.length ? (
				<ul aria-label={ __( 'Assigned courses', 'ohmylms' ) }>
					{ selected.map( ( course ) => (
						<li key={ course.id }>
							{ course.title }{ ' ' }
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
									/* translators: %s: course title. */
									__( 'Remove course %s', 'ohmylms' ),
									course.title
								) }
							>
								{ __( 'Remove', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( 'No courses assigned.', 'ohmylms' ) }</p>
			) }
			<div role="search" aria-label={ __( 'Search courses', 'ohmylms' ) }>
				<TextControl
					label={ __( 'Search courses', 'ohmylms' ) }
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
					disabled={ loading }
					onClick={ runSearch }
				>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</div>
			{ failure && (
				<Notice status="error" isDismissible={ false }>
					{ failure }
				</Notice>
			) }
			{ loading && (
				<p role="status">{ __( 'Loading courses…', 'ohmylms' ) }</p>
			) }
			{ ! loading && ! failure && ! data.items.length && (
				<p>{ __( 'No courses found.', 'ohmylms' ) }</p>
			) }
			{ ! loading && data.items.length > 0 && (
				<div
					role="group"
					aria-label={ __( 'Available courses', 'ohmylms' ) }
				>
					{ data.items.map( ( course ) => (
						<CheckboxControl
							key={ course.id }
							label={ course.title }
							checked={ isSelected( course.id ) }
							disabled={
								! isSelected( course.id ) &&
								selected.length >= max
							}
							onChange={ ( checked ) =>
								onChange(
									checked
										? [
												...selected,
												{
													id: course.id,
													title: course.title,
												},
											]
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
				page={ page }
				pages={ data.totalPages }
				busy={ loading }
				onChange={ setPage }
			/>
		</fieldset>
	);
}
