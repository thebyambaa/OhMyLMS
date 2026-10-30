import { Button, Notice, TextControl } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { normalizeApiError } from '../../lib/api';
import { createCourse, listCourses } from './api';
import { useRequest } from '../../hooks/useRequest';

export default function CoursesPage() {
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ title, setTitle ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	const navigate = useNavigate();
	const load = useCallback(
		( signal: AbortSignal ) => listCourses( page, query, signal ),
		[ page, query ]
	);
	const request = useRequest( load );
	async function create() {
		if ( operation.current || ! title.trim() ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( '' );
		try {
			const course = await createCourse(
				title.trim(),
				controller.signal
			);
			if ( ! controller.signal.aborted ) {
				navigate( `/content-hub/courses/${ course.id }` );
			}
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setError( normalizeApiError( failure ).message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				operation.current = null;
				setBusy( false );
			}
		}
	}
	return (
		<section aria-label={ __( 'Course structures', 'ohmylms' ) }>
			<h1>{ __( 'Courses', 'ohmylms' ) }</h1>
			<p>
				<Link to="/content-hub/curriculum">
					{ __( 'Manage curriculum', 'ohmylms' ) }
				</Link>
			</p>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					setPage( 1 );
					setQuery( search );
				} }
			>
				<TextControl
					label={ __( 'Search courses', 'ohmylms' ) }
					value={ search }
					onChange={ setSearch }
					maxLength={ 100 }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.loading && (
				<p role="status">{ __( 'Loading courses…', 'ohmylms' ) }</p>
			) }
			{ request.data && (
				<>
					<ul>
						{ request.data.items.map( ( course ) => (
							<li key={ course.id }>
								<Link
									to={ `/content-hub/courses/${ course.id }` }
								>
									{ course.title }
								</Link>{ ' ' }
								— { course.status }
							</li>
						) ) }
					</ul>
					{ ! request.data.items.length && (
						<p>{ __( 'No courses found.', 'ohmylms' ) }</p>
					) }
					<Button
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'ohmylms' ) }
					</Button>
					<span aria-live="polite">
						{ page } / { Math.max( 1, request.data.pages ) }
					</span>
					<Button
						disabled={ page >= request.data.pages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'ohmylms' ) }
					</Button>
				</>
			) }
			<h2>{ __( 'Create a draft course', 'ohmylms' ) }</h2>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					void create();
				} }
			>
				<TextControl
					label={ __( 'Course title', 'ohmylms' ) }
					value={ title }
					onChange={ setTitle }
					maxLength={ 190 }
					disabled={ busy }
				/>
				<Button
					type="submit"
					variant="primary"
					disabled={ busy || ! title.trim() }
					isBusy={ busy }
				>
					{ __( 'Create draft', 'ohmylms' ) }
				</Button>
			</form>
		</section>
	);
}
