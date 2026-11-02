import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { createResource, findResources, type ResourceType } from './api';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';

const TITLES: Record< ResourceType, () => string > = {
	lesson: () => __( 'Attach an existing lesson', 'ohmylms' ),
	quiz: () => __( 'Add a quiz', 'ohmylms' ),
	assignment: () => __( 'Add an assignment', 'ohmylms' ),
};

/**
 * Pick an existing lesson, quiz or assignment, or create a draft quiz or assignment through its own API.
 * The placement itself is only saved with the outline draft.
 *
 * @param root0
 * @param root0.course
 * @param root0.type
 * @param root0.onSelect
 * @param root0.onClose
 */
export function ResourcePicker( {
	course,
	type,
	onSelect,
	onClose,
}: {
	course: number;
	type: ResourceType;
	onSelect: ( resource: { id: number; title: string } ) => void;
	onClose: () => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ title, setTitle ] = useState( '' );
	const creation = useOperation();
	const load = useCallback(
		( signal: AbortSignal ) =>
			findResources( course, type, search, page, signal ),
		[ course, type, search, page ]
	);
	const request = useRequest( load );
	return (
		<Modal title={ TITLES[ type ]() } onRequestClose={ onClose }>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					setPage( 1 );
					setSearch( query );
				} }
			>
				<TextControl
					label={ __( 'Search by title', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
					maxLength={ 100 }
				/>
				<Button type="submit">{ __( 'Search', 'ohmylms' ) }</Button>
			</form>
			{ request.loading && (
				<p role="status">{ __( 'Loading…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<p role="alert">
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</p>
			) }
			{ request.data && (
				<>
					<ul>
						{ request.data.items.map( ( resource ) => (
							<li key={ resource.id }>
								<Button onClick={ () => onSelect( resource ) }>
									{ resource.title }
								</Button>
							</li>
						) ) }
					</ul>
					{ ! request.data.items.length && (
						<p>
							{ __(
								'No editable, unplaced items found.',
								'ohmylms'
							) }
						</p>
					) }
					<Button
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'ohmylms' ) }
					</Button>
					<span>{ page }</span>
					<Button
						disabled={ page >= request.data.pages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'ohmylms' ) }
					</Button>
				</>
			) }
			{ 'lesson' !== type && (
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						if ( ! title.trim() ) {
							return;
						}
						void creation.run(
							( signal ) => createResource( type, title, signal ),
							onSelect
						);
					} }
				>
					<h2>
						{ 'quiz' === type
							? __( 'Or create a draft quiz', 'ohmylms' )
							: __( 'Or create a draft assignment', 'ohmylms' ) }
					</h2>
					{ creation.error && (
						<Notice status="error" isDismissible={ false }>
							{ creation.error }
						</Notice>
					) }
					<TextControl
						label={ __( 'New title', 'ohmylms' ) }
						value={ title }
						maxLength={ 200 }
						onChange={ setTitle }
						disabled={ creation.busy }
					/>
					<Button
						type="submit"
						variant="secondary"
						disabled={ creation.busy || ! title.trim() }
						isBusy={ creation.busy }
					>
						{ __( 'Create draft and add', 'ohmylms' ) }
					</Button>
				</form>
			) }
		</Modal>
	);
}
