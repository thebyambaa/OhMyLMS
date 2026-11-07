import { Button, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Badge } from '../../../ui/Badge';
import { useRequest } from '../../hooks/useRequest';
import { readItemLinks, type CurriculumItem } from './api';
import { LinksPane } from './LinksPane';
import { SkillsPane } from './SkillsPane';
import { ancestorsOf } from './WorkspaceTree';

export type Pane = 'skills' | 'courses' | 'content';

/**
 * Text long enough to crowd the panel starts folded to a few lines, with a way to read it all.
 *
 * @param props      Component props.
 * @param props.text The item's description.
 */
function Description( { text }: { text: string } ) {
	const [ open, setOpen ] = useState( false );
	const long = text.length > 220;
	return (
		<div className="oml-cw-description">
			<p className={ long && ! open ? 'is-clamped' : '' }>{ text }</p>
			{ long && (
				<Button variant="link" onClick={ () => setOpen( ! open ) }>
					{ open
						? __( 'Show less', 'ohmylms' )
						: __( 'Show more', 'ohmylms' ) }
				</Button>
			) }
		</div>
	);
}

/**
 * The syllabus an item belongs to: itself, or the nearest parent that is one.
 * @param items
 * @param item
 */
export function syllabusOf(
	items: readonly CurriculumItem[],
	item: CurriculumItem
): CurriculumItem | null {
	if ( item.is_syllabus ) {
		return item;
	}
	const chain = ancestorsOf( items, item.id );
	return [ ...chain ].reverse().find( ( row ) => row.is_syllabus ) ?? null;
}

/**
 * The open item: where it sits, what it is, what you can do with it, and three panes for what it holds:
 * skills, courses and other content.
 *
 * @param props            Component props.
 * @param props.item       The open item.
 * @param props.items      All curriculum items.
 * @param props.pane       The open pane.
 * @param props.onPane     Opens a pane.
 * @param props.onSelect   Opens another item (a parent, for example).
 * @param props.onEdit     Edit this item.
 * @param props.onAddChild Add an item inside this one.
 * @param props.onDelete   Delete this item.
 * @param props.onChanged  The item or its place in the tree changed.
 */
export function ItemDetail( {
	item,
	items,
	pane,
	onPane,
	onSelect,
	onEdit,
	onAddChild,
	onDelete,
	onChanged,
}: {
	item: CurriculumItem;
	items: readonly CurriculumItem[];
	pane: Pane;
	onPane: ( pane: Pane ) => void;
	onSelect: ( id: number ) => void;
	onEdit: () => void;
	onAddChild: () => void;
	onDelete: () => void;
	onChanged: () => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => readItemLinks( item.id, signal ),
		[ item.id ]
	);
	const links = useRequest( load, {
		key: String( item.id ),
		keepData: true,
	} );
	const syllabus = syllabusOf( items, item );
	const path = ancestorsOf( items, item.id );
	const data = links.data;
	const tabs: { id: Pane; label: string; count?: number }[] = [
		{ id: 'skills', label: __( 'Skills', 'ohmylms' ) },
		{
			id: 'courses',
			label: __( 'Courses', 'ohmylms' ),
			count: data?.course.length,
		},
		{
			id: 'content',
			label: __( 'Question banks and quizzes', 'ohmylms' ),
			count: data ? data.bank.length + data.quiz.length : undefined,
		},
	];
	return (
		<section className="oml-cw-detail" aria-label={ item.name }>
			{ path.length > 0 && (
				<nav
					className="oml-cw-crumbs"
					aria-label={ __( 'Where this is', 'ohmylms' ) }
				>
					{ path.map( ( row ) => (
						<span key={ row.id }>
							<Button
								variant="link"
								onClick={ () => onSelect( row.id ) }
							>
								{ row.name }
							</Button>
							{ ' › ' }
						</span>
					) ) }
				</nav>
			) }
			<header className="oml-cw-header">
				<div>
					<h2>{ item.name }</h2>
					<p className="oml-cw-meta">
						<Badge>{ item.item_type }</Badge>
						{ item.code && <code>{ item.code }</code> }
						{ item.version && <span>{ item.version }</span> }
						{ syllabus && syllabus.id !== item.id && (
							<span>
								{ sprintf(
									/* translators: %s: syllabus name. */
									__( 'In %s', 'ohmylms' ),
									syllabus.name
								) }
							</span>
						) }
					</p>
				</div>
				<div className="oml-cw-header-actions">
					<Button
						variant="secondary"
						size="small"
						onClick={ onAddChild }
					>
						{ __( 'Add inside', 'ohmylms' ) }
					</Button>
					<Button variant="secondary" size="small" onClick={ onEdit }>
						{ __( 'Edit', 'ohmylms' ) }
					</Button>
					<Button
						variant="tertiary"
						size="small"
						isDestructive
						onClick={ onDelete }
					>
						{ __( 'Delete…', 'ohmylms' ) }
					</Button>
				</div>
			</header>
			{ item.description && <Description text={ item.description } /> }
			<div
				className="oml-cw-tabs"
				role="tablist"
				aria-label={ __( 'What this holds', 'ohmylms' ) }
			>
				{ tabs.map( ( tab ) => (
					<button
						key={ tab.id }
						type="button"
						role="tab"
						id={ `oml-cw-tab-${ tab.id }` }
						aria-selected={ pane === tab.id }
						aria-controls="oml-cw-panel"
						className={ pane === tab.id ? 'is-active' : '' }
						onClick={ () => onPane( tab.id ) }
					>
						{ tab.label }
						{ undefined !== tab.count && ` (${ tab.count })` }
					</button>
				) ) }
			</div>
			<div
				id="oml-cw-panel"
				role="tabpanel"
				aria-labelledby={ `oml-cw-tab-${ pane }` }
				className="oml-cw-panel"
			>
				{ links.error && (
					<Notice status="error" isDismissible={ false }>
						{ links.error }
						<Button variant="link" onClick={ links.retry }>
							{ __( 'Retry', 'ohmylms' ) }
						</Button>
					</Notice>
				) }
				{ 'skills' === pane && (
					<SkillsPane
						key={ item.id }
						item={ item }
						syllabus={ syllabus }
						onChanged={ onChanged }
					/>
				) }
				{ 'courses' === pane && data && (
					<LinksPane
						item={ item.id }
						type="course"
						rows={ data.course }
						empty={ __(
							'No courses are placed here. A published course placed here appears in the student library.',
							'ohmylms'
						) }
						addLabel={ __(
							'Find a course to place here',
							'ohmylms'
						) }
						href={ ( id ) => `/courses/${ id }` }
						onLinks={ ( next ) => {
							links.replace( next );
							onChanged();
						} }
					/>
				) }
				{ 'content' === pane && data && (
					<>
						<h3>{ __( 'Question banks', 'ohmylms' ) }</h3>
						<LinksPane
							item={ item.id }
							type="bank"
							rows={ data.bank }
							empty={ __(
								'No question banks are linked.',
								'ohmylms'
							) }
							addLabel={ __( 'Find a question bank', 'ohmylms' ) }
							onLinks={ links.replace }
						/>
						<h3>{ __( 'Quizzes and exams', 'ohmylms' ) }</h3>
						<LinksPane
							item={ item.id }
							type="quiz"
							rows={ data.quiz }
							empty={ __(
								'No quizzes or exams are linked.',
								'ohmylms'
							) }
							addLabel={ __( 'Find a quiz or exam', 'ohmylms' ) }
							href={ ( id ) => `/quizzes/${ id }` }
							onLinks={ links.replace }
						/>
					</>
				) }
				{ 'skills' !== pane && ! data && links.loading && (
					<p role="status">{ __( 'Loading…', 'ohmylms' ) }</p>
				) }
			</div>
		</section>
	);
}
