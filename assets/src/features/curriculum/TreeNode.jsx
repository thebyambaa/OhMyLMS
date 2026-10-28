/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useCurriculum } from './context';
import { AddItemRow } from './AddItemRow';
import { ItemPanel } from './ItemPanel';
import { isOpen, siblingInfo, syllabusBreadcrumbs } from './model.mjs';
import { workspacePath } from './workspace.mjs';
function counts( item ) {
	const parts = [];
	const { course = 0, skill = 0, bank = 0, quiz = 0 } = item.links || {};
	if ( course ) {
		parts.push(
			sprintf(
				/* translators: %d: item count. */ _n(
					'%d course',
					'%d courses',
					course,
					'ohmylms'
				),
				course
			)
		);
	}
	if ( skill ) {
		parts.push(
			sprintf(
				/* translators: %d: item count. */ _n(
					'%d skill',
					'%d skills',
					skill,
					'ohmylms'
				),
				skill
			)
		);
	}
	if ( bank ) {
		parts.push(
			sprintf(
				/* translators: %d: item count. */ _n(
					'%d question bank',
					'%d question banks',
					bank,
					'ohmylms'
				),
				bank
			)
		);
	}
	if ( quiz ) {
		parts.push(
			sprintf(
				/* translators: %d: item count. */ _n(
					'%d quiz or exam',
					'%d quizzes or exams',
					quiz,
					'ohmylms'
				),
				quiz
			)
		);
	}
	// A syllabus also counts its skill groups and the skills in them.
	const { groups = 0, skills: placed = 0 } = item.syllabus || {};
	if ( groups ) {
		parts.push(
			sprintf(
				/* translators: %d: item count. */ _n(
					'%d folder',
					'%d folders',
					groups,
					'ohmylms'
				),
				groups
			)
		);
	}
	if ( placed ) {
		parts.push(
			sprintf(
				/* translators: %d: item count. */ _n(
					'%d skill in groups',
					'%d skills in groups',
					placed,
					'ohmylms'
				),
				placed
			)
		);
	}
	return parts.join( ' · ' );
}

/**
 * One accordion row: disclosure toggle, summary, actions, optional edit panel and nested children.
 * @param root0
 * @param root0.node
 */
export function TreeNode( { node } ) {
	const c = useCurriculum();
	const { item } = node;
	const open = isOpen( c.view, c.search, item.id );
	const editing = c.openId === item.id;
	const { index, count } = siblingInfo( c.items, item.id );
	const childrenId = `ohmylms-cur-children-${ item.id }`;
	const panelId = `ohmylms-cur-panel-${ item.id }`;
	const summary = counts( item );
	const hasChildren = item.child_count > 0;
	const adding = c.adding === item.id;
	const shortcuts = open ? [] : syllabusBreadcrumbs( c.items, node.children );
	return (
		<li className="ohmylms-cur-node">
			<div
				className={ `ohmylms-cur-row${ editing ? ' is-editing' : '' }${ c.search.matches.has( item.id ) ? ' is-match' : '' }` }
				style={ {
					'--ohmylms-cur-depth': node.depth,
				} }
			>
				{ c.roadmapSelection && item.is_syllabus && (
					<input
						type="checkbox"
						aria-label={ sprintf(
							/* translators: %s: item label. */ __(
								'Include %s in Roadmap',
								'ohmylms'
							),
							item.name
						) }
						checked={ c.roadmapSelection.ids.includes( item.id ) }
						disabled={
							c.roadmapSelection.busy ||
							( c.roadmapSelection.ids.length >= 10 &&
								! c.roadmapSelection.ids.includes( item.id ) )
						}
						onChange={ ( event ) =>
							c.roadmapSelection.toggle(
								item.id,
								event.target.checked
							)
						}
					/>
				) }
				{ hasChildren ? (
					<button
						type="button"
						id={ `ohmylms-cur-toggle-${ item.id }` }
						className="ohmylms-cur-toggle"
						aria-expanded={ open }
						aria-controls={ open ? childrenId : undefined }
						aria-label={
							open
								? sprintf(
										/* translators: %s: item label. */ __(
											'Collapse %s',
											'ohmylms'
										),
										item.name
									)
								: sprintf(
										/* translators: %s: item label. */ __(
											'Expand %s',
											'ohmylms'
										),
										item.name
									)
						}
						onClick={ () => c.actions.toggle( item.id ) }
					>
						<span aria-hidden="true">{ open ? '▾' : '▸' }</span>
					</button>
				) : (
					<span
						className="ohmylms-cur-toggle-spacer"
						aria-hidden="true"
					/>
				) }
				<span className="screen-reader-text">
					{ sprintf(
						/* translators: %d: item count. */ __(
							'Level %d',
							'ohmylms'
						),
						node.depth + 1
					) }
				</span>
				{ item.is_syllabus ? (
					// A syllabus is edited in its own full-page workspace; its name opens it.
					<a
						className="ohmylms-cur-name ohmylms-cur-name-link"
						href={ `#${ workspacePath( item.id ) }` }
						title={ __( 'Open the syllabus editor', 'ohmylms' ) }
					>
						{ item.name }
					</a>
				) : (
					<span className="ohmylms-cur-name">{ item.name }</span>
				) }
				{ ( item.code || item.version ) && (
					<span className="ohmylms-cur-meta">
						{ [ item.code, item.version ]
							.filter( Boolean )
							.join( ' · ' ) }
					</span>
				) }
				{ summary && (
					<span className="ohmylms-cur-counts">{ summary }</span>
				) }
				<span className="ohmylms-cur-actions">
					<Button
						variant="secondary"
						size="small"
						aria-expanded={ editing }
						aria-controls={ editing ? panelId : undefined }
						aria-label={
							editing
								? sprintf(
										/* translators: %s: item label. */ __(
											'Close editor for %s',
											'ohmylms'
										),
										item.name
									)
								: sprintf(
										/* translators: %s: item label. */ __(
											'Edit %s',
											'ohmylms'
										),
										item.name
									)
						}
						onClick={ () =>
							item.item_type === 'roadmap'
								? ( window.location.hash = workspacePath(
										item.id
									) )
								: c.actions.toggleEditor( item.id )
						}
					>
						{ editing
							? __( 'Close', 'ohmylms' )
							: __( 'Edit', 'ohmylms' ) }
					</Button>
					{ /* A syllabus holds skill groups and skills; its contents are added in its Syllabus content section. */ }
					{ ! item.is_syllabus && (
						<Button
							variant="secondary"
							size="small"
							aria-label={ sprintf(
								/* translators: %s: item label. */ __(
									'Add child to %s',
									'ohmylms'
								),
								item.name
							) }
							onClick={ () => c.actions.startAdd( item.id ) }
						>
							{ __( 'Add child', 'ohmylms' ) }
						</Button>
					) }
					<Button
						id={ `ohmylms-cur-up-${ item.id }` }
						variant="secondary"
						size="small"
						disabled={ c.pending || c.search.active || index <= 0 }
						aria-label={ sprintf(
							/* translators: %s: item label. */ __(
								'Move %s up',
								'ohmylms'
							),
							item.name
						) }
						onClick={ () => c.actions.step( item, -1 ) }
					>
						<span aria-hidden="true">↑</span>
					</Button>
					<Button
						id={ `ohmylms-cur-down-${ item.id }` }
						variant="secondary"
						size="small"
						disabled={
							c.pending ||
							c.search.active ||
							index < 0 ||
							index >= count - 1
						}
						aria-label={ sprintf(
							/* translators: %s: item label. */ __(
								'Move %s down',
								'ohmylms'
							),
							item.name
						) }
						onClick={ () => c.actions.step( item, 1 ) }
					>
						<span aria-hidden="true">↓</span>
					</Button>
				</span>
			</div>
			{ shortcuts.length > 0 && (
				<ul
					className="ohmylms-cur-syllabus-shortcuts"
					aria-label={ __( 'Syllabuses in this branch', 'ohmylms' ) }
				>
					{ shortcuts.map( ( shortcut ) => (
						<li key={ shortcut.item.id }>
							<span className="ohmylms-cur-syllabus-path">
								{ shortcut.ancestors.map( ( ancestor ) => (
									<span key={ ancestor.id }>
										<Button
											variant="link"
											onClick={ () =>
												c.actions.reveal( ancestor.id )
											}
										>
											{ ancestor.name }
										</Button>
										<span aria-hidden="true">
											{ ' > ' }
										</span>
									</span>
								) ) }
								<a
									href={ `#${ workspacePath( shortcut.item.id ) }` }
								>
									{ shortcut.item.name }
								</a>
							</span>
						</li>
					) ) }
				</ul>
			) }
			{ editing && (
				<ItemPanel key={ item.id } id={ panelId } item={ item } />
			) }
			{ hasChildren && open && node.children.length > 0 && (
				<ul id={ childrenId } className="ohmylms-cur-children">
					{ node.children.map( ( child ) => (
						<TreeNode key={ child.item.id } node={ child } />
					) ) }
				</ul>
			) }
			{ adding && (
				<div
					className="ohmylms-cur-add-wrap"
					style={ {
						'--ohmylms-cur-depth': node.depth + 1,
					} }
				>
					<AddItemRow parentId={ item.id } parentName={ item.name } />
				</div>
			) }
		</li>
	);
}
