import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useSearchParams } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { WidefatTable } from '../../components/WidefatTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	CONTENT_KINDS,
	isConflict,
	readContentLinks,
	searchContent,
	updateContentLinks,
	type ContentKind,
	type ContentLinks,
	type SkillRef,
} from './workspace';
import { contentRoute, kindLabel, SkillChooser } from './WorkspaceParts';

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
	function choose( next: { kind?: ContentKind; content?: number } ) {
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
				<LinkEditor
					key={ `${ kind }-${ content }` }
					kind={ kind }
					id={ content }
				/>
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

function LinkEditor( { kind, id }: { kind: ContentKind; id: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => readContentLinks( kind, id, signal ),
		[ kind, id ]
	);
	const request = useRequest( load );
	const [ links, setLinks ] = useState< ContentLinks | null >( null );
	const [ add, setAdd ] = useState< SkillRef[] >( [] );
	const [ remove, setRemove ] = useState< number[] >( [] );
	const [ stale, setStale ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const operation = useOperation();
	const current = links ?? request.data;
	if ( request.loading && ! links ) {
		return <p role="status">{ __( 'Loading links…', 'ohmylms' ) }</p>;
	}
	if ( ! current ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ request.error }
			</Notice>
		);
	}
	const linkedIds = current.skills.map( ( skill ) => skill.id );
	const dirty = add.length > 0 || remove.length > 0;
	function save( base: ContentLinks ) {
		void operation.run(
			async ( signal ) => {
				try {
					return await updateContentLinks(
						base,
						add.map( ( skill ) => skill.id ),
						remove,
						signal
					);
				} catch ( failure ) {
					setStale( isConflict( failure ) );
					throw failure;
				}
			},
			( saved ) => {
				setLinks( saved );
				setAdd( [] );
				setRemove( [] );
				setStale( false );
				setMessage( __( 'Links saved.', 'ohmylms' ) );
			}
		);
	}
	async function reloadKeeping() {
		const fresh = await readContentLinks( kind, id ).catch( () => null );
		if ( ! fresh ) {
			return;
		}
		const ids = fresh.skills.map( ( skill ) => skill.id );
		setLinks( fresh );
		setAdd( ( list ) =>
			list.filter( ( skill ) => ! ids.includes( skill.id ) )
		);
		setRemove( ( list ) =>
			list.filter( ( skill ) => ids.includes( skill ) )
		);
		setStale( false );
		operation.clearError();
		setMessage(
			__(
				'Reloaded the latest links. Your unsaved changes were kept.',
				'ohmylms'
			)
		);
	}
	return (
		<section aria-labelledby="oml-ws-links">
			<h4 id="oml-ws-links">
				<Link to={ contentRoute( kind, current.content.id ) }>
					{ current.content.title }
				</Link>{ ' ' }
				<small>{ kindLabel( kind ) }</small>
			</h4>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ stale ? (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Someone changed the skills of this content after you opened it.',
						'ohmylms'
					) }{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => void reloadKeeping() }
					>
						{ __( 'Reload links and keep my changes', 'ohmylms' ) }
					</Button>
				</Notice>
			) : (
				operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				)
			) }
			<WidefatTable caption={ __( 'Linked skills', 'ohmylms' ) }>
				<thead>
					<tr>
						<th scope="col">{ __( 'Remove', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Skill', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Link', 'ohmylms' ) }</th>
						<th scope="col">{ __( 'Syllabuses', 'ohmylms' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ ! current.skills.length && ! add.length && (
						<tr>
							<td colSpan={ 4 }>
								{ __( 'No linked skills yet.', 'ohmylms' ) }
							</td>
						</tr>
					) }
					{ current.skills.map( ( skill ) => (
						<tr key={ skill.id }>
							<td>
								<input
									type="checkbox"
									aria-label={ sprintf(
										/* translators: %s: skill. */
										__( 'Remove %s', 'ohmylms' ),
										skill.name
									) }
									disabled={ 'primary' === skill.role }
									checked={ remove.includes( skill.id ) }
									onChange={ ( event ) => {
										const checked = event.target.checked;
										setRemove( ( list ) =>
											checked
												? [ ...list, skill.id ]
												: list.filter(
														( entry ) =>
															entry !== skill.id
													)
										);
									} }
								/>
							</td>
							<th scope="row">
								{ skill.code
									? `${ skill.code } ${ skill.name }`
									: skill.name }
								{ skill.archived &&
									` (${ __( 'archived', 'ohmylms' ) })` }
							</th>
							<td>
								{
									{
										linked: skill.shared
											? __( 'Shared skill', 'ohmylms' )
											: __( 'Objective', 'ohmylms' ),
										primary: __(
											'Primary skill',
											'ohmylms'
										),
										supporting: __(
											'Supporting skill',
											'ohmylms'
										),
									}[ skill.role ]
								}
							</td>
							<td>{ skill.syllabuses.join( ', ' ) || '—' }</td>
						</tr>
					) ) }
					{ add.map( ( skill ) => (
						<tr key={ `add-${ skill.id }` }>
							<td>
								<Button
									variant="link"
									onClick={ () =>
										setAdd( ( list ) =>
											list.filter(
												( entry ) =>
													entry.id !== skill.id
											)
										)
									}
								>
									{ __( 'Undo', 'ohmylms' ) }
								</Button>
							</td>
							<th scope="row">{ skill.name }</th>
							<td>{ __( 'To be linked', 'ohmylms' ) }</td>
							<td>—</td>
						</tr>
					) ) }
				</tbody>
			</WidefatTable>
			<SkillChooser
				label={ __( 'Add a skill or objective', 'ohmylms' ) }
				exclude={ [
					...linkedIds,
					...add.map( ( skill ) => skill.id ),
				] }
				disabled={ operation.busy }
				onChoose={ ( skill ) =>
					setAdd( ( list ) => [ ...list, skill ] )
				}
			/>
			<Button
				variant="primary"
				disabled={ ! dirty || operation.busy || stale }
				isBusy={ operation.busy }
				onClick={ () => save( current ) }
			>
				{ sprintf(
					/* translators: 1: skills to link, 2: skills to unlink. */
					__( 'Save links (+%1$d, −%2$d)', 'ohmylms' ),
					add.length,
					remove.length
				) }
			</Button>
		</section>
	);
}
