import { NavLink } from 'react-router-dom';
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { FeatureDefinition } from '../features/types';

/**
 * Primary navigation. `NavLink` sets `aria-current="page"` on the active link.
 *
 * @param props          Component props.
 * @param props.features Features to link to, already filtered by capability.
 */
export function Navigation( {
	features,
}: {
	features: readonly FeatureDefinition[];
} ) {
	return (
		<nav aria-label={ __( 'Main', 'ohmylms' ) } className="ohmylms-app-nav">
			<ul>
				{ features.map( ( feature ) => (
					<li key={ feature.id }>
						<NavLink
							to={ feature.path }
							end={ '/' === feature.path }
						>
							{ feature.icon && (
								<Icon icon={ feature.icon } size={ 20 } />
							) }
							<span>{ feature.title }</span>
						</NavLink>
					</li>
				) ) }
			</ul>
		</nav>
	);
}
