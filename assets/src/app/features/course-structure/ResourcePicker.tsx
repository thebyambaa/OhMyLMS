import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { createResource, findResources, type ResourceType } from './api';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { newId } from '../../lib/uuid';
import { useBankTerms } from '../content-bank';

const TITLES: Record< ResourceType, () => string > = {
	lesson: () => __( 'Add a lesson', 'ohmylms' ),
	quiz: () => __( 'Add a quiz', 'ohmylms' ),
	assignment: () => __( 'Add an assignment', 'ohmylms' ),
};

/**
 * Pick an existing lesson, quiz or assignment, or create a draft through its own API.
 * The placement itself is only saved with the outline draft.
 *
 * @param root0
 * @param root0.course
 * @param root0.type
 * @param root0.exclude
 * @param root0.onSelect
 * @param root0.onClose
 */
export function ResourcePicker( {
	course,
	type,
	exclude = [],
	onSelect,
	onClose,
}: {
	course: number;
	type: ResourceType;
	exclude?: readonly number[];
	onSelect: ( resource: { id: number; title: string } ) => void;
	onClose: () => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ folder, setFolder ] = useState( '' );
	const [ tag, setTag ] = useState( '' );
	const bank = useBankTerms();
	const [ title, setTitle ] = useState( '' );
	const [ requestId ] = useState( newId );
	const creation = useOperation();
	const load = useCallback(
		( signal: AbortSignal ) =>
			findResources( course, type, search, page, signal, {
				folder,
				tag,
			} ),
		[ course, type, search, page, folder, tag ]
	);
	const request = useRequest( load );
	const items =
		request.data?.items.filter(
			( resource ) => ! exclude.includes( resource.id )
		) ?? [];
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
			{ ( bank.paths.length > 0 || bank.terms.tags.length > 0 ) && (
				<>
					<SelectControl
						label={ __( 'Folder', 'ohmylms' ) }
						value={ folder }
						options={ [
							{
								value: '',
								label: __( 'All folders', 'ohmylms' ),
							},
							...bank.paths.map( ( item ) => ( {
								value: String( item.id ),
								label: item.label,
							} ) ),
						] }
						onChange={ ( value ) => {
							setPage( 1 );
							setFolder( value );
						} }
					/>
					<SelectControl
						label={ __( 'Tag', 'ohmylms' ) }
						value={ tag }
						options={ [
							{ value: '', label: __( 'All tags', 'ohmylms' ) },
							...bank.terms.tags.map( ( item ) => ( {
								value: String( item.id ),
								label: item.name,
							} ) ),
						] }
						onChange={ ( value ) => {
							setPage( 1 );
							setTag( value );
						} }
					/>
				</>
			) }
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
						{ items.map( ( resource ) => (
							<li key={ resource.id }>
								<Button onClick={ () => onSelect( resource ) }>
									{ resource.title }
								</Button>
							</li>
						) ) }
					</ul>
					{ ! items.length && (
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
			{
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						if ( ! title.trim() ) {
							return;
						}
						void creation.run(
							( signal ) =>
								createResource(
									type,
									title,
									signal,
									requestId
								),
							onSelect
						);
					} }
				>
					<h2>
						{
							{
								lesson: __(
									'Or create a draft lesson',
									'ohmylms'
								),
								quiz: __( 'Or create a draft quiz', 'ohmylms' ),
								assignment: __(
									'Or create a draft assignment',
									'ohmylms'
								),
							}[ type ]
						}
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
			}
		</Modal>
	);
}
