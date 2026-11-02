import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Badge } from '../../../ui/Badge';
import { itemPath, type CurriculumItem } from './api';

/**
 * Expandable curriculum listing. Searching keeps matching descendants and their ancestors visible.
 * @param root0
 * @param root0.items
 * @param root0.search
 * @param root0.selected
 * @param root0.disabled
 * @param root0.onSelect
 * @param root0.onAddChild
 */
export function CurriculumTree( {
	items,
	search,
	selected,
	disabled,
	onSelect,
	onAddChild,
}: {
	items: CurriculumItem[];
	search: string;
	selected: number | null;
	disabled: boolean;
	onSelect: ( item: CurriculumItem ) => void;
	onAddChild: ( item: CurriculumItem ) => void;
} ) {
	const [ expanded, setExpanded ] = useState< number[] >( [] );
	const query = search.trim().toLocaleLowerCase();
	const visible = items.filter(
		( item ) =>
			! query ||
			itemPath( items, item ).toLocaleLowerCase().includes( query )
	);
	const ids = new Set( visible.map( ( item ) => item.id ) );
	for ( const item of visible ) {
		let parent = items.find( ( row ) => row.id === item.parent_id );
		const visited = new Set< number >( [ item.id ] );
		while ( parent && ! visited.has( parent.id ) ) {
			visited.add( parent.id );
			ids.add( parent.id );
			parent = items.find( ( row ) => row.id === parent?.parent_id );
		}
	}
	function branch( parent: number, ancestors: number[] = [] ): JSX.Element {
		return (
			<ul className="oml-curriculum-branches">
				{ items
					.filter(
						( item ) =>
							item.parent_id === parent &&
							ids.has( item.id ) &&
							! ancestors.includes( item.id )
					)
					.map( ( item ) => {
						const children = items.filter(
							( row ) =>
								row.parent_id === item.id && ids.has( row.id )
						);
						const open = !! query || expanded.includes( item.id );
						return (
							<li key={ item.id }>
								<div className="oml-curriculum-node">
									{ children.length > 0 && (
										<Button
											icon={
												open
													? 'arrow-down-alt2'
													: 'arrow-right-alt2'
											}
											aria-expanded={ open }
											aria-controls={ `oml-curriculum-children-${ item.id }` }
											label={ sprintf(
												/* translators: %s: curriculum item. */ __(
													'Expand or collapse %s',
													'ohmylms'
												),
												item.name
											) }
											disabled={ !! query }
											onClick={ () =>
												setExpanded( ( current ) =>
													open
														? current.filter(
																( id ) =>
																	id !==
																	item.id
															)
														: [
																...current,
																item.id,
															]
												)
											}
										/>
									) }
									<Button
										variant="tertiary"
										isPressed={ selected === item.id }
										disabled={ disabled }
										onClick={ () => onSelect( item ) }
									>
										{ item.name }
									</Button>
									<Badge>{ item.item_type }</Badge>
									<Button
										variant="link"
										disabled={ disabled }
										onClick={ () => onAddChild( item ) }
										aria-label={ sprintf(
											/* translators: %s: parent item. */ __(
												'Add child to %s',
												'ohmylms'
											),
											item.name
										) }
									>
										{ __( 'Add child', 'ohmylms' ) }
									</Button>
									{ item.is_syllabus && (
										<Link
											to={ `/content-hub/curriculum/syllabus/${ item.id }` }
										>
											{ __(
												'Open syllabus editor',
												'ohmylms'
											) }
										</Link>
									) }
									{ item.course_id > 0 && (
										<Link
											to={ `/content-hub/courses/${ item.course_id }` }
										>
											{ __(
												'Edit course structure',
												'ohmylms'
											) }
										</Link>
									) }
								</div>
								{ children.length > 0 && (
									<div
										id={ `oml-curriculum-children-${ item.id }` }
										hidden={ ! open }
									>
										{ open &&
											branch( item.id, [
												...ancestors,
												item.id,
											] ) }
									</div>
								) }
							</li>
						);
					} ) }
			</ul>
		);
	}
	return (
		<section aria-label={ __( 'Curriculum hierarchy', 'ohmylms' ) }>
			<div className="oml-authoring-actions">
				<Button
					variant="secondary"
					disabled={ !! query }
					onClick={ () =>
						setExpanded( items.map( ( item ) => item.id ) )
					}
				>
					{ __( 'Expand all', 'ohmylms' ) }
				</Button>
				<Button
					variant="tertiary"
					disabled={ !! query }
					onClick={ () => setExpanded( [] ) }
				>
					{ __( 'Collapse all', 'ohmylms' ) }
				</Button>
			</div>
			{ branch( 0 ) }
			{ query && ! visible.length && (
				<p role="status">
					{ __( 'No matching curriculum items.', 'ohmylms' ) }
				</p>
			) }
		</section>
	);
}
