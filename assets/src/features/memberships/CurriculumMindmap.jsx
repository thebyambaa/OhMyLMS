import { createElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Mindmap } from '../../extensions/mindmap/Mindmap';

/**
 * Membership-specific adapter: selecting a curriculum item includes everything below it.
 * @param root0
 * @param root0.items
 * @param root0.selected
 * @param root0.onChange
 * @param root0.emptyMessage
 */
export function CurriculumMindmap( {
	items,
	selected,
	onChange,
	emptyMessage,
} ) {
	return (
		<Mindmap
			items={ items.map( ( item ) => ( {
				...item,
				id: Number( item.value ),
			} ) ) }
			selected={ selected }
			onSelectionChange={ onChange }
			includeDescendants
			hideNode={ ( node ) => node.item_type === 'topic' }
			title={ __( 'Curriculum mindmap', 'ohmylms' ) }
			rootLabel={ __( 'Curriculum', 'ohmylms' ) }
			description={ __(
				'Select a curriculum item to include its whole branch. Included child items are highlighted. The course preview below applies your exclusions.',
				'ohmylms'
			) }
			emptyMessage={
				emptyMessage ??
				__(
					'No curriculum items yet. Add them from the Curriculum menu to see them here.',
					'ohmylms'
				)
			}
			nodeHint={ ( node, { explicit, inherited } ) =>
				( () => {
					if ( inherited ) {
						return __( 'Included through parent', 'ohmylms' );
					}
					if ( explicit ) {
						return __( 'Selected item', 'ohmylms' );
					}
					return __( 'Select this branch', 'ohmylms' );
				} )()
			}
			renderNodeDetails={ ( node ) =>
				!! node.courses?.length && (
					<details className="ohmylms-mindmap-node-details">
						<summary>
							{ sprintf(
								/* translators: %d: number of courses. */ _n(
									'%d course linked to this item',
									'%d courses linked to this item',
									node.courses.length,
									'ohmylms'
								),
								node.courses.length
							) }
						</summary>
						<ul>
							{ node.courses.map( ( course ) => (
								<li key={ course.id }>{ course.title }</li>
							) ) }
						</ul>
					</details>
				)
			}
		/>
	);
}
