import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	readSyllabus,
	syllabusCommand,
	type Content,
	type SyllabusOutline,
} from '../syllabus/public';
import { saveItem, type CurriculumItem } from './api';

type Adding =
	| { kind: 'item-skill' }
	| { kind: 'group'; item: number }
	| { kind: 'skill'; group: number; groupName: string };

/**
 * The contents of the open item inside its syllabus: the item itself and everything under it.
 *
 * @param outline The syllabus outline.
 * @param itemId  The open item.
 * @return The outline's contents that sit at or below the item, in tree order.
 */
export function contentsUnder(
	outline: Pick< SyllabusOutline, 'contents' >,
	itemId: number
): Content[] {
	const inside = new Set< number >( [ itemId ] );
	const rows: Content[] = [];
	for ( const content of outline.contents ) {
		if ( inside.has( content.id ) || inside.has( content.parent_id ) ) {
			inside.add( content.id );
			rows.push( content );
		}
	}
	return rows;
}

function NameDialog( {
	title,
	submit,
	onClose,
	onSubmit,
	busy,
	error,
}: {
	title: string;
	submit: string;
	onClose: () => void;
	onSubmit: ( name: string, code: string ) => void;
	busy: boolean;
	error: string;
} ) {
	const [ name, setName ] = useState( '' );
	const [ code, setCode ] = useState( '' );
	return (
		<Modal title={ title } onRequestClose={ onClose }>
			<form
				className="oml-cw-form"
				onSubmit={ ( event ) => {
					event.preventDefault();
					onSubmit( name.trim(), code.trim() );
				} }
			>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<TextControl
					label={ __( 'Name', 'ohmylms' ) }
					value={ name }
					required
					onChange={ setName }
				/>
				<TextControl
					label={ __( 'Code (optional)', 'ohmylms' ) }
					value={ code }
					onChange={ setCode }
				/>
				<div className="oml-cw-actions">
					<Button
						variant="primary"
						type="submit"
						isBusy={ busy }
						disabled={ busy || ! name.trim() }
					>
						{ submit }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}

/**
 * Skill groups and skills of the open item, kept in its syllabus. Add a group or a skill and take a skill
 * out of a group here; the syllabus editor has the rest (import, requirements, mapping).
 *
 * @param props           Component props.
 * @param props.item      The open item.
 * @param props.syllabus  The syllabus the item belongs to, or null when it is in none.
 * @param props.onChanged Called after the item became a syllabus.
 */
export function SkillsPane( {
	item,
	syllabus,
	onChanged,
}: {
	item: CurriculumItem;
	syllabus: CurriculumItem | null;
	onChanged: () => void;
} ) {
	const root = syllabus?.id ?? 0;
	const load = useCallback(
		( signal: AbortSignal ) =>
			root
				? readSyllabus( root, signal )
				: Promise.resolve( null as SyllabusOutline | null ),
		[ root ]
	);
	const request = useRequest( load, { key: String( root ) } );
	const operation = useOperation();
	const [ adding, setAdding ] = useState< Adding | null >( null );
	/**
	 * Add a skill straight to the open item. The item becomes a skill holder and gets a plain
	 * "Skills" group the first time, so nobody has to create either by hand.
	 *
	 * @param name   Skill name.
	 * @param code   Optional skill code.
	 * @param signal Abort signal.
	 * @return The updated outline.
	 */
	async function addSkillToItem(
		name: string,
		code: string,
		signal: AbortSignal
	): Promise< SyllabusOutline > {
		let rootId = root;
		if ( ! rootId ) {
			await saveItem(
				{
					name: item.name,
					item_type: item.item_type,
					description: item.description,
					code: item.code,
					version: item.version,
					is_syllabus: true,
					icon: item.icon,
				},
				item,
				item.parent_id,
				signal
			);
			rootId = item.id;
		}
		const own = ( outline: SyllabusOutline ) =>
			outline.contents.find( ( row ) => row.id === item.id )?.groups[ 0 ];
		let outline = await readSyllabus( rootId, signal );
		let group = own( outline );
		if ( ! group ) {
			outline = await syllabusCommand(
				rootId,
				'add_group',
				{ item_id: item.id, name: __( 'Skills', 'ohmylms' ), code: '' },
				signal
			);
			group = own( outline );
		}
		if ( ! group ) {
			throw new Error( __( 'The skill could not be added.', 'ohmylms' ) );
		}
		return syllabusCommand(
			rootId,
			'create_skill',
			{ group_id: group.id, name, code },
			signal
		);
	}
	const addToItem = (
		<>
			<Button
				variant="primary"
				size="small"
				onClick={ () => setAdding( { kind: 'item-skill' } ) }
			>
				{ __( 'Add skill', 'ohmylms' ) }
			</Button>
			{ adding && 'item-skill' === adding.kind && (
				<NameDialog
					title={ __( 'Add skill', 'ohmylms' ) }
					submit={ __( 'Add skill', 'ohmylms' ) }
					busy={ operation.busy }
					error={ operation.error }
					onClose={ () => setAdding( null ) }
					onSubmit={ ( name, code ) =>
						void operation.run(
							( signal ) => addSkillToItem( name, code, signal ),
							( result ) => {
								setAdding( null );
								if ( root ) {
									request.replace( result );
								} else {
									onChanged();
								}
							}
						)
					}
				/>
			) }
		</>
	);
	if ( ! syllabus ) {
		return (
			<div className="oml-cw-hint">
				<p>{ __( 'No skills here yet.', 'ohmylms' ) }</p>
				{ addToItem }
			</div>
		);
	}
	if ( request.loading && ! request.data ) {
		return <p role="status">{ __( 'Loading skills…', 'ohmylms' ) }</p>;
	}
	if ( request.error || ! request.data ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ request.error ||
					__( 'Skills could not be loaded.', 'ohmylms' ) }
				<Button variant="link" onClick={ request.retry }>
					{ __( 'Retry', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	const outline = request.data;
	const rows = contentsUnder( outline, item.id );
	const total = rows.reduce(
		( sum, row ) =>
			sum +
			row.groups.reduce(
				( inner, group ) => inner + group.skills.length,
				0
			),
		0
	);
	function run(
		operationName: 'add_group' | 'create_skill' | 'detach_skill',
		fields: Record< string, unknown >,
		after?: () => void
	) {
		void operation.run(
			( signal ) =>
				syllabusCommand( root, operationName, fields, signal ),
			( result ) => {
				request.replace( result );
				after?.();
			}
		);
	}
	return (
		<div className="oml-cw-skills">
			<div className="oml-cw-pane-head">
				<p>
					{ sprintf(
						/* translators: %d: number of skills. */
						_n(
							'%d skill under this item.',
							'%d skills under this item.',
							total,
							'ohmylms'
						),
						total
					) }
				</p>
				<span className="oml-cw-pane-actions">
					{ addToItem }
					<Button
						size="small"
						variant="tertiary"
						onClick={ () =>
							setAdding( { kind: 'group', item: item.id } )
						}
					>
						{ __( 'Add group', 'ohmylms' ) }
					</Button>
					<Link
						className="components-button is-tertiary is-small"
						to={ `/content-hub/curriculum/syllabus/${ root }` }
					>
						{ __( 'Import and more', 'ohmylms' ) }
					</Link>
				</span>
			</div>
			{ operation.error && ! adding && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ 0 === total &&
				0 === rows.reduce( ( n, row ) => n + row.groups.length, 0 ) && (
					<p className="oml-cw-empty">
						{ __(
							'No skill groups here yet. Add a group, then add skills to it.',
							'ohmylms'
						) }
					</p>
				) }
			{ rows.map( ( row ) => (
				<section key={ row.id } className="oml-cw-content">
					{ row.id !== item.id && (
						<h3>
							{ row.code ? `${ row.code } · ` : '' }
							{ row.name }
						</h3>
					) }
					{ row.groups.map( ( group ) => (
						<div key={ group.id } className="oml-cw-group">
							{ ( row.groups.length > 1 ||
								row.id !== item.id ) && (
								<div className="oml-cw-group-head">
									<h4>
										{ group.code
											? `${ group.code } · `
											: '' }
										{ group.name }
									</h4>
									<Button
										size="small"
										variant="tertiary"
										onClick={ () =>
											setAdding( {
												kind: 'skill',
												group: group.id,
												groupName: group.name,
											} )
										}
									>
										{ __( 'Add skill', 'ohmylms' ) }
									</Button>
								</div>
							) }
							{ group.skills.length ? (
								<ul className="oml-cw-skilllist">
									{ group.skills.map( ( skill ) => (
										<li key={ skill.term_id }>
											<span>
												{ skill.code && (
													<code>{ skill.code }</code>
												) }{ ' ' }
												{ skill.name }
											</span>
											<Button
												size="small"
												variant="tertiary"
												isDestructive
												disabled={ operation.busy }
												label={ sprintf(
													/* translators: %s: skill name. */
													__(
														'Take %s out of this group',
														'ohmylms'
													),
													skill.name
												) }
												onClick={ () =>
													run( 'detach_skill', {
														group_id: group.id,
														term_id: skill.term_id,
													} )
												}
											>
												{ __( 'Remove', 'ohmylms' ) }
											</Button>
										</li>
									) ) }
								</ul>
							) : (
								<p className="oml-cw-empty">
									{ __(
										'No skills in this group.',
										'ohmylms'
									) }
								</p>
							) }
						</div>
					) ) }
					{ row.id !== item.id && 0 === row.groups.length && (
						<Button
							size="small"
							variant="tertiary"
							onClick={ () =>
								setAdding( { kind: 'group', item: row.id } )
							}
						>
							{ __( 'Add skill group', 'ohmylms' ) }
						</Button>
					) }
				</section>
			) ) }
			{ adding && 'item-skill' !== adding.kind && (
				<NameDialog
					title={
						'group' === adding.kind
							? __( 'Add skill group', 'ohmylms' )
							: sprintf(
									/* translators: %s: group name. */
									__( 'Add a skill to %s', 'ohmylms' ),
									adding.groupName
								)
					}
					submit={
						'group' === adding.kind
							? __( 'Add group', 'ohmylms' )
							: __( 'Add skill', 'ohmylms' )
					}
					busy={ operation.busy }
					error={ operation.error }
					onClose={ () => setAdding( null ) }
					onSubmit={ ( name, code ) =>
						'group' === adding.kind
							? run(
									'add_group',
									{ item_id: adding.item, name, code },
									() => setAdding( null )
								)
							: run(
									'create_skill',
									{ group_id: adding.group, name, code },
									() => setAdding( null )
								)
					}
				/>
			) }
		</div>
	);
}
