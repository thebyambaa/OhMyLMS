import { Button, Notice, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { useRequest } from '../../hooks/useRequest';
import { useOperation } from '../../hooks/useOperation';
import { newId } from '../../lib/uuid';
import { createLesson, listLessons } from './api';

export default function LessonsPage() {
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ title, setTitle ] = useState( '' );
	const [ requestId, setRequestId ] = useState( newId );
	const navigate = useNavigate();
	const load = useCallback(
		( signal: AbortSignal ) => listLessons( page, search, signal ),
		[ page, search ]
	);
	const request = useRequest( load );
	const operation = useOperation();
	return (
		<section>
			<h1>{ __( 'Lessons', 'ohmylms' ) }</h1>
			<TextControl
				label={ __( 'Search lessons', 'ohmylms' ) }
				value={ search }
				onChange={ ( value ) => {
					setSearch( value );
					setPage( 1 );
				} }
				maxLength={ 100 }
			/>
			{ request.loading && (
				<p role="status">{ __( 'Loading lessons…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ! request.loading && request.data && (
				<>
					<ul>
						{ request.data.items.map( ( lesson ) => (
							<li key={ lesson.id }>
								<Link
									to={ `/content-hub/lessons/${ lesson.id }` }
								>
									{ lesson.title }
								</Link>{ ' ' }
								— { lesson.status }
							</li>
						) ) }
					</ul>
					{ ! request.data.items.length && (
						<p>{ __( 'No lessons found.', 'ohmylms' ) }</p>
					) }
					<Button
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'ohmylms' ) }
					</Button>
					<span>
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
			<h2>{ __( 'Create a draft lesson', 'ohmylms' ) }</h2>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					void operation.run(
						( signal ) => createLesson( title, requestId, signal ),
						( lesson ) =>
							navigate( `/content-hub/lessons/${ lesson.id }` )
					);
				} }
			>
				<TextControl
					label={ __( 'Lesson title', 'ohmylms' ) }
					required
					maxLength={ 190 }
					value={ title }
					disabled={ operation.busy }
					onChange={ ( value ) => {
						setTitle( value );
						setRequestId( newId() );
					} }
				/>
				<Button
					type="submit"
					variant="primary"
					disabled={ operation.busy || ! title.trim() }
					isBusy={ operation.busy }
				>
					{ __( 'Create draft lesson', 'ohmylms' ) }
				</Button>
			</form>
		</section>
	);
}
