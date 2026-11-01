import {
	Button,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import type { ResourceType } from './api';
import type { Activity, Unit } from './model';
import { unitKey } from './reorder';
import {
	dragStyle,
	GRIP,
	type DragState,
	type useDragMove,
} from './useDragMove';

function Grip( { handlers }: { handlers: object } ) {
	return (
		<span
			aria-hidden="true"
			title={ __( 'Drag to reorder', 'ohmylms' ) }
			style={ GRIP }
			{ ...handlers }
		>
			⠿
		</span>
	);
}

const TYPE_LABELS: Record< string, () => string > = {
	lesson: () => __( 'Lesson', 'ohmylms' ),
	quiz: () => __( 'Quiz', 'ohmylms' ),
	assignment: () => __( 'Assignment', 'ohmylms' ),
	practice: () => __( 'Practice', 'ohmylms' ),
};

const TYPE_ICONS: Record< string, string > = {
	lesson: 'media-document',
	quiz: 'editor-help',
	assignment: 'portfolio',
	practice: 'update',
};

const EDIT_ROUTES: Record< string, string > = {
	lesson: '/content-hub/lessons/',
	quiz: '/quizzes/',
	assignment: '/assignments/',
};

/**
 * Unit fields and activity placements, with no transport or application-runtime dependencies.
 * @param root0
 * @param root0.unit
 * @param root0.units
 * @param root0.items
 * @param root0.onChange
 * @param root0.onAdd
 * @param root0.onDelete
 * @param root0.onDetach
 * @param root0.onReorder Moves a unit or activity (source) before/after a target; see `dropOn`.
 * @param root0.drag      Current pointer drag, for row feedback.
 * @param root0.handle    Pointer handlers for a row's grip.
 */
export function UnitEditor( {
	unit,
	units,
	items,
	onChange,
	onAdd,
	onDelete,
	onDetach,
	onReorder,
	drag,
	handle,
}: {
	unit: Unit;
	units: Unit[];
	items: Activity[];
	onChange: ( patch: Partial< Unit > ) => void;
	onAdd: ( type: ResourceType ) => void;
	onDelete: () => void;
	onDetach: ( item: Activity ) => void;
	onReorder: (
		source: string,
		target: string,
		after: boolean,
		control: string
	) => void;
	drag: DragState | null;
	handle: ReturnType< typeof useDragMove >[ 'handle' ];
} ) {
	const unitName = unit.title || __( 'New unit', 'ohmylms' );
	const [ open, setOpen ] = useState( true );
	const key = unitKey( unit.id );
	const at = units.indexOf( unit );
	const neighbour = ( offset: number ) =>
		onReorder(
			key,
			unitKey( units[ at + offset ]!.id ),
			offset > 0,
			offset > 0 ? 'unit-down' : 'unit-up'
		);
	return (
		<section
			className="oml-unit"
			aria-label={ unitName }
			data-oml-drop={ key }
			tabIndex={ -1 }
			style={ dragStyle( drag, key ) }
		>
			<header className="oml-unit-header">
				<Grip handlers={ handle( key ) } />
				<span className="oml-unit-number">{ unit.number }</span>
				<h2 className="oml-unit-title">{ unitName }</h2>
				<span className="oml-unit-count">
					{ sprintf(
						/* translators: %d: number of activities. */
						_n(
							'%d activity',
							'%d activities',
							items.length,
							'ohmylms'
						),
						items.length
					) }
				</span>
				<div className="oml-unit-tools">
					<Button
						size="small"
						icon="edit"
						label={ __( 'Unit details', 'ohmylms' ) }
						aria-expanded={ open }
						isPressed={ open }
						onClick={ () => setOpen( ! open ) }
					/>
					<Button
						size="small"
						icon="arrow-up-alt2"
						label={ __( 'Move unit up', 'ohmylms' ) }
						disabled={ 0 === at }
						data-oml-control="unit-up"
						onClick={ () => neighbour( -1 ) }
					/>
					<Button
						size="small"
						icon="arrow-down-alt2"
						label={ __( 'Move unit down', 'ohmylms' ) }
						disabled={ at === units.length - 1 }
						data-oml-control="unit-down"
						onClick={ () => neighbour( 1 ) }
					/>
					<Button
						size="small"
						icon="trash"
						isDestructive
						label={ __( 'Delete unit', 'ohmylms' ) }
						onClick={ onDelete }
					/>
				</div>
			</header>
			<div className="oml-unit-details" hidden={ ! open }>
				<div className="oml-unit-row">
					<TextControl
						label={ __( 'Unit title', 'ohmylms' ) }
						value={ unit.title }
						onChange={ ( title ) => onChange( { title } ) }
					/>
					<TextControl
						label={ __( 'Unit number', 'ohmylms' ) }
						value={ unit.number }
						onChange={ ( number ) => onChange( { number } ) }
					/>
				</div>
				<TextareaControl
					label={ __( 'Unit description', 'ohmylms' ) }
					value={ unit.description }
					rows={ 2 }
					onChange={ ( description ) => onChange( { description } ) }
				/>
			</div>
			<ol className="oml-unit-items">
				{ items.length === 0 && (
					<li className="oml-unit-empty">
						{ __( 'No activities yet. Add one below.', 'ohmylms' ) }
					</li>
				) }
				{ items.map( ( item, index ) => {
					const name = item.name || String( item.content_id );
					const edit = EDIT_ROUTES[ item.type ];
					return (
						<li
							key={ item.id }
							className="oml-outline-item"
							data-oml-drop={ item.id }
							tabIndex={ -1 }
							style={ dragStyle( drag, item.id ) }
						>
							<Grip handlers={ handle( item.id ) } />
							<span
								className={ `dashicons dashicons-${
									TYPE_ICONS[ item.type ] ?? 'media-default'
								} oml-outline-item-icon` }
								aria-hidden="true"
							/>
							<span className="oml-outline-item-name">
								{ edit ? (
									<Link
										to={ `${ edit }${ item.content_id }` }
										aria-label={ sprintf(
											/* translators: %s: activity name. */
											__( 'Edit %s', 'ohmylms' ),
											name
										) }
									>
										{ name }
									</Link>
								) : (
									name
								) }
							</span>{ ' ' }
							<span
								className={ `oml-badge oml-outline-item-type oml-outline-item-type-${ item.type }` }
							>
								{ TYPE_LABELS[ item.type ]?.() ?? item.type }
							</span>
							<span className="oml-outline-item-tools">
								{ units.length > 1 && (
									<SelectControl< string >
										__nextHasNoMarginBottom
										hideLabelFromVision
										size="small"
										label={ sprintf(
											/* translators: %s: activity name. */
											__( 'Unit for %s', 'ohmylms' ),
											name
										) }
										value={ String( unit.id ) }
										data-oml-control="unit"
										options={ units.map( ( row ) => ( {
											value: String( row.id ),
											label:
												row.title ||
												__( 'New unit', 'ohmylms' ),
										} ) ) }
										onChange={ ( value ) =>
											onReorder(
												item.id,
												unitKey( value ),
												true,
												'unit'
											)
										}
									/>
								) }
								<Button
									size="small"
									icon="arrow-up-alt2"
									label={ sprintf(
										/* translators: %s: activity name. */
										__( 'Move %s up', 'ohmylms' ),
										name
									) }
									disabled={ 0 === index }
									data-oml-control="up"
									onClick={ () =>
										onReorder(
											item.id,
											items[ index - 1 ]!.id,
											false,
											'up'
										)
									}
								/>
								<Button
									size="small"
									icon="arrow-down-alt2"
									label={ sprintf(
										/* translators: %s: activity name. */
										__( 'Move %s down', 'ohmylms' ),
										name
									) }
									disabled={ index === items.length - 1 }
									data-oml-control="down"
									onClick={ () =>
										onReorder(
											item.id,
											items[ index + 1 ]!.id,
											true,
											'down'
										)
									}
								/>
								<Button
									size="small"
									icon="no-alt"
									label={ sprintf(
										/* translators: %s: activity name. */
										__( 'Detach %s', 'ohmylms' ),
										name
									) }
									onClick={ () => onDetach( item ) }
								/>
							</span>
						</li>
					);
				} ) }
			</ol>
			<div className="oml-unit-add">
				<Button
					size="small"
					variant="tertiary"
					icon="plus"
					aria-label={ __( 'Attach lesson to unit', 'ohmylms' ) }
					onClick={ () => onAdd( 'lesson' ) }
				>
					{ __( 'Lesson', 'ohmylms' ) }
				</Button>
				<Button
					size="small"
					variant="tertiary"
					icon="plus"
					aria-label={ __( 'Add quiz to unit', 'ohmylms' ) }
					onClick={ () => onAdd( 'quiz' ) }
				>
					{ __( 'Quiz', 'ohmylms' ) }
				</Button>
				<Button
					size="small"
					variant="tertiary"
					icon="plus"
					aria-label={ __( 'Add assignment to unit', 'ohmylms' ) }
					onClick={ () => onAdd( 'assignment' ) }
				>
					{ __( 'Assignment', 'ohmylms' ) }
				</Button>
			</div>
		</section>
	);
}
