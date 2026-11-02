import type { ComponentType } from 'react';
import { useParams } from 'react-router-dom';
import { ExtensionBoundary } from '../../extensions/ExtensionBoundary';
import { useExtensionRegistry, useExtensions } from '../../extensions/context';
import { NotFound } from '../../layout/NotFound';

/** Renders the admin page an extension registered under the `:id` route param. */
export default function ExtensionPage() {
	const { id = '' } = useParams();
	const registry = useExtensionRegistry();
	useExtensions( 'admin-page' );
	const entry = registry.get( 'admin-page', id );
	if ( ! entry?.enabled ) {
		return <NotFound homeTo="/" />;
	}
	const Render = entry.render as ComponentType;
	return (
		<ExtensionBoundary key={ entry.id } id={ entry.id }>
			<Render />
		</ExtensionBoundary>
	);
}
