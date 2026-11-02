import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { ContentSkillConnections } from './ContentSkillConnections';
import { useRequest } from '../../hooks/useRequest';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import '../../styles/authoring.css';
import { CONTENT_KINDS, searchContent, type ContentKind } from './workspace';
import { kindLabel } from './WorkspaceParts';

function kindOf( value: string | null ): ContentKind {
	return CONTENT_KINDS.find( ( kind ) => kind === value ) ?? 'lesson';
}

/**
 * Link one lesson, quiz, assignment, course or question to several skills and syllabus objectives
 * (`?kind=&content=`). Changes are staged and saved together against the links the author read.
 */
export function ContentLinksPanel() {
	const [ params, setParams ] = useSearchParams();
	const kind = kindOf( params.get( 'kind' ) );
	const content = Number( params.get( 'content' ) );
	const [ dirty, setDirty ] = useState( false );
	const [ pending, setPending ] = useState< {
		kind?: ContentKind;
		content?: number;
	} | null >( null );
	const guard = useUnsavedChangesGuard( dirty );
	function choose( next: { kind?: ContentKind; content?: number } ) {
		if ( dirty ) {
			setPending( next );
			return;
		}
		applyChoice( next );
	}
	function applyChoice( next: { kind?: ContentKind; content?: number } ) {
		const query = new URLSearchParams( params );
		query.set( 'kind', next.kind ?? kind );
		if ( next.content ) {
			query.set( 'content', String( next.content ) );
		} else {
			query.delete( 'content' );
		}
		setParams( query );
	}
	return (
		<section aria-labelledby="oml-ws-content">
			<h3 id="oml-ws-content">
				{ __( 'Shared content links', 'ohmylms' ) }
			</h3>
			<p>
				{ __(
					'Link one piece of content to every skill and syllabus objective it teaches or assesses. A question’s primary skill is changed in the question editor; here you add or remove its supporting skills.',
					'ohmylms'
				) }
			</p>
			<SelectControl
				label={ __( 'Content type', 'ohmylms' ) }
				value={ kind }
				options={ CONTENT_KINDS.map( ( value ) => ( {
					value,
					label: kindLabel( value ),
				} ) ) }
				onChange={ ( value ) => choose( { kind: kindOf( value ) } ) }
			/>
			<ContentSearch
				key={ kind }
				kind={ kind }
				onChoose={ ( id ) => choose( { content: id } ) }
			/>
			{ Number.isSafeInteger( content ) && content > 0 && (
				<ContentSkillConnections
					key={ `${ kind }-${ content }` }
					kind={ kind }
					id={ content }
					onDirtyChange={ setDirty }
				/>
			) }
			{ pending && (
				<Modal
					title={ __(
						'Discard unsaved skill connections?',
						'ohmylms'
					) }
					onRequestClose={ () => setPending( null ) }
				>
					<Button onClick={ () => setPending( null ) }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button
						onClick={ () => {
							setDirty( false );
							applyChoice( pending );
							setPending( null );
						} }
					>
						{ __( 'Discard and switch', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __(
						'Leave with unsaved skill connections?',
						'ohmylms'
					) }
					onRequestClose={ guard.stay }
				>
					<Button onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button onClick={ guard.leave }>
						{ __( 'Discard edits and leave', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}

function ContentSearch( {
	kind,
	onChoose,
}: {
	kind: ContentKind;
	onChoose: ( id: number ) => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const load = useCallback(
		( signal: AbortSignal ) => searchContent( kind, search, page, signal ),
		[ kind, search, page ]
	);
	const request = useRequest( load );
	return (
		<>
			<form
				role="search"
				aria-label={ __( 'Find content', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setPage( 1 );
					setSearch( query.trim() );
				} }
			>
				<TextControl
					label={ sprintf(
						/* translators: %s: content type. */
						__( 'Search %s', 'ohmylms' ),
						kindLabel( kind ).toLowerCase()
					) }
					value={ query }
					maxLength={ 200 }
					onChange={ setQuery }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ request.data && (
				<>
					<ul aria-label={ __( 'Content found', 'ohmylms' ) }>
						{ request.data.items.map( ( item ) => (
							<li key={ item.id }>
								<Button
									variant="link"
									disabled={ ! item.editable }
									onClick={ () => onChoose( item.id ) }
								>
									{ item.title ||
										sprintf(
											/* translators: %d: ID. */
											__( '#%d', 'ohmylms' ),
											item.id
										) }
								</Button>{ ' ' }
								<small>{ item.status }</small>
							</li>
						) ) }
					</ul>
					{ ! request.data.items.length && (
						<p>{ __( 'Nothing found.', 'ohmylms' ) }</p>
					) }
					{ request.data.pages > 1 && (
						<Pagination
							page={ page }
							pages={ request.data.pages }
							busy={ request.loading }
							onChange={ setPage }
						/>
					) }
				</>
			) }
		</>
	);
}
