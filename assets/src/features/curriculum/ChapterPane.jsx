import { createElement, useState } from '@wordpress/element';
import { skillText } from './skillTranslations.mjs';
import { categoryAppearance } from './categoryAppearance.mjs';
import { __, _n, sprintf } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { AttachDialog } from '../content-hub/AttachDialog';
import { AttachmentItem } from '../content-hub/AttachmentItem';
import { syllabusReturnPath } from '../content-hub/editorNavigation.mjs';
import { attachmentsFor } from '../content-hub/catalogModel.mjs';
import { AddContentMenu } from './AddContentMenu';
import { useWorkspace } from './context';
import { ExistingSkill } from './SyllabusForms';
import { containerOptions, groupLabel, validateGroup } from './syllabus.mjs';
import { DeleteChapterDialog } from './WorkspaceDialogs';
import {
	Breadcrumb,
	MESSAGES,
	QuickAdd,
	Row,
	RowMenu,
	SaveField,
	Tag,
	KindIcon,
} from './WorkspaceParts';
import { pathTo, stepAmongSiblings, topicLabel } from './workspace.mjs';

/**
 * A chapter: a group of skills, and a chapter of the syllabus's course. Its name and notes, the skills in it
 * (added one after another from the keyboard), and the lessons, quizzes and assignments attached to the
 * whole chapter. What a single skill owns is shown when the skill is opened.
 * @param root0
 * @param root0.node
 */
export function ChapterPane( { node } ) {
	const w = useWorkspace();
	const group = node.group;
	const [ adding, setAdding ] = useState( '' );
	const [ attaching, setAttaching ] = useState( false );
	const [ confirming, setConfirming ] = useState( false );
	const courseCatalog = w.courseCatalog;
	const catalog = courseCatalog.catalog;
	const chapterId = group.chapter_id || 0;
	const ready = Boolean( catalog && chapterId );
	const attached = ready
		? attachmentsFor( catalog.attachments, {
				chapterId,
			} )
		: [];
	const skills = node.children;
	const holder = w.tree.index.get( node.parentKey );
	const first = stepAmongSiblings( w.tree, node, -1 ) === null;
	const last = stepAmongSiblings( w.tree, node, 1 ) === null;
	const check = ( text, key ) => {
		const draft =
			key === 'name'
				? {
						name: text,
						code: group.code,
					}
				: {
						name: group.name,
						code: text,
					};
		const code = validateGroup( draft )[ key ];
		return code ? MESSAGES[ code ]() : '';
	};
	const attachedTo = ( skill ) =>
		ready
			? attachmentsFor( catalog.attachments, {
					skillId: skill.term_id,
				} ).length
			: 0;
	// What the course requires of each skill (the syllabus decides the chapters and skills; the course, this).
	const entries = new Map(
		( catalog?.skills || [] ).map( ( entry ) => [ entry.term_id, entry ] )
	);
	const openAttach = () => setAttaching( true );
	const noCourse = ! ready;
	const skillControls = ( child, position ) => [
		{
			title: __( 'Open', 'ohmylms' ),
			onClick: () => w.select( child.key ),
		},
		{
			title: __( 'Move up', 'ohmylms' ),
			isDisabled: position <= 0,
			onClick: () => w.actions.stepSkill( group.id, child.id, -1 ),
		},
		{
			title: __( 'Move down', 'ohmylms' ),
			isDisabled: position >= skills.length - 1,
			onClick: () => w.actions.stepSkill( group.id, child.id, 1 ),
		},
		{
			title: __( 'Take out of this folder', 'ohmylms' ),
			onClick: () => w.actions.removeSkill( group.id, child.id ),
		},
	];
	return (
		<div className="ohmylms-ws-pane">
			<Breadcrumb
				nodes={ pathTo( w.tree.index, node.key ) }
				labelOf={ ( entry ) =>
					( () => {
						if ( entry.kind === 'group' ) {
							return groupLabel( entry.group );
						}
						if ( entry.depth === 0 ) {
							return entry.content.name;
						}
						return topicLabel( entry.content );
					} )()
				}
				onSelect={ w.select }
			/>
			<header className="ohmylms-ws-pane-head">
				<div className="ohmylms-ws-pane-title">
					<KindIcon kind="chapter" />
					<SaveField
						id={ `ohmylms-ws-chapter-name-${ group.id }` }
						label={ __( 'Folder name', 'ohmylms' ) }
						hideLabel
						value={ group.name }
						placeholder={ __( 'Enter folder name', 'ohmylms' ) }
						inputClassName="ohmylms-ws-title"
						validate={ ( text ) => check( text, 'name' ) }
						onSave={ ( text ) =>
							w.actions.saveChapter( group, {
								name: text.trim(),
							} )
						}
					/>
					<RowMenu
						label={ __( 'Folder actions', 'ohmylms' ) }
						disabled={ w.pending }
						controls={ [
							{
								title: __( 'Move up', 'ohmylms' ),
								isDisabled: first,
								onClick: () =>
									w.actions.stepChapter( group, -1 ),
							},
							{
								title: __( 'Move down', 'ohmylms' ),
								isDisabled: last,
								onClick: () =>
									w.actions.stepChapter( group, 1 ),
							},
							{
								title: __( 'Delete folder…', 'ohmylms' ),
								onClick: () => setConfirming( true ),
							},
						] }
					/>
				</div>
				<p className="ohmylms-ws-counts">
					{ sprintf(
						/* translators: %d: number of skills. */ _n(
							'%d skill',
							'%d skills',
							skills.length,
							'ohmylms'
						),
						skills.length
					) }
				</p>
				<div className="ohmylms-ws-meta">
					<SaveField
						id={ `ohmylms-ws-chapter-code-${ group.id }` }
						label={ __( 'Code', 'ohmylms' ) }
						help={ __( 'For example C1.1.', 'ohmylms' ) }
						value={ group.code }
						validate={ ( text ) => check( text, 'code' ) }
						onSave={ ( text ) =>
							w.actions.saveChapter( group, {
								code: text.trim(),
							} )
						}
					/>
					<SelectControl
						label={ __( 'Sits under', 'ohmylms' ) }
						value={ String( group.item_id ) }
						options={ containerOptions( w.outline ).map(
							( option ) => ( {
								value: String( option.id ),
								label: `${ '— '.repeat( option.depth ) }${ option.name }${ option.isRoot ? ` ${ __( '(the syllabus itself)', 'ohmylms' ) }` : '' }`,
							} )
						) }
						disabled={ w.pending }
						onChange={ ( value ) =>
							w.actions.moveChapter( group, Number( value ) )
						}
						__nextHasNoMarginBottom
					/>
				</div>
				<SaveField
					id={ `ohmylms-ws-chapter-notes-${ group.id }` }
					label={ __( 'Notes', 'ohmylms' ) }
					hideLabel
					multiline
					value={ group.description }
					placeholder={ __( 'Add folder description …', 'ohmylms' ) }
					inputClassName="ohmylms-ws-description"
					validate={ ( text ) =>
						text.length > 2000 ? MESSAGES[ 'too-long' ]() : ''
					}
					onSave={ ( text ) =>
						w.actions.saveChapter( group, {
							description: text,
						} )
					}
				/>
			</header>

			<section
				className="ohmylms-ws-content"
				aria-label={ __( 'Skills', 'ohmylms' ) }
			>
				<div className="ohmylms-ws-content-head">
					<h3>{ __( 'Skills', 'ohmylms' ) }</h3>
					<div className="ohmylms-ws-content-tools">
						<AddContentMenu
							label={ __( 'Add to this folder', 'ohmylms' ) }
							disabled={ w.pending }
							items={ [
								{
									key: 'skill',
									title: __( 'Skill', 'ohmylms' ),
									info: __(
										'A learning objective. It owns its lessons and questions.',
										'ohmylms'
									),
									onClick: () => setAdding( 'skill' ),
								},
								{
									key: 'library',
									title: __(
										'Skill from the library',
										'ohmylms'
									),
									info: __(
										'A skill that already exists, with its lessons and questions',
										'ohmylms'
									),
									onClick: () => setAdding( 'library' ),
								},
								{
									key: 'attach',
									title: __(
										'Lesson, quiz or assignment',
										'ohmylms'
									),
									info: noCourse
										? __(
												'Waiting for the course to load',
												'ohmylms'
											)
										: __(
												'For the whole folder, in the course',
												'ohmylms'
											),
									onClick: openAttach,
								},
							] }
						/>
					</div>
				</div>
				{ adding === 'skill' && (
					<QuickAdd
						nameLabel={ __( 'Skill', 'ohmylms' ) }
						codeLabel={ __( 'Code', 'ohmylms' ) }
						submitLabel={ __( 'Add skill', 'ohmylms' ) }
						pending={ w.pending }
						help={ __(
							'The learning objective. Press Enter to add it and start the next one.',
							'ohmylms'
						) }
						onAdd={ ( data ) =>
							w.actions.addSkill( group.id, data )
						}
						onCancel={ () => setAdding( '' ) }
					/>
				) }
				{ adding === 'library' && (
					<ExistingSkill
						defaultOpen
						outline={ w.outline }
						pending={ w.pending }
						onPlace={ ( termId ) =>
							w.actions.placeSkill( group.id, termId )
						}
					/>
				) }
				{ skills.length > 0 ? (
					<ul className="ohmylms-ws-rows">
						{ skills.map( ( child, position ) => {
							const owned = attachedTo( child.skill );
							const appearance = categoryAppearance(
								child.skill,
								w.outline.settings,
								group
							);
							return (
								<Row
									key={ child.key }
									kind="skill"
									icon={ appearance.icon }
									iconColor={ appearance.color }
									title={ skillText(
										child.skill,
										w.skillLanguage
									) }
									notes={
										skillText(
											child.skill,
											w.skillLanguage,
											'name'
										) !==
										skillText(
											child.skill,
											w.skillLanguage
										)
											? skillText(
													child.skill,
													w.skillLanguage,
													'name'
												)
											: undefined
									}
									tag={ child.skill.code || undefined }
									tagTone="code"
									status={
										<>
											{ child.skill.category && (
												<Tag>
													{ child.skill.category }
												</Tag>
											) }
											{ entries.get( child.id )
												?.required && (
												<Tag tone="ok">
													{ __(
														'Required',
														'ohmylms'
													) }
												</Tag>
											) }
										</>
									}
									meta={
										owned
											? sprintf(
													/* translators: %d: number of attached learning activities. */ _n(
														'%d attached',
														'%d attached',
														owned,
														'ohmylms'
													),
													owned
												)
											: undefined
									}
									onOpen={ () => w.select( child.key ) }
									menu={
										<RowMenu
											label={ sprintf(
												/* translators: %s: curriculum item name. */ __(
													'Actions for %s',
													'ohmylms'
												),
												child.skill.name
											) }
											controls={ skillControls(
												child,
												position
											) }
											disabled={ w.pending }
										/>
									}
								/>
							);
						} ) }
					</ul>
				) : (
					adding === '' && (
						<p className="ohmylms-ext-muted ohmylms-ws-empty">
							{ __(
								'No skills in this folder yet. Use Add Content to add the first one.',
								'ohmylms'
							) }
						</p>
					)
				) }
			</section>

			<section
				className="ohmylms-ws-content"
				aria-label={ __(
					'Lessons, quizzes and assignments of this folder',
					'ohmylms'
				) }
			>
				<div className="ohmylms-ws-content-head">
					<h3>
						{ __( 'Lessons, quizzes and assignments', 'ohmylms' ) }
					</h3>
				</div>
				{ courseCatalog.error && (
					<p role="alert">{ courseCatalog.error }</p>
				) }
				{ ! catalog && ! courseCatalog.error && (
					<p className="ohmylms-ext-muted">
						{ __( 'Loading the course…', 'ohmylms' ) }
					</p>
				) }
				{ catalog && ! chapterId && (
					<p className="ohmylms-ext-muted">
						{ __(
							'The course has no folder for this one yet. Use “Update course from syllabus” on the syllabus page.',
							'ohmylms'
						) }
					</p>
				) }
				{ ready && ! attached.length && (
					<p className="ohmylms-ext-muted">
						{ __(
							'Nothing is attached to the whole folder. Use Add Content to attach a lesson, a quiz or an assignment, for learners of the course.',
							'ohmylms'
						) }
					</p>
				) }
				{ attached.length > 0 && (
					<ul className="ohmylms-catalog-items is-chapter">
						{ attached.map( ( item ) => (
							<AttachmentItem
								key={ item.id }
								item={ item }
								returnTo={ syllabusReturnPath(
									w.syllabusId,
									node.key
								) }
								skills={ catalog.skills }
								actions={ {
									updateAttachment:
										courseCatalog.updateAttachment,
									removeAttachment:
										courseCatalog.removeAttachment,
								} }
							/>
						) ) }
					</ul>
				) }
			</section>

			{ attaching && ready && (
				<AttachDialog
					courseId={ catalog.course.id }
					chapters={ catalog.chapters }
					skills={ catalog.skills }
					scope={ {
						chapterId,
						skillIds: [],
					} }
					onClose={ () => setAttaching( false ) }
					onConfirm={ ( { picks, scope, options } ) => {
						setAttaching( false );
						courseCatalog.attach( picks, scope, options );
					} }
				/>
			) }
			{ confirming && (
				<DeleteChapterDialog
					group={ group }
					pending={ w.pending }
					onClose={ () => setConfirming( false ) }
					onConfirm={ async () => {
						const fallback = holder?.key;
						if ( await w.actions.deleteChapter( group ) ) {
							setConfirming( false );
							if ( fallback ) {
								w.select( fallback );
							}
						}
					} }
				/>
			) }
		</div>
	);
}
