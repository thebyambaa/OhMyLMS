import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';
import { useCurriculum } from './context';
import * as api from './api.mjs';
const GROUPS = [
	{
		type: 'course',
		title: () => __( 'Courses', 'ohmylms' ),
		find: () => __( 'Find a course to add', 'ohmylms' ),
	},
	{
		type: 'skill',
		title: () => __( 'Skills', 'ohmylms' ),
		find: () => __( 'Find a skill to add', 'ohmylms' ),
	},
	{
		type: 'bank',
		title: () => __( 'Question banks', 'ohmylms' ),
		find: () => __( 'Find a question bank to add', 'ohmylms' ),
	},
	{
		type: 'quiz',
		title: () => __( 'Quizzes and exams', 'ohmylms' ),
		find: () => __( 'Find a quiz or exam to add', 'ohmylms' ),
	},
];
const STATUS = {
	draft: () => __( 'Draft', 'ohmylms' ),
	pending: () => __( 'Pending', 'ohmylms' ),
	private: () => __( 'Private', 'ohmylms' ),
	future: () => __( 'Scheduled', 'ohmylms' ),
	missing: () => __( 'No longer available', 'ohmylms' ),
};

/**
 * Courses, skills, question banks and exams that sit under this curriculum item. Links are references only.
 * @param root0
 * @param root0.item
 * @param root0.detail
 * @param root0.onChange
 */
export function LinkedContent( { item, detail, onChange } ) {
	return (
		<section className="ohmylms-cur-links">
			<h3>{ __( 'Linked content', 'ohmylms' ) }</h3>
			<p className="ohmylms-ext-muted">
				{ __(
					'Links place existing content under this item. They do not copy content, change who can open it or set a learning mode.',
					'ohmylms'
				) }
			</p>
			{ GROUPS.map( ( group ) => (
				<LinkGroup
					key={ group.type }
					group={ group }
					item={ item }
					linked={ detail.links?.[ group.type ] || [] }
					onChange={ onChange }
				/>
			) ) }
		</section>
	);
}
function LinkGroup( { group, item, linked, onChange } ) {
	const c = useCurriculum();
	const [ open, setOpen ] = useState( false );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const linkedIds = new Set( linked.map( ( entry ) => entry.id ) );
	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		let current = true;
		const timer = setTimeout(
			() => {
				api.searchTargets( group.type, search )
					.then(
						( rows ) =>
							current &&
							( setResults( rows || [] ), setError( '' ) )
					)
					.catch(
						( cause ) =>
							current &&
							( setResults( [] ),
							setError(
								cause?.message ||
									__( 'Could not search.', 'ohmylms' )
							) )
					);
			},
			search ? 300 : 0
		);
		return () => {
			current = false;
			clearTimeout( timer );
		};
	}, [ open, search, group.type ] );
	async function change( work, success ) {
		const response = await c.actions.run( work, success );
		if ( response ) {
			onChange( ( previous ) => ( {
				...previous,
				links: response.links,
				skill_mappings: response.skill_mappings,
				dependents: previous.dependents,
			} ) );
		}
	}
	const available = results.filter( ( row ) => ! linkedIds.has( row.id ) );
	return (
		<details
			className="ohmylms-cur-link-group"
			open={ open }
			onToggle={ ( event ) => setOpen( event.currentTarget.open ) }
		>
			<summary>
				{ group.title() }{ ' ' }
				<span className="ohmylms-cur-badge">{ linked.length }</span>
			</summary>
			{ linked.length ? (
				<ul className="ohmylms-cur-link-list">
					{ linked.map( ( entry ) => (
						<li key={ entry.id }>
							<span>
								{ entry.title ||
									sprintf(
										/* translators: %d: item number. */ __(
											'Item %d',
											'ohmylms'
										),
										entry.id
									) }
								{ entry.code ? ` (${ entry.code })` : '' }
							</span>
							{ STATUS[ entry.status ] && (
								<span className="ohmylms-cur-badge">
									{ STATUS[ entry.status ]() }
								</span>
							) }
							<Button
								variant="link"
								isDestructive
								disabled={ c.pending }
								aria-label={ sprintf(
									/* translators: %1$s: linked content title; %2$s: curriculum item name. */ __(
										'Remove %1$s from %2$s',
										'ohmylms'
									),
									entry.title || entry.id,
									item.name
								) }
								onClick={ () =>
									change(
										() =>
											api.removeLink(
												item.id,
												group.type,
												entry.id
											),
										__( 'Link removed.', 'ohmylms' )
									)
								}
							>
								{ __( 'Remove', 'ohmylms' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) : (
				<p className="ohmylms-ext-muted">
					{ __( 'Nothing linked yet.', 'ohmylms' ) }
				</p>
			) }
			<TextControl
				label={ group.find() }
				type="search"
				value={ search }
				onChange={ setSearch }
				__nextHasNoMarginBottom
			/>
			{ error && <p role="alert">{ error }</p> }
			{ open && ! error && ! available.length && (
				<p className="ohmylms-ext-muted">
					{ __( 'No more matches.', 'ohmylms' ) }
				</p>
			) }
			<ul className="ohmylms-cur-link-list ohmylms-cur-link-results">
				{ available.map( ( row ) => (
					<li key={ row.id }>
						<span>
							{ row.title }
							{ row.code ? ` (${ row.code })` : '' }
						</span>
						{ STATUS[ row.status ] && (
							<span className="ohmylms-cur-badge">
								{ STATUS[ row.status ]() }
							</span>
						) }
						<Button
							variant="secondary"
							size="small"
							disabled={ c.pending }
							aria-label={ sprintf(
								/* translators: %1$s: linked content title; %2$s: curriculum item name. */ __(
									'Add %1$s to %2$s',
									'ohmylms'
								),
								row.title,
								item.name
							) }
							onClick={ () =>
								change(
									() =>
										api.addLink(
											item.id,
											group.type,
											row.id
										),
									__( 'Linked.', 'ohmylms' )
								)
							}
						>
							{ __( 'Add', 'ohmylms' ) }
						</Button>
					</li>
				) ) }
			</ul>
		</details>
	);
}
