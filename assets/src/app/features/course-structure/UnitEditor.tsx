import {
	Button,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
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
			aria-label={ unitName }
			data-oml-drop={ key }
			tabIndex={ -1 }
			style={ dragStyle( drag, key ) }
		>
			<h2>
				<Grip handlers={ handle( key ) } />
				{ unitName }
			</h2>
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
			<TextareaControl
				label={ __( 'Unit description', 'ohmylms' ) }
				value={ unit.description }
				onChange={ ( description ) => onChange( { description } ) }
			/>
			<Button
				disabled={ 0 === at }
				data-oml-control="unit-up"
				onClick={ () => neighbour( -1 ) }
			>
				{ __( 'Move unit up', 'ohmylms' ) }
			</Button>
			<Button
				disabled={ at === units.length - 1 }
				data-oml-control="unit-down"
				onClick={ () => neighbour( 1 ) }
			>
				{ __( 'Move unit down', 'ohmylms' ) }
			</Button>
			<Button isDestructive onClick={ onDelete }>
				{ __( 'Delete unit', 'ohmylms' ) }
			</Button>
			<ol>
				{ items.map( ( item, index ) => {
					const name = item.name || String( item.content_id );
					const edit = EDIT_ROUTES[ item.type ];
					return (
						<li
							key={ item.id }
							data-oml-drop={ item.id }
							tabIndex={ -1 }
							style={ dragStyle( drag, item.id ) }
						>
							<Grip handlers={ handle( item.id ) } />
							{ name } (
							{ TYPE_LABELS[ item.type ]?.() ?? item.type }){ ' ' }
							{ edit && (
								<Link to={ `${ edit }${ item.content_id }` }>
									{ sprintf(
										/* translators: %s: activity name. */
										__( 'Edit %s', 'ohmylms' ),
										name
									) }
								</Link>
							) }
							<Button
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
							>
								{ sprintf(
									/* translators: %s: activity name. */
									__( 'Move %s up', 'ohmylms' ),
									name
								) }
							</Button>
							<Button
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
							>
								{ sprintf(
									/* translators: %s: activity name. */
									__( 'Move %s down', 'ohmylms' ),
									name
								) }
							</Button>
							{ units.length > 1 && (
								<SelectControl< string >
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
							<Button onClick={ () => onDetach( item ) }>
								{ sprintf(
									/* translators: %s: activity name. */
									__( 'Detach %s', 'ohmylms' ),
									name
								) }
							</Button>
						</li>
					);
				} ) }
			</ol>
			<Button onClick={ () => onAdd( 'lesson' ) }>
				{ __( 'Attach lesson to unit', 'ohmylms' ) }
			</Button>
			<Button onClick={ () => onAdd( 'quiz' ) }>
				{ __( 'Add quiz to unit', 'ohmylms' ) }
			</Button>
			<Button onClick={ () => onAdd( 'assignment' ) }>
				{ __( 'Add assignment to unit', 'ohmylms' ) }
			</Button>
		</section>
	);
}
