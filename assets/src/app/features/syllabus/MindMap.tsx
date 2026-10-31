import { Button, TextControl } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import type { SyllabusOutline } from './model';
import {
	branchKeys,
	countNodes,
	edgePath,
	layoutMap,
	MAP_CARD,
	syllabusTree,
	type MapKind,
	type MapNode,
} from './mind-map';
import './views.css';

const SHORT = 30;

function kindName( kind: MapKind ) {
	return {
		syllabus: __( 'Syllabus', 'ohmylms' ),
		item: __( 'Content', 'ohmylms' ),
		group: __( 'Skill group', 'ohmylms' ),
		skill: __( 'Skill', 'ohmylms' ),
	}[ kind ];
}

function title( node: MapNode ) {
	return node.code ? `${ node.code } ${ node.label }` : node.label;
}

function skillRoute( node: MapNode ) {
	return `/content-hub/skills/mappings?skill=${ node.id }`;
}

/**
 * Read-only mind map of a syllabus: an SVG tree with collapse and expand, and the same tree as a nested
 * list (the text alternative, with the same controls and links). Nothing here writes.
 *
 * @param props         Component properties.
 * @param props.outline Syllabus outline.
 */
export function MindMap( { outline }: { outline: SyllabusOutline } ) {
	const root = useMemo( () => syllabusTree( outline ), [ outline ] );
	const counts = useMemo( () => countNodes( root ), [ root ] );
	const [ collapsed, setCollapsed ] = useState< Set< string > >(
		() => new Set( root.children.flatMap( branchKeys ) )
	);
	const [ query, setQuery ] = useState( '' );
	const [ textVersion, setTextVersion ] = useState( true );
	const layout = useMemo(
		() => layoutMap( root, collapsed, query ),
		[ root, collapsed, query ]
	);
	const toggle = ( key: string ) =>
		setCollapsed( ( current ) => {
			const next = new Set( current );
			if ( next.has( key ) ) {
				next.delete( key );
			} else {
				next.add( key );
			}
			return next;
		} );
	const summary = sprintf(
		/* translators: 1: syllabus, 2: content items, 3: skill groups, 4: skills. */
		__(
			'Mind map of %1$s: %2$d content items, %3$d skill groups and %4$d skills. The same tree follows as a list.',
			'ohmylms'
		),
		outline.syllabus.name,
		counts.item,
		counts.group,
		counts.skill
	);
	return (
		<section
			className="ohmylms-mindmap"
			aria-label={ __( 'Mind map', 'ohmylms' ) }
		>
			<div className="ohmylms-mindmap__toolbar">
				<TextControl
					label={ __( 'Find in the map', 'ohmylms' ) }
					value={ query }
					maxLength={ 100 }
					onChange={ setQuery }
				/>
				<Button
					variant="secondary"
					onClick={ () => setCollapsed( new Set() ) }
				>
					{ __( 'Expand all', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () =>
						setCollapsed(
							new Set( root.children.flatMap( branchKeys ) )
						)
					}
				>
					{ __( 'Collapse all', 'ohmylms' ) }
				</Button>
				<Button
					variant="tertiary"
					aria-expanded={ textVersion }
					onClick={ () => setTextVersion( ( shown ) => ! shown ) }
				>
					{ textVersion
						? __( 'Hide text version', 'ohmylms' )
						: __( 'Show text version', 'ohmylms' ) }
				</Button>
			</div>
			{ query.trim() && ! layout.nodes.length && (
				<p role="status">{ __( 'Nothing matches.', 'ohmylms' ) }</p>
			) }
			<div className="ohmylms-mindmap__canvas">
				<svg
					role="img"
					aria-label={ summary }
					width={ layout.width }
					height={ layout.height }
					viewBox={ `0 0 ${ layout.width } ${ layout.height }` }
				>
					{ layout.edges.map( ( edge ) => (
						<path
							key={ `${ edge.from.node.key }-${ edge.to.node.key }` }
							className="ohmylms-mindmap__edge"
							d={ edgePath( edge.from, edge.to ) }
						/>
					) ) }
					{ layout.nodes.map( ( placed ) => {
						const text = title( placed.node );
						const short =
							text.length > SHORT
								? `${ text.slice( 0, SHORT - 1 ) }…`
								: text;
						const branch = placed.node.children.length > 0;
						return (
							<g
								key={ placed.node.key }
								className={ `ohmylms-mindmap__node is-${ placed.node.kind }${
									query.trim() &&
									title( placed.node )
										.toLocaleLowerCase()
										.includes(
											query.trim().toLocaleLowerCase()
										)
										? ' is-match'
										: ''
								}` }
								transform={ `translate(${ placed.x } ${ placed.y })` }
								onClick={
									branch
										? () => toggle( placed.node.key )
										: undefined
								}
							>
								<title>{ `${ kindName(
									placed.node.kind
								) }: ${ text }` }</title>
								<rect
									width={ MAP_CARD.width }
									height={ MAP_CARD.height }
									rx={ 6 }
								/>
								<text x={ 10 } y={ 18 }>
									{ short }
								</text>
								<text
									className="ohmylms-mindmap__meta"
									x={ 10 }
									y={ 35 }
								>
									{ branch
										? sprintf(
												/* translators: 1: kind, 2: number of children, 3: + or −. */
												__(
													'%1$s · %2$d %3$s',
													'ohmylms'
												),
												kindName( placed.node.kind ),
												placed.node.children.length,
												placed.folded ? '+' : '−'
											)
										: kindName( placed.node.kind ) }
								</text>
							</g>
						);
					} ) }
				</svg>
			</div>
			{ textVersion && (
				<div className="ohmylms-mindmap__list">
					<h3>{ __( 'Text version', 'ohmylms' ) }</h3>
					<p>{ summary }</p>
					<ul>
						<TreeItem
							node={ root }
							collapsed={ collapsed }
							matches={ query.trim() ? layout.matches : null }
							onToggle={ toggle }
						/>
					</ul>
				</div>
			) }
		</section>
	);
}

function TreeItem( {
	node,
	collapsed,
	matches,
	onToggle,
}: {
	node: MapNode;
	collapsed: ReadonlySet< string >;
	matches: ReadonlySet< string > | null;
	onToggle: ( key: string ) => void;
} ) {
	if ( matches && ! matches.has( node.key ) ) {
		return null;
	}
	const open = !! matches || ! collapsed.has( node.key );
	const branch = node.children.length > 0;
	return (
		<li>
			{ branch && (
				<Button
					variant="link"
					aria-expanded={ open }
					disabled={ !! matches }
					onClick={ () => onToggle( node.key ) }
					aria-label={ sprintf(
						/* translators: 1: expand or collapse, 2: node name. */
						__( '%1$s %2$s', 'ohmylms' ),
						open
							? __( 'Collapse', 'ohmylms' )
							: __( 'Expand', 'ohmylms' ),
						title( node )
					) }
				>
					{ open ? '−' : '+' }
				</Button>
			) }{ ' ' }
			<span className="screen-reader-text">
				{ kindName( node.kind ) }:{ ' ' }
			</span>
			{ 'skill' === node.kind ? (
				<Link to={ skillRoute( node ) }>{ title( node ) }</Link>
			) : (
				<strong>{ title( node ) }</strong>
			) }
			{ branch && open && (
				<ul>
					{ node.children.map( ( child ) => (
						<TreeItem
							key={ child.key }
							node={ child }
							collapsed={ collapsed }
							matches={ matches }
							onToggle={ onToggle }
						/>
					) ) }
				</ul>
			) }
		</li>
	);
}
