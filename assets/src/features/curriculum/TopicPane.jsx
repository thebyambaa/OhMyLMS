import { createElement, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { AddContentMenu } from './AddContentMenu';
import { categoryAppearance } from './categoryAppearance.mjs';
import { SyllabusOverview } from './SyllabusOverview';
import { SkillCategories } from './SkillCategories';
import { useWorkspace } from './context';
import { siblingInfo } from './model.mjs';
import { DeleteTopicDialog } from './WorkspaceDialogs';
import {
	Breadcrumb,
	MESSAGES,
	QuickAdd,
	Row,
	RowMenu,
	SaveField,
	KindIcon,
} from './WorkspaceParts';
import {
	displayCounts,
	chaptersOf,
	outlineDropPosition,
	pathTo,
	topicLabel,
	topicsOf,
	validateTopic,
	visibleChildren,
} from './workspace.mjs';
const countsLine = ( counts ) =>
	[
		sprintf(
			/* translators: %d: number of curriculum folders. */ _n(
				'%d folder',
				'%d folders',
				counts.topics + counts.chapters,
				'ohmylms'
			),
			counts.topics + counts.chapters
		),
		sprintf(
			/* translators: %d: number of skills. */ _n(
				'%d skill',
				'%d skills',
				counts.skills,
				'ohmylms'
			),
			counts.skills
		),
	].join( ' · ' );

/**
 * The syllabus home or a topic with its skills and nested topics.
 * @param root0
 * @param root0.node
 */
export function TopicPane( { node } ) {
	const w = useWorkspace();
	const root = node.depth === 0;
	const item =
		w.items.find( ( entry ) => entry.id === node.id ) || node.content;
	const [ adding, setAdding ] = useState( '' );
	const [ confirm, setConfirm ] = useState( null );
	const [ dragged, setDragged ] = useState( '' );
	const [ dropTarget, setDropTarget ] = useState( null );
	const skills = visibleChildren( node, w.outline.settings ).filter(
		( child ) => child.kind === 'skill'
	);
	const topics = topicsOf( node );
	const groups = chaptersOf( node );
	const endDrag = () => {
		setDragged( '' );
		setDropTarget( null );
	};
	const skillReorder = ( child, position ) => ( {
		disabled: w.pending,
		target: dropTarget?.key === child.key,
		after: dropTarget?.after,
		onDragStart( event ) {
			if ( w.pending ) {
				return event.preventDefault();
			}
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData( 'text/plain', child.key );
			setDragged( child.key );
		},
		onDragEnd: endDrag,
		onDragOver( event ) {
			const box = event.currentTarget.getBoundingClientRect();
			const after = event.clientY > box.top + box.height / 2;
			if (
				w.pending ||
				outlineDropPosition( w.tree, dragged, child.key, after ) ===
					null
			) {
				setDropTarget( null );
				return;
			}
			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';
			setDropTarget( {
				key: child.key,
				after,
			} );
		},
		onDrop( event ) {
			event.preventDefault();
			const box = event.currentTarget.getBoundingClientRect();
			const after = event.clientY > box.top + box.height / 2;
			if (
				! w.pending &&
				outlineDropPosition( w.tree, dragged, child.key, after ) !==
					null
			) {
				w.actions.reorderOutline( dragged, child.key, after, {
					selectMoved: false,
				} );
			}
			endDrag();
		},
		onKeyDown( event ) {
			if (
				w.pending ||
				! event.altKey ||
				! [ 'ArrowUp', 'ArrowDown' ].includes( event.key )
			) {
				return;
			}
			event.preventDefault();
			const down = event.key === 'ArrowDown';
			const target = skills[ position + ( down ? 1 : -1 ) ];
			if ( target ) {
				w.actions.reorderOutline( child.key, target.key, down, {
					selectMoved: false,
				} );
			}
		},
	} );
	const own = siblingInfo( w.items, node.id );
	const check = ( key ) => ( text ) => {
		const code = validateTopic( {
			name: 'x',
			[ key ]: text,
		} )[ key ];
		return code ? MESSAGES[ code ]() : '';
	};
	const save =
		( key, trim = true ) =>
		( text ) =>
			w.actions.saveTopic( item, {
				[ key ]: trim ? text.trim() : text,
			} );
	const noun = root ? __( 'syllabus', 'ohmylms' ) : __( 'folder', 'ohmylms' );
	const topicControls = ( child ) => {
		const info = siblingInfo( w.items, child.id );
		const entry =
			w.items.find( ( row ) => row.id === child.id ) || child.content;
		return [
			{
				title: __( 'Open', 'ohmylms' ),
				onClick: () => w.select( child.key ),
			},
			{
				title: __( 'Move up', 'ohmylms' ),
				isDisabled: info.index <= 0,
				onClick: () => w.actions.stepTopic( entry, -1 ),
			},
			{
				title: __( 'Move down', 'ohmylms' ),
				isDisabled: info.index < 0 || info.index >= info.count - 1,
				onClick: () => w.actions.stepTopic( entry, 1 ),
			},
			{
				title: __( 'Delete folder…', 'ohmylms' ),
				onClick: () =>
					setConfirm( {
						kind: 'topic',
						item: entry,
						key: child.key,
					} ),
			},
		];
	};
	const headerMenu = root
		? null
		: [
				{
					title: __( 'Move up', 'ohmylms' ),
					isDisabled: own.index <= 0,
					onClick: () => w.actions.stepTopic( item, -1 ),
				},
				{
					title: __( 'Move down', 'ohmylms' ),
					isDisabled: own.index < 0 || own.index >= own.count - 1,
					onClick: () => w.actions.stepTopic( item, 1 ),
				},
				{
					title: __( 'Delete folder…', 'ohmylms' ),
					onClick: () =>
						setConfirm( {
							kind: 'topic',
							item,
							key: node.key,
							own: true,
						} ),
				},
			];
	return (
		<div className="ohmylms-ws-pane">
			<Breadcrumb
				nodes={ pathTo( w.tree.index, node.key ) }
				labelOf={ ( entry ) =>
					entry.depth === 0
						? entry.content.name
						: topicLabel( entry.content )
				}
				onSelect={ w.select }
			/>
			<header className="ohmylms-ws-pane-head">
				<div className="ohmylms-ws-pane-title">
					{ ! root && <KindIcon kind="topic" /> }
					<SaveField
						id={ `ohmylms-ws-name-${ node.id }` }
						label={
							root
								? __( 'Syllabus name', 'ohmylms' )
								: __( 'Folder name', 'ohmylms' )
						}
						hideLabel
						value={ item.name }
						placeholder={
							root
								? __( 'Enter syllabus name', 'ohmylms' )
								: __( 'Enter folder name', 'ohmylms' )
						}
						inputClassName={
							root
								? 'ohmylms-ws-title is-syllabus-name'
								: 'ohmylms-ws-title'
						}
						validate={ check( 'name' ) }
						onSave={ save( 'name' ) }
					/>
					{ headerMenu && (
						<RowMenu
							label={ __( 'Folder actions', 'ohmylms' ) }
							controls={ headerMenu }
							disabled={ w.pending }
						/>
					) }
				</div>
				<p className="ohmylms-ws-counts">
					{ countsLine( displayCounts( node, w.outline.settings ) ) }
				</p>
				{ root && (
					<div className="ohmylms-ws-meta">
						<SaveField
							id={ `ohmylms-ws-version-${ node.id }` }
							label={ __( 'Version', 'ohmylms' ) }
							help={ __(
								'For example the years a syllabus applies.',
								'ohmylms'
							) }
							value={ item.version }
							validate={ check( 'version' ) }
							onSave={ save( 'version' ) }
						/>
					</div>
				) }
				{ root && <SkillCategories /> }
				<SaveField
					id={ `ohmylms-ws-description-${ node.id }` }
					label={ __( 'Description', 'ohmylms' ) }
					hideLabel
					multiline
					value={ item.description }
					placeholder={ sprintf(
						/* translators: %s: curriculum item name. */ __(
							'Add %s description …',
							'ohmylms'
						),
						noun
					) }
					inputClassName="ohmylms-ws-description"
					validate={ check( 'description' ) }
					onSave={ save( 'description', false ) }
				/>
			</header>

			{ root && w.course && <SyllabusOverview /> }

			{ ! root && (
				<section
					className="ohmylms-ws-content"
					aria-label={ __( 'Content', 'ohmylms' ) }
				>
					<div className="ohmylms-ws-content-head">
						<h3>{ __( 'Content', 'ohmylms' ) }</h3>
						<AddContentMenu
							compact
							label={ sprintf(
								/* translators: %s: curriculum item name. */ __(
									'Add to this %s',
									'ohmylms'
								),
								noun
							) }
							disabled={ w.pending }
							items={ [
								{
									key: 'topic',
									title: __( 'Folder', 'ohmylms' ),
									info: __(
										'A folder containing skills and nested folders',
										'ohmylms'
									),
									onClick: () => setAdding( 'topic' ),
								},
								{
									key: 'skill',
									title: __( 'Skill', 'ohmylms' ),
									info: __(
										'A learning objective',
										'ohmylms'
									),
									onClick: () => setAdding( 'skill' ),
								},
							] }
						/>
					</div>
					{ adding === 'skill' && (
						<QuickAdd
							nameLabel={ __( 'Skill name', 'ohmylms' ) }
							codeLabel={ __( 'Code', 'ohmylms' ) }
							submitLabel={ __( 'Add skill', 'ohmylms' ) }
							pending={ w.pending }
							help={ __(
								'Press Enter to add it and start the next one.',
								'ohmylms'
							) }
							onAdd={ ( data ) =>
								w.actions.addSkillInTopic( node.id, data )
							}
							onCancel={ () => setAdding( '' ) }
						/>
					) }
					{ adding === 'topic' && (
						<QuickAdd
							nameLabel={ __( 'Folder name', 'ohmylms' ) }
							codeLabel={ __( 'Code', 'ohmylms' ) }
							submitLabel={ __( 'Add folder', 'ohmylms' ) }
							pending={ w.pending }
							help={ __(
								'Press Enter to add it and start the next one.',
								'ohmylms'
							) }
							onAdd={ ( data ) =>
								w.actions.addTopic( node.id, data, {
									open: false,
								} )
							}
							onCancel={ () => setAdding( '' ) }
						/>
					) }
					{ skills.length + topics.length + groups.length > 0 ? (
						<ul className="ohmylms-ws-rows">
							{ groups.map( ( child ) => (
								<Row
									key={ child.key }
									kind="chapter"
									title={
										child.group.code
											? `${ child.group.code } · ${ child.group.name }`
											: child.group.name
									}
									tag={ __( 'Folder', 'ohmylms' ) }
									meta={ sprintf(
										/* translators: %d: number of skills. */ _n(
											'%d skill',
											'%d skills',
											child.children.length,
											'ohmylms'
										),
										child.children.length
									) }
									onOpen={ () => w.select( child.key ) }
								/>
							) ) }
							{ topics.map( ( child ) => {
								const counts = displayCounts(
									child,
									w.outline.settings
								);
								return (
									<Row
										key={ child.key }
										kind="topic"
										icon={ child.content.icon }
										title={ topicLabel( child.content ) }
										tag={ __( 'Folder', 'ohmylms' ) }
										meta={ [
											sprintf(
												/* translators: %d: number of skills. */ _n(
													'%d skill',
													'%d skills',
													counts.skills,
													'ohmylms'
												),
												counts.skills
											),
											sprintf(
												/* translators: %d: number of curriculum folders. */ _n(
													'%d folder',
													'%d folders',
													counts.topics +
														counts.chapters,
													'ohmylms'
												),
												counts.topics + counts.chapters
											),
										].join( ' · ' ) }
										onOpen={ () => w.select( child.key ) }
										menu={
											<RowMenu
												label={ sprintf(
													/* translators: %s: curriculum item name. */ __(
														'Actions for %s',
														'ohmylms'
													),
													topicLabel( child.content )
												) }
												controls={ topicControls(
													child
												) }
												disabled={ w.pending }
											/>
										}
									/>
								);
							} ) }
							{ skills.map( ( child, position ) => {
								const appearance = categoryAppearance(
									child.skill,
									w.outline.settings,
									w.tree.index.get( child.parentKey )?.group
								);
								return (
									<Row
										key={ child.key }
										kind="skill"
										icon={ appearance.icon }
										iconColor={ appearance.color }
										title={ child.skill.name }
										tag={
											appearance.category ||
											child.skill.code ||
											undefined
										}
										reorder={ skillReorder(
											child,
											position
										) }
										onOpen={ () => w.select( child.key ) }
									/>
								);
							} ) }
						</ul>
					) : (
						adding === '' && (
							<p className="ohmylms-ext-muted ohmylms-ws-empty">
								{ root
									? __(
											'Nothing is in this syllabus yet. Add a folder or a skill, or import a CSV file.',
											'ohmylms'
										)
									: __(
											'Nothing is in this folder yet. Add a skill or a nested folder.',
											'ohmylms'
										) }
							</p>
						)
					) }
				</section>
			) }

			{ confirm?.kind === 'topic' && (
				<DeleteTopicDialog
					item={ confirm.item }
					pending={ w.pending }
					onClose={ () => setConfirm( null ) }
					onConfirm={ async ( options ) => {
						const fallback = confirm.own ? node.parentKey : '';
						if (
							await w.actions.deleteTopic( confirm.item, options )
						) {
							setConfirm( null );
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
