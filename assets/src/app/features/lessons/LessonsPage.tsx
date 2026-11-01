import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { useListState } from '../../components/listQuery';
import { useRequest } from '../../hooks/useRequest';
import { useOperation } from '../../hooks/useOperation';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { newId } from '../../lib/uuid';
import { ListTable } from '../../../ui/ListTable';
import { createLesson, listLessons } from './api';
import {
	LearnerPreviewNotices,
	useLearnerPreview,
} from './LearnerPreviewButton';

type LessonRow = Awaited<
	ReturnType< typeof listLessons >
>[ 'items' ][ number ];

const NO_FILTERS = { search: '' };

/**
 * Lesson list (`tab=lessons`): paged and searchable, with the search and page in the address. "Add
 * lesson" names a draft in a dialog and opens it in the lesson editor.
 */
export default function LessonsPage() {
	const list = useListState( NO_FILTERS, { search: 's' } );
	const { page, setPage } = list;
	const search = list.filters.search;
	const [ draft, setDraft ] = useState( search );
	useEffect( () => setDraft( search ), [ search ] );
	const [ creating, setCreating ] = useState( false );
	const [ title, setTitle ] = useState( '' );
	const [ requestId, setRequestId ] = useState( newId );
	const navigate = useNavigate();
	const load = useCallback(
		( signal: AbortSignal ) => listLessons( page, search, signal ),
		[ page, search ]
	);
	const request = useRequest( load );
	const operation = useOperation();
	const preview = useLearnerPreview();
	return (
		<section>
			<PageTitleActions>
				<PageTitleAction
					onClick={ () => {
						setTitle( '' );
						setRequestId( newId() );
						operation.clearError();
						setCreating( true );
					} }
				>
					{ __( 'Add lesson', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
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
			<LearnerPreviewNotices
				error={ preview.error }
				blocked={ preview.blocked }
			/>
			{ ( request.loading || request.data ) && (
				<ListTable< LessonRow >
					caption={ __( 'Lessons', 'ohmylms' ) }
					heading={ __( 'Lessons list', 'ohmylms' ) }
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
						value: draft,
						maxLength: 100,
						onChange: setDraft,
						onSubmit: () =>
							list.setFilters( { search: draft.trim() } ),
					} }
					pagination={ {
						page,
						totalPages: request.data?.pages ?? 1,
						onChange: setPage,
						href: list.pageHref,
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
						{
							key: 'view',
							label: __( 'Preview as learner', 'ohmylms' ),
							ariaLabel: sprintf(
								/* translators: %s: lesson title. */
								__( 'Preview “%s” as a learner', 'ohmylms' ),
								lesson.title
							),
							disabled: preview.busy,
							onClick: () => preview.open( lesson.id ),
						},
					] }
				/>
			) }
			{ creating && (
				<Modal
					title={ __( 'Create a draft lesson', 'ohmylms' ) }
					onRequestClose={ () => {
						if ( ! operation.busy ) {
							setCreating( false );
						}
					} }
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<form
						onSubmit={ ( event ) => {
							event.preventDefault();
							void operation.run(
								( signal ) =>
									createLesson( title, requestId, signal ),
								( lesson ) =>
									navigate(
										`/content-hub/lessons/${ lesson.id }`
									)
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
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							disabled={ operation.busy }
							onClick={ () => setCreating( false ) }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</form>
				</Modal>
			) }
		</section>
	);
}
