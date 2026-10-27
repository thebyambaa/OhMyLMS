import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { MovableTabs } from './MovableTabs';

/**
 * The same navigation for screens whose tabs select a panel rather than a hash route.
 * @param root0
 * @param root0.scope
 * @param root0.items
 * @param root0.activekey
 * @param root0.onChange
 * @param root0.variant
 * @param root0.className
 */
export function MovableTabPanel( {
	scope,
	items,
	activekey,
	onChange,
	variant,
	className = '',
} ) {
	const active = String( activekey ?? items[ 0 ]?.key ?? '' );
	const item = items.find( ( entry ) => String( entry.key ) === active );
	return (
		<div
			className={ `ohmylms-movable-tab-panel ${ variant === 'vertical' ? 'is-vertical' : '' } ${ className }` }
		>
			<MovableTabs
				orientation={
					variant === 'vertical' ? 'vertical' : 'horizontal'
				}
				scope={ scope }
				tabs={ items.map( ( entry ) => ( {
					id: String( entry.key ),
				} ) ) }
				labels={ Object.fromEntries(
					items.map( ( entry ) => [
						String( entry.key ),
						entry.label,
					] )
				) }
				active={ active }
				onChange={ ( id ) =>
					onChange(
						items.find( ( entry ) => String( entry.key ) === id )
							.key
					)
				}
				label={ __( 'Sections', 'ohmylms' ) }
			/>
			<div className="ohmylms-tab-panel-body" key={ active }>
				{ item?.children }
			</div>
		</div>
	);
}
