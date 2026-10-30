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
 * @param root0.first
 * @param root0.last
 * @param root0.onChange
 * @param root0.onMove
 * @param root0.onAdd
 * @param root0.onDelete
 * @param root0.onDetach
 * @param root0.onMoveItem
 * @param root0.onMoveToUnit
 */
export function UnitEditor( {
	unit,
	units,
	items,
	first,
	last,
	onChange,
	onMove,
	onAdd,
	onDelete,
	onDetach,
	onMoveItem,
	onMoveToUnit,
}: {
	unit: Unit;
	units: Unit[];
	items: Activity[];
	first: boolean;
	last: boolean;
	onChange: ( patch: Partial< Unit > ) => void;
	onMove: ( offset: number ) => void;
	onAdd: ( type: ResourceType ) => void;
	onDelete: () => void;
	onDetach: ( item: Activity ) => void;
	onMoveItem: ( id: string, offset: number ) => void;
	onMoveToUnit: ( id: string, unit: number | string ) => void;
} ) {
	const unitName = unit.title || __( 'New unit', 'ohmylms' );
	return (
		<section aria-label={ unitName }>
			<h2>{ unitName }</h2>
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
			<Button disabled={ first } onClick={ () => onMove( -1 ) }>
				{ __( 'Move unit up', 'ohmylms' ) }
			</Button>
			<Button disabled={ last } onClick={ () => onMove( 1 ) }>
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
						<li key={ item.id }>
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
								onClick={ () => onMoveItem( item.id, -1 ) }
							>
								{ sprintf(
									/* translators: %s: activity name. */
									__( 'Move %s up', 'ohmylms' ),
									name
								) }
							</Button>
							<Button
								disabled={ index === items.length - 1 }
								onClick={ () => onMoveItem( item.id, 1 ) }
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
									options={ units.map( ( row ) => ( {
										value: String( row.id ),
										label:
											row.title ||
											__( 'New unit', 'ohmylms' ),
									} ) ) }
									onChange={ ( value ) => {
										const target = units.find(
											( row ) =>
												String( row.id ) === value
										);
										if ( target ) {
											onMoveToUnit( item.id, target.id );
										}
									} }
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
