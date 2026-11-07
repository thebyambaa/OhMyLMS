import { Button, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { EmptyState } from '../../../ui/EmptyState';
import { useRequest } from '../../hooks/useRequest';
import { readWorkspace, type CurriculumItem } from './api';
import { DeleteItemDialog } from './DeleteItemDialog';
import { ItemDetail, type Pane } from './ItemDetail';
import { ItemDialog } from './ItemDialog';
import { WorkspaceTree } from './WorkspaceTree';
import './CurriculumWorkspace.css';

const PANES: readonly Pane[] = [ 'skills', 'courses', 'content' ];

type Dialog =
	| { kind: 'add'; parent: number }
	| { kind: 'edit'; item: CurriculumItem }
	| { kind: 'delete'; item: CurriculumItem };

/**
 * Curriculum: one screen for the whole curriculum. The tree of curricula, topics and groups is on the left;
 * the open item is on the right with its skills, the courses placed on it and the question banks and
 * quizzes linked to it. `?item=` and `?pane=` keep the place in the address.
 */
export default function CurriculumWorkspace() {
	const load = useCallback(
		( signal: AbortSignal ) => readWorkspace( signal ),
		[]
	);
	const request = useRequest( load, { keepData: true } );
	const [ params, setParams ] = useSearchParams();
	const [ dialog, setDialog ] = useState< Dialog | null >( null );
	const [ message, setMessage ] = useState( '' );
	const items = request.data?.items ?? [];
	const asked = Number( params.get( 'item' ) ) || 0;
	const open = items.find( ( item ) => item.id === asked ) ?? null;
	const pane =
		PANES.find( ( name ) => name === params.get( 'pane' ) ) ?? 'skills';
	const choose = ( id: number | null, next?: Pane ) =>
		setParams(
			( current ) => {
				const query = new URLSearchParams( current );
				if ( id ) {
					query.set( 'item', String( id ) );
				} else {
					query.delete( 'item' );
				}
				if ( next ) {
					query.set( 'pane', next );
				}
				return query;
			},
			{ replace: false }
		);
	const nameOf = ( id: number ) =>
		items.find( ( item ) => item.id === id )?.name ?? '';
	return (
		<section
			className="oml-cw"
			aria-label={ __( 'Curriculum', 'ohmylms' ) }
		>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
					<Button variant="link" onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ request.loading && ! request.data && (
				<p role="status">{ __( 'Loading curriculum…', 'ohmylms' ) }</p>
			) }
			{ request.data && 0 === items.length && (
				<EmptyState
					title={ __( 'No curriculum yet', 'ohmylms' ) }
					description={ __(
						'Start with a subject or a syllabus. Topics, skills and courses go inside it.',
						'ohmylms'
					) }
				>
					<Button
						variant="primary"
						onClick={ () =>
							setDialog( { kind: 'add', parent: 0 } )
						}
					>
						{ __( 'Add the first item', 'ohmylms' ) }
					</Button>
				</EmptyState>
			) }
			{ items.length > 0 && request.data && (
				<div className="oml-cw-grid">
					<WorkspaceTree
						items={ items }
						counts={ request.data.counts }
						selected={ open?.id ?? null }
						onSelect={ ( id ) => choose( id ) }
						onAdd={ () => setDialog( { kind: 'add', parent: 0 } ) }
					/>
					{ open ? (
						<ItemDetail
							key={ open.id }
							item={ open }
							items={ items }
							pane={ pane }
							onPane={ ( next ) => choose( open.id, next ) }
							onSelect={ ( id ) => choose( id ) }
							onEdit={ () =>
								setDialog( { kind: 'edit', item: open } )
							}
							onAddChild={ () =>
								setDialog( { kind: 'add', parent: open.id } )
							}
							onDelete={ () =>
								setDialog( { kind: 'delete', item: open } )
							}
							onChanged={ request.retry }
						/>
					) : (
						<div className="oml-cw-detail oml-cw-start">
							<h2>
								{ __(
									'Pick something from the tree',
									'ohmylms'
								) }
							</h2>
							<p>
								{ __(
									'Open a curriculum, topic or group to see its skills, place courses on it and link question banks and quizzes.',
									'ohmylms'
								) }
							</p>
						</div>
					) }
				</div>
			) }
			{ dialog && 'delete' !== dialog.kind && (
				<ItemDialog
					item={ 'edit' === dialog.kind ? dialog.item : null }
					parent={ 'add' === dialog.kind ? dialog.parent : 0 }
					parentName={
						'add' === dialog.kind ? nameOf( dialog.parent ) : ''
					}
					types={ request.data?.types ?? [] }
					onClose={ () => setDialog( null ) }
					onSaved={ ( saved ) => {
						const adding = 'add' === dialog.kind;
						setDialog( null );
						request.retry();
						setMessage(
							adding
								? __( 'Curriculum item added.', 'ohmylms' )
								: __( 'Curriculum item saved.', 'ohmylms' )
						);
						choose( saved.id );
					} }
				/>
			) }
			{ dialog && 'delete' === dialog.kind && (
				<DeleteItemDialog
					item={ dialog.item }
					onClose={ () => setDialog( null ) }
					onDeleted={ () => {
						const parent = dialog.item.parent_id;
						setDialog( null );
						request.retry();
						setMessage(
							__( 'Curriculum item deleted.', 'ohmylms' )
						);
						choose( parent || null );
					} }
				/>
			) }
		</section>
	);
}
