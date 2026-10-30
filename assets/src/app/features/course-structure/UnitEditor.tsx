import { Button, TextareaControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import type { Activity, Unit } from './model';

/**
 * Unit fields and lesson placements, with no transport or application-runtime dependencies.
 * @param root0
 * @param root0.unit
 * @param root0.items
 * @param root0.first
 * @param root0.last
 * @param root0.onChange
 * @param root0.onMove
 * @param root0.onAttach
 * @param root0.onDetach
 * @param root0.onMoveLesson
 */
export function UnitEditor( {
	unit,
	items,
	first,
	last,
	onChange,
	onMove,
	onAttach,
	onDetach,
	onMoveLesson,
}: {
	unit: Unit;
	items: Activity[];
	first: boolean;
	last: boolean;
	onChange: ( patch: Partial< Unit > ) => void;
	onMove: ( offset: number ) => void;
	onAttach: () => void;
	onDetach: ( item: Activity ) => void;
	onMoveLesson: ( id: string, offset: number ) => void;
} ) {
	return (
		<section aria-label={ unit.title || __( 'New unit', 'ohmylms' ) }>
			<h2>{ unit.title || __( 'New unit', 'ohmylms' ) }</h2>
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
			<ul>
				{ items.map( ( item ) => (
					<li key={ item.id }>
						{ item.name || String( item.content_id ) } (
						{ item.type })
						{ item.type === 'lesson' && (
							<>
								<Link
									to={ `/content-hub/lessons/${ item.content_id }` }
								>
									{ __( 'Edit lesson content', 'ohmylms' ) }
								</Link>
								<Button
									onClick={ () =>
										onMoveLesson( item.id, -1 )
									}
								>
									{ __( 'Move lesson up', 'ohmylms' ) }
								</Button>
								<Button
									onClick={ () => onMoveLesson( item.id, 1 ) }
								>
									{ __( 'Move lesson down', 'ohmylms' ) }
								</Button>
								<Button onClick={ () => onDetach( item ) }>
									{ __( 'Detach lesson', 'ohmylms' ) }
								</Button>
							</>
						) }
					</li>
				) ) }
			</ul>
			<Button onClick={ onAttach }>
				{ __( 'Attach lesson to unit', 'ohmylms' ) }
			</Button>
		</section>
	);
}
