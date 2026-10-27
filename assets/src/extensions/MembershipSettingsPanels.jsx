import { createElement, useEffect, useState } from '@wordpress/element';
import { ExtensionBoundary } from './ExtensionBoundary';
export function MembershipSettingsPanels( { registry, plan, onChange } ) {
	const [ , update ] = useState( 0 );
	useEffect(
		() => registry.subscribe( () => update( ( n ) => n + 1 ) ),
		[ registry ]
	);
	const settings = plan?.extension_settings || {};
	return registry.list( 'membership-settings' ).map( ( entry ) => (
		<ExtensionBoundary key={ entry.id } id={ entry.id }>
			{ createElement( entry.render, {
				id: plan?.id,
				plan,
				value: settings[ entry.id ] || {},
				onChange: ( value ) =>
					onChange( 'extension_settings', {
						...settings,
						[ entry.id ]: value,
					} ),
			} ) }
		</ExtensionBoundary>
	) );
}
