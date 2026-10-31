import { Button, Modal, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { command, readSyllabus } from './api';
import { ComparisonDialog } from './ComparisonDialog';
import { CsvImportDialog } from './CsvImportDialog';
import { FieldsDialog } from './FieldsDialog';
import { GroupList, type EditSelection, type MoveSelection } from './GroupList';
import { LibraryPicker } from './LibraryPicker';
import { MappingDialog } from './MappingDialog';
import { MindMap } from './MindMap';
import { MoveDialog } from './MoveDialog';
import type { Group, SendCommand, Skill, SyllabusOutline } from './model';
import { SpreadsheetView } from './SpreadsheetView';

const LAYOUTS = [ 'outline', 'mindmap', 'spreadsheet' ] as const;
type Layout = ( typeof LAYOUTS )[ number ];

function layoutLabel( layout: Layout ) {
	return {
		outline: __( 'Outline', 'ohmylms' ),
		mindmap: __( 'Mind map', 'ohmylms' ),
		spreadsheet: __( 'Spreadsheet', 'ohmylms' ),
	}[ layout ];
}

export default function SyllabusPage() {
	const id = Number( useParams().id );
	return Number.isSafeInteger( id ) && id > 0 ? (
		<LoadedSyllabus key={ id } id={ id } />
	) : (
		<p role="alert">{ __( 'Invalid syllabus ID.', 'ohmylms' ) }</p>
	);
}
function LoadedSyllabus( { id }: { id: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => readSyllabus( id, signal ),
		[ id ]
	);
	const request = useRequest( load );
	if ( request.loading ) {
		return <p role="status">{ __( 'Loading syllabus…', 'ohmylms' ) }</p>;
	}
	if ( request.error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ request.error }
				<Button onClick={ request.retry }>
					{ __( 'Retry', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	return request.data ? <SyllabusWorkspace initial={ request.data } /> : null;
}
export function SyllabusWorkspace( { initial }: { initial: SyllabusOutline } ) {
	const [ params ] = useSearchParams();
	const layout: Layout =
		LAYOUTS.find( ( id ) => id === params.get( 'layout' ) ) ?? 'outline';
	const [ data, setData ] = useState( initial );
	const operation = useOperation();
	const [ message, setMessage ] = useState( '' );
	const [ editor, setEditor ] = useState< EditSelection | null >( null );
	const [ moving, setMoving ] = useState< MoveSelection | null >( null );
	const [ attaching, setAttaching ] = useState< Group | null >( null );
	const [ removing, setRemoving ] = useState< {
		group: Group;
		skill: Skill;
	} | null >( null );
	const [ importing, setImporting ] = useState( false );
	const [ comparing, setComparing ] = useState( false );
	const [ mapping, setMapping ] = useState< Skill | null >( null );
	const send: SendCommand = ( name, fields ) =>
		operation.run(
			( signal ) => command( data.syllabus.id, name, fields, signal ),
			( result ) => {
				setData( result );
				setMessage( __( 'Syllabus saved.', 'ohmylms' ) );
			}
		);
	const groups = data.contents.flatMap( ( content ) =>
		content.groups.map( ( group ) => ( {
			value: String( group.id ),
			label: `${ content.name } › ${ group.name }`,
		} ) )
	);
	return (
		<section aria-label={ __( 'Syllabus editor', 'ohmylms' ) }>
			<p>
				<Link to="/content-hub/curriculum">
					{ __( 'Back to curriculum', 'ohmylms' ) }
				</Link>
			</p>
			<h2>{ data.syllabus.name }</h2>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ message && (
				<Notice status="success" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			{ data.course_error && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The syllabus was saved, but its course could not synchronize:',
						'ohmylms'
					) }{ ' ' }
					{ data.course_error }
				</Notice>
			) }
			<p>
				{ __(
					'Syllabus changes update the course draft. They do not publish it to learners.',
					'ohmylms'
				) }
			</p>
			{ data.course && (
				<p>
					<Link to={ `/content-hub/courses/${ data.course.id }` }>
						{ data.course.title } —{ ' ' }
						{ __( 'Open course structure', 'ohmylms' ) }
					</Link>
				</p>
			) }
			<Button
				disabled={ operation.busy }
				onClick={ () => void send( 'sync_course', {} ) }
			>
				{ data.course
					? __( 'Update course from syllabus', 'ohmylms' )
					: __( 'Create course from syllabus', 'ohmylms' ) }
			</Button>
			<Button
				disabled={ operation.busy }
				onClick={ () => setImporting( true ) }
			>
				{ __( 'Import CSV', 'ohmylms' ) }
			</Button>
			<Button
				disabled={ operation.busy }
				onClick={ () => setComparing( true ) }
			>
				{ __( 'Compare syllabuses', 'ohmylms' ) }
			</Button>
			<nav aria-label={ __( 'Syllabus layouts', 'ohmylms' ) }>
				<ul className="subsubsub">
					{ LAYOUTS.map( ( id, index ) => (
						<li key={ id }>
							<Link
								to={
									'outline' === id
										? `/content-hub/curriculum/syllabus/${ data.syllabus.id }`
										: `/content-hub/curriculum/syllabus/${ data.syllabus.id }?layout=${ id }`
								}
								className={
									id === layout ? 'current' : undefined
								}
								aria-current={
									id === layout ? 'page' : undefined
								}
							>
								{ layoutLabel( id ) }
							</Link>
							{ index < LAYOUTS.length - 1 ? ' | ' : null }
						</li>
					) ) }
				</ul>
			</nav>
			<br className="clear" />
			{ 'mindmap' === layout && <MindMap outline={ data } /> }
			{ 'spreadsheet' === layout && (
				<SpreadsheetView
					outline={ data }
					onSaved={ ( next ) => {
						setData( next );
						setMessage( '' );
					} }
				/>
			) }
			{ 'outline' === layout && (
				<GroupList
					data={ data }
					busy={ operation.busy }
					send={ send }
					edit={ ( value ) => {
						operation.clearError();
						setEditor( value );
					} }
					move={ ( value ) => {
						operation.clearError();
						setMoving( value );
					} }
					attach={ ( group ) => {
						operation.clearError();
						setAttaching( group );
					} }
					detach={ ( group, skill ) => {
						operation.clearError();
						setRemoving( { group, skill } );
					} }
					mapping={ setMapping }
				/>
			) }
			{ editor && (
				<FieldsDialog
					title={
						editor.skill
							? __( 'Edit shared library skill', 'ohmylms' )
							: __( 'Syllabus details', 'ohmylms' )
					}
					initial={ editor.initial }
					busy={ operation.busy }
					error={ operation.error }
					shared={
						!! editor.skill || editor.operation === 'create_skill'
					}
					onClose={ () => setEditor( null ) }
					onSave={ ( fields ) =>
						send( editor.operation, {
							...fields,
							...( editor.operation === 'add_group'
								? { item_id: editor.item }
								: {} ),
							...( editor.operation === 'edit_group' ||
							editor.operation === 'create_skill'
								? { group_id: editor.group!.id }
								: {} ),
							...( editor.skill
								? { term_id: editor.skill.term_id }
								: {} ),
						} )
					}
				/>
			) }
			{ moving && (
				<MoveDialog
					title={
						moving.skill
							? __( 'Move skill placement', 'ohmylms' )
							: __( 'Move skill group', 'ohmylms' )
					}
					options={
						moving.skill
							? groups
							: data.contents.map( ( content ) => ( {
									value: String( content.id ),
									label: content.name,
								} ) )
					}
					current={
						moving.skill ? moving.group.id : moving.group.item_id
					}
					busy={ operation.busy }
					error={ operation.error }
					onClose={ () => setMoving( null ) }
					onMove={ ( destination, position ) =>
						moving.skill
							? send( 'move_skill', {
									group_id: moving.group.id,
									term_id: moving.skill.term_id,
									destination,
									position,
								} )
							: send( 'move_group', {
									group_id: moving.group.id,
									item_id: destination,
									position,
								} )
					}
				/>
			) }
			{ attaching && (
				<LibraryPicker
					busy={ operation.busy }
					error={ operation.error }
					onClose={ () => setAttaching( null ) }
					onChoose={ ( termId ) =>
						send( 'attach_skill', {
							group_id: attaching.id,
							term_id: termId,
						} )
					}
				/>
			) }
			{ removing && (
				<Modal
					title={ __( 'Detach this skill placement?', 'ohmylms' ) }
					onRequestClose={ () => {
						if ( ! operation.busy ) {
							setRemoving( null );
						}
					} }
				>
					<p>
						{ __(
							'The skill, its questions, lessons and learner evidence stay in the library. Only this syllabus placement is removed.',
							'ohmylms'
						) }
					</p>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<Button
						disabled={ operation.busy }
						onClick={ () => setRemoving( null ) }
					>
						{ __( 'Keep skill', 'ohmylms' ) }
					</Button>
					<Button
						disabled={ operation.busy }
						onClick={ () =>
							void send( 'detach_skill', {
								group_id: removing.group.id,
								term_id: removing.skill.term_id,
							} ).then( ( saved ) => {
								if ( saved ) {
									setRemoving( null );
								}
							} )
						}
					>
						{ __( 'Detach placement', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ importing && (
				<CsvImportDialog
					id={ data.syllabus.id }
					onClose={ () => setImporting( false ) }
					onApplied={ ( result ) => {
						setData( result );
						setMessage(
							__( 'Reviewed CSV import applied.', 'ohmylms' )
						);
					} }
				/>
			) }
			{ comparing && (
				<ComparisonDialog
					id={ data.syllabus.id }
					onClose={ () => setComparing( false ) }
				/>
			) }
			{ mapping && (
				<MappingDialog
					skill={ mapping }
					onClose={ () => setMapping( null ) }
				/>
			) }
		</section>
	);
}
