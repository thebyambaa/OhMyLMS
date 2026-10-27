import { createElement, useEffect, useState } from '@wordpress/element';
import { ExtensionBoundary } from './ExtensionBoundary';
export function ExtensionSlot( {
	registry,
	name,
	kind = 'slot',
	context = {},
	onlyId,
} ) {
	const [ , update ] = useState( registry.getRevision() );
	useEffect(
		() => registry.subscribe( () => update( registry.getRevision() ) ),
		[ registry ]
	);
	return registry
		.list( kind )
		.filter(
			( item ) =>
				( ! onlyId || item.id === onlyId ) &&
				( ! item.slot || item.slot === name )
		)
		.map( ( item ) => (
			<ExtensionBoundary key={ item.id } id={ item.id }>
				<ExtensionEntry item={ item } context={ context } />
			</ExtensionBoundary>
		) );
}
function ExtensionEntry( { item, context } ) {
	if ( item.when && ! item.when( context ) ) {
		return null;
	}
	return createElement( item.render, context );
}
