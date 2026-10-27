/** @jsx createElement */
import { createElement, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { lessonSkillIds } from './lessonSkills.mjs';
import { outlineUnitSummary } from './outline.mjs';
import { provenanceBadge } from './provenance.mjs';
import { badgeLabel } from './SkillProvenance';

const same = ( left, right ) => String( left || 0 ) === String( right || 0 );
const terminal = ( item ) =>
	[ 'unit-test', 'course-challenge' ].includes( item.style );

/**
 * A skill's label, or a clear marker when the shared skill no longer exists.
 *
 * @param {Object} names  Skill labels keyed by term ID.
 * @param {number} termId Skill term ID.
 * @return {string} Label.
 */
export function outlineSkillName( names, termId ) {
	return (
		names?.[ termId ] ||
		sprintf(
			/* translators: %d: skill ID. */ __(
				'Missing skill #%d',
				'ohmylms'
			),
			termId
		)
	);
}

/**
 * Dashicon for a placed activity, matching its learner-facing kind.
 *
 * @param {Object} item Placement.
 * @return {string} Dashicon class suffix.
 */
function icon( item ) {
	if ( 'practice' === item.type ) {
		return 'edit-large';
	}
	if ( 'quiz' === item.type ) {
		return (
			{
				'unit-test': 'awards',
				'course-challenge': 'flag',
			}[ item.style ] || 'forms'
		);
	}
	if ( 'assignment' === item.type ) {
		return 'portfolio';
	}
	return 'video' === item.format ? 'video-alt3' : 'media-document';
}

/**
 * Khan Academy-shaped outline: unit cards holding lessons, their activities and skills,
 * a unit test slot at the end of every unit and a course challenge slot after the units.
 *
 * @param {Object}                                                    props             Component properties.
 * @param {Object}                                                    props.draft       Current outline.
 * @param {?Object}                                                   props.selected    Selected row.
 * @param {boolean}                                                   props.busy        A save is running.
 * @param {(type: string, id: string|number) => void}                 props.onSelect    Select a row for the inspector.
 * @param {() => void}                                                props.onAddUnit   Add a unit.
 * @param {(unitId: number) => void}                                  props.onAddLesson Add a lesson to a unit.
 * @param {(unitId: number, groupId: string, kind: string) => void}   props.onAdd       Open the activity picker.
 * @param {(type: string, id: string|number, target: Object) => void} props.onMove      Move a dragged unit, lesson or activity.
 */
export function CourseOutlineTree( {
	draft,
	selected,
	busy,
	onSelect,
	onAddUnit,
	onAddLesson,
	onAdd,
	onMove,
} ) {
	const [ collapsed, setCollapsed ] = useState( [] );
	const [ drag, setDrag ] = useState( null );
	const [ over, setOver ] = useState( '' );
	const codes = draft.skill_codes;
	const code = ( termId ) =>
		codes?.[ termId ] ? (
			<span className="ohmylms-outline-code">{ codes[ termId ] }</span>
		) : null;
	// Native drag and drop for pointer users; the inspector's Move buttons remain the keyboard path.
	const dragProps = ( type, id ) =>
		busy
			? {}
			: {
					draggable: true,
					onDragStart: ( event ) => {
						event.stopPropagation();
						event.dataTransfer.effectAllowed = 'move';
						event.dataTransfer.setData(
							'text/plain',
							String( id )
						);
						setDrag( { type, id } );
					},
					onDragEnd: () => {
						setDrag( null );
						setOver( '' );
					},
				};
	const dropProps = ( key, targetFor ) => {
		const target = drag ? targetFor( drag.type ) : null;
		if ( ! target ) {
			return {};
		}
		return {
			onDragOver: ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				event.dataTransfer.dropEffect = 'move';
				setOver( key );
			},
			onDragLeave: ( event ) => {
				if ( ! event.currentTarget.contains( event.relatedTarget ) ) {
					setOver( ( current ) =>
						current === key ? '' : current
					);
				}
			},
			onDrop: ( event ) => {
				event.preventDefault();
				event.stopPropagation();
				onMove( drag.type, drag.id, target );
				setDrag( null );
				setOver( '' );
			},
		};
	};
	const dropClass = ( key ) => ( over === key ? ' is-drop-target' : '' );
	const groups = draft.program.groups || [];
	const names = draft.skill_names;
	const items = draft.program.items;
	const rowsIn = ( unitId, groupId = '' ) =>
		items.filter(
			( item ) =>
				same( item.chapter_id, unitId ) &&
				( item.group_id || '' ) === groupId
		);
	const practised = ( termId ) =>
		items.some(
			( item ) => 'practice' === item.type && item.content_id === termId
		);
	const isSelected = ( id ) => selected?.id === id;
	const itemRow = ( item, unitId, groupId = '' ) => {
		const fixed = terminal( item );
		const label =
			item.title ||
			item.name ||
			( 'practice' === item.type
				? outlineSkillName( names, item.content_id )
				: __( 'Unavailable resource', 'ohmylms' ) );
		let badge = '';
		if ( 'practice' !== item.type && ! item.status ) {
			badge = __( 'Missing', 'ohmylms' );
		} else if ( 'practice' !== item.type && 'publish' !== item.status ) {
			badge = __( 'Draft', 'ohmylms' );
		} else if ( 'practice' === item.type && ! names?.[ item.content_id ] ) {
			badge = __( 'Missing skill', 'ohmylms' );
		} else if ( 'practice' === item.type ) {
			const origin = provenanceBadge(
				draft.skill_meta?.[ item.content_id ]
			);
			badge = origin ? badgeLabel( origin.kind ) : '';
		}
		return (
			<li
				key={ item.id }
				className={ `ohmylms-outline-row${
					isSelected( item.id ) ? ' is-selected' : ''
				}${ dropClass( `item-${ item.id }` ) }` }
				{ ...( fixed ? {} : dragProps( 'activity', item.id ) ) }
				{ ...( fixed
					? {}
					: dropProps( `item-${ item.id }`, ( type ) =>
							'activity' === type
								? { unitId, groupId, beforeId: item.id }
								: null
						) ) }
			>
				<span
					className={ `dashicons dashicons-${ icon( item ) }` }
					aria-hidden="true"
				/>
				{ 'practice' === item.type && code( item.content_id ) }
				<Button
					className="ohmylms-outline-row-button"
					aria-current={ isSelected( item.id ) ? 'true' : undefined }
					onClick={ () => onSelect( 'activity', item.id ) }
				>
					{ label }
				</Button>
				{ badge && (
					<span className="ohmylms-outline-badge is-warning">
						{ badge }
					</span>
				) }
			</li>
		);
	};
	const skillRow = ( outcome ) => (
		<li
			key={ `skill-${ outcome.term_id }` }
			className={ `ohmylms-outline-row is-skill${
				isSelected( outcome.term_id ) ? ' is-selected' : ''
			}` }
		>
			<span
				className="dashicons dashicons-lightbulb"
				aria-hidden="true"
			/>
			{ code( outcome.term_id ) }
			<Button
				className="ohmylms-outline-row-button"
				aria-current={
					isSelected( outcome.term_id ) ? 'true' : undefined
				}
				onClick={ () => onSelect( 'skill', outcome.term_id ) }
			>
				{ outlineSkillName( names, outcome.term_id ) }
			</Button>
			{ provenanceBadge( draft.skill_meta?.[ outcome.term_id ] ) && (
				<span className="ohmylms-outline-badge is-warning">
					{ badgeLabel(
						provenanceBadge( draft.skill_meta?.[ outcome.term_id ] )
							.kind
					) }
				</span>
			) }
			<span
				className={ `ohmylms-outline-badge${
					names?.[ outcome.term_id ] ? '' : ' is-warning'
				}` }
			>
				{ names?.[ outcome.term_id ]
					? __( 'No practice', 'ohmylms' )
					: __( 'Missing', 'ohmylms' ) }
			</span>
		</li>
	);
	const addButton = ( label, text, onClick ) => (
		<Button
			className="ohmylms-outline-add"
			variant="link"
			icon="plus-alt2"
			iconSize={ 16 }
			disabled={ busy }
			aria-label={ label }
			onClick={ onClick }
		>
			{ text }
		</Button>
	);
	const allCollapsed =
		draft.units.length > 0 &&
		draft.units.every( ( unit ) => collapsed.includes( unit.id ) );
	const challenge = rowsIn( 0 ).filter(
		( item ) => 'course-challenge' === item.style
	);
	const courseLevel = rowsIn( 0 ).filter(
		( item ) => 'course-challenge' !== item.style
	);
	return (
		<nav
			className="ohmylms-outline-tree"
			aria-label={ __( 'Units, lessons and activities', 'ohmylms' ) }
		>
			<div className="ohmylms-outline-tree-toolbar">
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ onAddUnit }
				>
					{ __( 'Add unit', 'ohmylms' ) }
				</Button>
				{ draft.units.length > 0 && (
					<Button
						variant="tertiary"
						onClick={ () =>
							setCollapsed(
								allCollapsed
									? []
									: draft.units.map( ( unit ) => unit.id )
							)
						}
					>
						{ allCollapsed
							? __( 'Expand all', 'ohmylms' )
							: __( 'Collapse all', 'ohmylms' ) }
					</Button>
				) }
			</div>
			{ ! draft.units.length && (
				<p className="ohmylms-outline-empty">
					{ __(
						'This course has no units yet. Add a unit, then give it lessons with videos, readings and practice.',
						'ohmylms'
					) }
				</p>
			) }
			<ol className="ohmylms-outline-units">
				{ draft.units.map( ( unit, index ) => {
					const open = ! collapsed.includes( unit.id );
					const summary = outlineUnitSummary(
						draft.program,
						unit.id
					);
					const lessons = groups.filter( ( row ) =>
						same( row.chapter_id, unit.id )
					);
					const loose = draft.program.outcomes.filter(
						( row ) =>
							same( row.chapter_id, unit.id ) &&
							! row.group_id &&
							! practised( row.term_id )
					);
					const ungrouped = rowsIn( unit.id ).filter(
						( item ) => ! terminal( item )
					);
					const tests = rowsIn( unit.id ).filter(
						( item ) => 'unit-test' === item.style
					);
					const panel = `ohmylms-outline-unit-${ unit.id }`;
					return (
						<li
							key={ unit.id }
							className={ `ohmylms-outline-unit${
								isSelected( unit.id ) ? ' is-selected' : ''
							}` }
							{ ...dragProps( 'unit', unit.id ) }
						>
							<div
								className={ `ohmylms-outline-unit-header${ dropClass(
									`unit-${ unit.id }`
								) }` }
								{ ...dropProps(
									`unit-${ unit.id }`,
									( type ) => {
										if ( 'unit' === type ) {
											return { beforeId: unit.id };
										}
										return 'lesson' === type
											? { unitId: unit.id, beforeId: '' }
											: {
													unitId: unit.id,
													groupId: '',
													beforeId: '',
												};
									}
								) }
							>
								<Button
									className="ohmylms-outline-toggle"
									icon={
										open
											? 'arrow-down-alt2'
											: 'arrow-right-alt2'
									}
									iconSize={ 16 }
									aria-expanded={ open }
									aria-controls={ panel }
									label={ sprintf(
										/* translators: %s: unit title. */ __(
											'Show contents of %s',
											'ohmylms'
										),
										unit.title
									) }
									showTooltip={ false }
									onClick={ () =>
										setCollapsed(
											open
												? [ ...collapsed, unit.id ]
												: collapsed.filter(
														( id ) => id !== unit.id
													)
										)
									}
								/>
								<div className="ohmylms-outline-unit-title">
									<span className="ohmylms-outline-eyebrow">
										{ sprintf(
											/* translators: %s: unit number. */ __(
												'Unit %s',
												'ohmylms'
											),
											unit.number || index + 1
										) }
									</span>
									<Button
										className="ohmylms-outline-row-button"
										aria-current={
											isSelected( unit.id )
												? 'true'
												: undefined
										}
										onClick={ () =>
											onSelect( 'unit', unit.id )
										}
									>
										<strong>{ unit.title }</strong>
									</Button>
									<span className="ohmylms-outline-meta">
										{ [
											sprintf(
												/* translators: %d: number of lessons. */ _n(
													'%d lesson',
													'%d lessons',
													summary.lessons,
													'ohmylms'
												),
												summary.lessons
											),
											sprintf(
												/* translators: %d: number of skills. */ _n(
													'%d skill',
													'%d skills',
													summary.skills,
													'ohmylms'
												),
												summary.skills
											),
											sprintf(
												/* translators: %d: number of activities. */ _n(
													'%d activity',
													'%d activities',
													summary.activities,
													'ohmylms'
												),
												summary.activities
											),
										].join( ' · ' ) }
									</span>
								</div>
							</div>
							<div id={ panel } hidden={ ! open }>
								<ol className="ohmylms-outline-lessons">
									{ lessons.map( ( lesson ) => {
										const lessonItems = rowsIn(
											unit.id,
											lesson.id
										);
										const lessonSkills =
											draft.program.outcomes.filter(
												( row ) =>
													lessonSkillIds(
														draft.program,
														lesson.id
													).includes( row.term_id )
											);
										return (
											<li
												key={ lesson.id }
												className={ `ohmylms-outline-lesson${
													isSelected( lesson.id )
														? ' is-selected'
														: ''
												}${ dropClass(
													`lesson-${ lesson.id }`
												) }` }
												{ ...dragProps(
													'lesson',
													lesson.id
												) }
												{ ...dropProps(
													`lesson-${ lesson.id }`,
													( type ) => {
														if ( 'unit' === type ) {
															return null;
														}
														return 'lesson' === type
															? {
																	unitId: unit.id,
																	beforeId:
																		lesson.id,
																}
															: {
																	unitId: unit.id,
																	groupId:
																		lesson.id,
																	beforeId:
																		'',
																};
													}
												) }
											>
												<Button
													className="ohmylms-outline-row-button ohmylms-outline-lesson-title"
													aria-current={
														isSelected( lesson.id )
															? 'true'
															: undefined
													}
													onClick={ () =>
														onSelect(
															'lesson',
															lesson.id
														)
													}
												>
													{ lesson.title }
												</Button>
												{ lessonItems.length > 0 ||
												lessonSkills.length > 0 ? (
													<ul className="ohmylms-outline-rows">
														{ lessonItems.map(
															( item ) =>
																itemRow(
																	item,
																	unit.id,
																	lesson.id
																)
														) }
														{ lessonSkills.map(
															skillRow
														) }
													</ul>
												) : (
													<p className="ohmylms-outline-hint">
														{ __(
															'No activities yet.',
															'ohmylms'
														) }
													</p>
												) }
												{ addButton(
													sprintf(
														/* translators: %s: lesson title. */ __(
															'Add activity to %s',
															'ohmylms'
														),
														lesson.title
													),
													__(
														'Add activity',
														'ohmylms'
													),
													() =>
														onAdd(
															unit.id,
															lesson.id,
															''
														)
												) }
											</li>
										);
									} ) }
								</ol>
								{ ( ungrouped.length > 0 ||
									loose.length > 0 ) && (
									<div
										className={ `ohmylms-outline-loose${ dropClass(
											`loose-${ unit.id }`
										) }` }
										{ ...dropProps(
											`loose-${ unit.id }`,
											( type ) =>
												'activity' === type
													? {
															unitId: unit.id,
															groupId: '',
															beforeId: '',
														}
													: null
										) }
									>
										<h3>
											{ __(
												'Not in a lesson',
												'ohmylms'
											) }
										</h3>
										<ul className="ohmylms-outline-rows">
											{ ungrouped.map( ( item ) =>
												itemRow( item, unit.id )
											) }
											{ loose.map( skillRow ) }
										</ul>
									</div>
								) }
								<div className="ohmylms-outline-unit-actions">
									{ addButton(
										sprintf(
											/* translators: %s: unit title. */ __(
												'Add lesson to %s',
												'ohmylms'
											),
											unit.title
										),
										__( 'Add lesson', 'ohmylms' ),
										() => onAddLesson( unit.id )
									) }
								</div>
								<div className="ohmylms-outline-slot">
									{ tests.length > 0 ? (
										<ul className="ohmylms-outline-rows">
											{ tests.map( ( item ) =>
												itemRow( item, unit.id )
											) }
										</ul>
									) : (
										<Button
											className="ohmylms-outline-slot-button"
											disabled={ busy }
											aria-label={ sprintf(
												/* translators: %s: unit title. */ __(
													'Add unit test to %s',
													'ohmylms'
												),
												unit.title
											) }
											onClick={ () =>
												onAdd(
													unit.id,
													'',
													'unit-test'
												)
											}
										>
											<span
												className="dashicons dashicons-awards"
												aria-hidden="true"
											/>
											{ __( 'Add unit test', 'ohmylms' ) }
										</Button>
									) }
								</div>
							</div>
						</li>
					);
				} ) }
			</ol>
			{ draft.units.length > 0 && (
				<div
					className={ `ohmylms-outline-course-end${ dropClass(
						'course-end'
					) }` }
					{ ...dropProps( 'course-end', ( type ) =>
						'unit' === type ? { beforeId: '' } : null
					) }
				>
					{ courseLevel.length > 0 && (
						<div className="ohmylms-outline-loose">
							<h3>
								{ __( 'Course-level activities', 'ohmylms' ) }
							</h3>
							<ul className="ohmylms-outline-rows">
								{ courseLevel.map( ( item ) =>
									itemRow( item, 0 )
								) }
							</ul>
						</div>
					) }
					<div className="ohmylms-outline-slot">
						{ challenge.length > 0 ? (
							<ul className="ohmylms-outline-rows">
								{ challenge.map( ( item ) =>
									itemRow( item, 0 )
								) }
							</ul>
						) : (
							<Button
								className="ohmylms-outline-slot-button"
								disabled={ busy }
								onClick={ () =>
									onAdd( 0, '', 'course-challenge' )
								}
							>
								<span
									className="dashicons dashicons-flag"
									aria-hidden="true"
								/>
								{ __( 'Add course challenge', 'ohmylms' ) }
							</Button>
						) }
					</div>
				</div>
			) }
		</nav>
	);
}
