import { Component, createElement, lazy, Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { createFeatureLoader } from './featureLoader.mjs';
const factoryRuntimes = new Map();
export const getFactoryRuntime = ( registry, name ) =>
	factoryRuntimes.get( `${ registry }:${ name }` )?.();

class FeatureBoundary extends Component {
	state = { failed: false };
	static getDerivedStateFromError() {
		return { failed: true };
	}
	render() {
		return this.state.failed ? (
			<div role="alert">
				<p>
					{ __(
						'This screen could not be loaded. Reload to try again.',
						'ohmylms'
					) }
				</p>
				<button
					type="button"
					onClick={ () => window.location.reload() }
				>
					{ __( 'Reload', 'ohmylms' ) }
				</button>
			</div>
		) : (
			this.props.children
		);
	}
}

function wrapLazy( loadComponent, name ) {
	const View = lazy( async () => {
		const [ component ] = await Promise.all( [
			loadComponent(),
			globalThis.ohmylmsStyles?.ensure( name ),
		] );
		return { default: component };
	} );
	function LazyFeature( props ) {
		return (
			<FeatureBoundary>
				<Suspense
					fallback={
						<span role="status">
							{ __( 'Loading…', 'ohmylms' ) }
						</span>
					}
				>
					<View { ...props } />
				</Suspense>
			</FeatureBoundary>
		);
	}
	LazyFeature.displayName = `Lazy(${ name })`;
	return LazyFeature;
}

/**
 * Preserve synchronous factory registration and defer runtime reads until rendering.
 * @param manifest
 * @param importFeature
 * @param registryName
 */
export function lazyFactories( manifest, importFeature, registryName ) {
	const load = createFeatureLoader( importFeature, registryName );
	return Object.fromEntries(
		manifest.map( ( { name } ) => [
			name,
			( readRuntime ) => {
				factoryRuntimes.set(
					`${ registryName }:${ name }`,
					readRuntime
				);
				return wrapLazy(
					async () => ( await load() )[ name ]( readRuntime ),
					name
				);
			},
		] )
	);
}

export function lazyComponents( names, importFeature, registryName ) {
	const load = createFeatureLoader( importFeature, registryName );
	return Object.fromEntries(
		names.map( ( name ) => [
			name,
			wrapLazy( async () => ( await load() )[ name ], name ),
		] )
	);
}

/**
 * Keep recovered template conversion synchronous once its libraries are ready.
 * @param {import('react').ComponentType} Screen        Recovered template component.
 * @param {() => Promise<unknown>}        loadLibraries Load the matching editor chunk.
 * @return {import('react').ComponentType} Component with loading and failure handling.
 */
export function lazyEmailComponent( Screen, loadLibraries ) {
	return wrapLazy( async () => {
		await Promise.all( [
			loadLibraries(),
			globalThis.ohmylmsStyles?.ensure( 'EmailTemplate' ),
		] );
		return Screen;
	}, 'EmailTemplate' );
}
