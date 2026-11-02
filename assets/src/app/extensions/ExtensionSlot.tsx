import type { ComponentType } from 'react';
import type {
	ExtensionContext,
	ExtensionEntry,
	ExtensionKind,
} from './registry.mjs';
import { ExtensionBoundary } from './ExtensionBoundary';
import { useExtensions } from './context';

interface ExtensionSlotProps {
	/** Slot name; entries without a `slot` render in every slot of their kind. */
	name: string;
	kind?: ExtensionKind;
	context?: ExtensionContext;
	/** Render only this entry. */
	onlyId?: string;
}

/**
 * Renders the registered entries of one kind and slot, each in its own error boundary. Updates when
 * extensions register later.
 *
 * @param props         Component props.
 * @param props.name    Slot name.
 * @param props.kind    Extension category; defaults to `slot`.
 * @param props.context Props passed to every entry and to its `when` check.
 * @param props.onlyId  Restrict to one entry.
 */
export function ExtensionSlot( {
	name,
	kind = 'slot',
	context = {},
	onlyId,
}: ExtensionSlotProps ) {
	const entries = useExtensions( kind ).filter(
		( entry ) =>
			( ! onlyId || entry.id === onlyId ) &&
			( ! entry.slot || entry.slot === name )
	);
	return (
		<>
			{ entries.map( ( entry ) => (
				<ExtensionBoundary key={ entry.id } id={ entry.id }>
					<ExtensionView entry={ entry } context={ context } />
				</ExtensionBoundary>
			) ) }
		</>
	);
}

function ExtensionView( {
	entry,
	context,
}: {
	entry: ExtensionEntry;
	context: ExtensionContext;
} ) {
	if ( entry.when && ! entry.when( context ) ) {
		return null;
	}
	const Render = entry.render as ComponentType< ExtensionContext >;
	return <Render { ...context } />;
}
