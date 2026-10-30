import { NavLink } from 'react-router-dom';
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useExtensions } from '../extensions/context';
import type { FeatureDefinition } from '../features/types';

/**
 * Primary navigation: features, then pages registered by extensions (`/extensions/<id>`).
 * `NavLink` sets `aria-current="page"` on the active link.
 *
 * @param props          Component props.
 * @param props.features Features to link to, already filtered by capability.
 */
export function Navigation( {
	features,
}: {
	features: readonly FeatureDefinition[];
} ) {
	const pages = useExtensions( 'admin-page' );
	return (
		<nav aria-label={ __( 'Main', 'ohmylms' ) } className="ohmylms-app-nav">
			<ul>
				{ features
					.filter( ( feature ) => ! feature.hidden )
					.map( ( feature ) => (
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
				{ pages.map( ( page ) => (
					<li key={ `extension-${ page.id }` }>
						<NavLink to={ `/extensions/${ page.id }` }>
							<span>{ page.label }</span>
						</NavLink>
					</li>
				) ) }
			</ul>
		</nav>
	);
}
