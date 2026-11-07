import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useListState } from '../../components/listQuery';
import { useRequest } from '../../hooks/useRequest';
import { useOperation } from '../../hooks/useOperation';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { newId } from '../../lib/uuid';
import { ListTable } from '../../../ui/ListTable';
import { BankOrganizer, useBankControls } from '../content-bank';
import { createLesson, listLessons } from './api';
import { blockEditorUrl } from './editUrl';
import {
	LearnerPreviewNotices,
	useLearnerPreview,
} from './LearnerPreviewButton';

type LessonRow = Awaited<
	ReturnType< typeof listLessons >
>[ 'items' ][ number ];

const NO_FILTERS = { search: '', folder: '', tag: '' };

/**
 * Lesson list (`tab=lessons`): paged and searchable, with the search and page in the address. "Add
 * lesson" names a draft in a dialog and opens it in the WordPress block editor.
 */
export default function LessonsPage() {
	const list = useListState( NO_FILTERS, {
		search: 's',
		folder: 'bank_folder',
		tag: 'bank_tag',
	} );
	const { page, setPage } = list;
	const { search, folder, tag } = list.filters;
	const [ draft, setDraft ] = useState( search );
	useEffect( () => setDraft( search ), [ search ] );
	const [ creating, setCreating ] = useState( false );
	const [ title, setTitle ] = useState( '' );
	const [ requestId, setRequestId ] = useState( newId );
	const load = useCallback(
		( signal: AbortSignal ) =>
			listLessons( page, search, signal, { folder, tag } ),
		[ page, search, folder, tag ]
	);
	const request = useRequest( load );
	const bank = useBankControls( {
		folder,
		tag,
		ids: ( request.data?.items ?? [] ).map( ( lesson ) => lesson.id ),
		onFilter: ( nextFolder, nextTag ) =>
			list.setFilters( {
				search,
				folder: nextFolder,
				tag: nextTag,
			} ),
	} );
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
			{ bank.notices }
			<BankOrganizer folders={ bank.paths } onCreate={ bank.addTerm } />
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
					rowLabel={ ( lesson ) => lesson.title }
					filters={ bank.filters }
					filterLabel={ null }
					bulkActions={ bank.bulkActions }
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
							list.setFilters( {
								search: draft.trim(),
								folder,
								tag,
							} ),
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
									<a
										className="row-title"
										href={ blockEditorUrl( lesson.id ) }
									>
										{ lesson.title }
									</a>
								</strong>
							),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( lesson ) => lesson.status,
						},
						bank.column,
					] }
					rowActions={ ( lesson ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							href: blockEditorUrl( lesson.id ),
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
									window.location.assign(
										blockEditorUrl( lesson.id )
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
