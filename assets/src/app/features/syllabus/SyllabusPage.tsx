import { Button, Modal, Notice } from '@wordpress/components';
import { lazy, Suspense, useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { command, readSyllabus } from './api';
import { ComparisonDialog } from './ComparisonDialog';
import { CourseFromSyllabusDialog } from './CourseFromSyllabusDialog';
import { CsvImportDialog } from './CsvImportDialog';
import { FieldsDialog } from './FieldsDialog';
import { GroupList, type EditSelection, type MoveSelection } from './GroupList';
import { LibraryPicker } from './LibraryPicker';
import { MappingDialog } from './MappingDialog';
import { MoveDialog } from './MoveDialog';
import type { Group, SendCommand, Skill, SyllabusOutline } from './model';
import { SourceDialog } from './SourceDialog';

const PublicationPanel = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-course-structure" */ '../course-structure/public'
	).then( ( module ) => ( { default: module.PublicationPanel } ) )
);

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
	const [ deleting, setDeleting ] = useState< Group | null >( null );
	const [ editingSource, setEditingSource ] = useState( false );
	const [ generating, setGenerating ] = useState( false );
	const requirements = data.course
		? new Map(
				data.requirements.map( ( requirement ) => [
					requirement.term_id,
					requirement,
				] )
			)
		: null;
	const required = data.requirements.filter(
		( requirement ) => requirement.required
	).length;
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
				<Link to="/curriculum">
					{ __( 'Back to curriculum', 'ohmylms' ) }
				</Link>
			</p>
			<h2>{ data.syllabus.name }</h2>
			{ ( data.syllabus.code || data.syllabus.version ) && (
				<p>
					{ [ data.syllabus.code, data.syllabus.version ]
						.filter( Boolean )
						.join( ' · ' ) }
				</p>
			) }
			<Button
				variant="secondary"
				disabled={ operation.busy }
				onClick={ () => {
					operation.clearError();
					setEditingSource( true );
				} }
			>
				{ __( 'Source and profile', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				variant="primary"
				disabled={ operation.busy }
				onClick={ () => setGenerating( true ) }
			>
				{ __( 'New course from this syllabus…', 'ohmylms' ) }
			</Button>
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
			<h3>{ __( 'Mirrored syllabus course', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'The mirrored course follows this syllabus: its groups are units and its skills are outcomes. Syllabus changes update its draft; they do not publish it to learners.',
					'ohmylms'
				) }
			</p>
			{ data.course && (
				<>
					<p>
						<Link to={ `/content-hub/courses/${ data.course.id }` }>
							{ data.course.title } —{ ' ' }
							{ __( 'Open course structure', 'ohmylms' ) }
						</Link>
					</p>
					<p>
						{ sprintf(
							/* translators: 1: required skills, 2: skills in the course. */
							_n(
								'%1$d of %2$d skill is required for the course.',
								'%1$d of %2$d skills are required for the course.',
								data.requirements.length,
								'ohmylms'
							),
							required,
							data.requirements.length
						) }
					</p>
					{ ! required && (
						<Notice status="warning" isDismissible={ false }>
							{ 'traditional' === data.course.mode
								? __(
										'The course cannot be published without a completion requirement. Require a skill below or an activity in the course outline.',
										'ohmylms'
									)
								: __(
										'The course cannot be published until at least one skill is required.',
										'ohmylms'
									) }
						</Notice>
					) }
					<p>
						{ data.course.published
							? __(
									'A learning program of this course is published to learners.',
									'ohmylms'
								)
							: __(
									'The course is a draft: learners cannot enroll yet.',
									'ohmylms'
								) }
					</p>
					{ data.course_error && (
						<p>
							{ __(
								'Update the course from the syllabus before publishing it.',
								'ohmylms'
							) }
						</p>
					) }
					<Suspense
						fallback={
							<p role="status">{ __( 'Loading…', 'ohmylms' ) }</p>
						}
					>
						<PublicationPanel
							key={ data.course.id }
							course={ data.course.id }
							disabled={ operation.busy || !! data.course_error }
							onPublished={ () =>
								void operation.run(
									( signal ) =>
										readSyllabus(
											data.syllabus.id,
											signal
										),
									setData
								)
							}
						/>
					</Suspense>
				</>
			) }
			<Button
				disabled={ operation.busy }
				onClick={ () => void send( 'sync_course', {} ) }
			>
				{ data.course
					? __( 'Update course from syllabus', 'ohmylms' )
					: __( 'Create mirrored syllabus course', 'ohmylms' ) }
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
				remove={ ( group ) => {
					operation.clearError();
					setDeleting( group );
				} }
				requirements={ requirements }
			/>
			{ editingSource && (
				<SourceDialog
					data={ data }
					busy={ operation.busy }
					error={ operation.error }
					onClose={ () => setEditingSource( false ) }
					onSave={ ( fields ) => send( 'edit_source', fields ) }
				/>
			) }
			{ generating && (
				<CourseFromSyllabusDialog
					syllabusId={ data.syllabus.id }
					onClose={ () => setGenerating( false ) }
				/>
			) }
			{ deleting && (
				<Modal
					title={ __( 'Delete this skill group?', 'ohmylms' ) }
					onRequestClose={ () => {
						if ( ! operation.busy ) {
							setDeleting( null );
						}
					} }
				>
					<p>
						{ deleting.skills.length
							? sprintf(
									/* translators: %d: number of skills. */
									_n(
										'This group holds %d skill. The skill stays in the skill library with its questions, lessons and learner evidence; only its placement in this syllabus is removed.',
										'This group holds %d skills. The skills stay in the skill library with their questions, lessons and learner evidence; only their placements in this syllabus are removed.',
										deleting.skills.length,
										'ohmylms'
									),
									deleting.skills.length
								)
							: __( 'This group holds no skills.', 'ohmylms' ) }
					</p>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<Button
						disabled={ operation.busy }
						onClick={ () => setDeleting( null ) }
					>
						{ __( 'Keep group', 'ohmylms' ) }
					</Button>
					<Button
						isDestructive
						disabled={ operation.busy }
						onClick={ () =>
							void send( 'delete_group', {
								group_id: deleting.id,
								confirm: deleting.skills.length > 0,
							} ).then( ( saved ) => {
								if ( saved ) {
									setDeleting( null );
								}
							} )
						}
					>
						{ __( 'Delete group', 'ohmylms' ) }
					</Button>
				</Modal>
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
