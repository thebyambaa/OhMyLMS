import { Button, Notice, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { useRequest } from '../../hooks/useRequest';
import { useOperation } from '../../hooks/useOperation';
import { newId } from '../../lib/uuid';
import { ListTable } from '../../../ui/ListTable';
import { createLesson, listLessons } from './api';

type LessonRow = Awaited<
	ReturnType< typeof listLessons >
>[ 'items' ][ number ];

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
			{ ( request.loading || request.data ) && (
				<ListTable< LessonRow >
					caption={ __( 'Lessons', 'ohmylms' ) }
					busy={ request.loading }
					rows={ request.data?.items ?? [] }
					rowKey={ ( lesson ) => lesson.id }
					emptyMessage={
						request.loading
							? ''
							: __( 'No lessons found.', 'ohmylms' )
					}
					search={ {
						label: __( 'Search lessons', 'ohmylms' ),
						submitLabel: __( 'Search Lessons', 'ohmylms' ),
						value: search,
						maxLength: 100,
						onChange: ( value ) => {
							setSearch( value );
							setPage( 1 );
						},
					} }
					pagination={ {
						page,
						totalPages: request.data?.pages ?? 1,
						onChange: setPage,
					} }
					columns={ [
						{
							id: 'title',
							label: __( 'Title', 'ohmylms' ),
							primary: true,
							render: ( lesson ) => (
								<strong>
									<Link
										className="row-title"
										to={ `/content-hub/lessons/${ lesson.id }` }
									>
										{ lesson.title }
									</Link>
								</strong>
							),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( lesson ) => lesson.status,
						},
					] }
					rowActions={ ( lesson ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							render: (
								<Link
									to={ `/content-hub/lessons/${ lesson.id }` }
								>
									{ __( 'Edit', 'ohmylms' ) }
								</Link>
							),
						},
					] }
				/>
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
