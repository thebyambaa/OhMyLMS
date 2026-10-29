import { createContext, useContext } from '@wordpress/element';
import type { ReactNode } from 'react';
import type { AppBootstrap } from './config';

const BootstrapContext = createContext< AppBootstrap | null >( null );

/**
 * Provides the validated bootstrap data to the tree.
 *
 * @param props          Component props.
 * @param props.value    Bootstrap data.
 * @param props.children Application tree.
 */
export function BootstrapProvider( {
	value,
	children,
}: {
	value: AppBootstrap;
	children: ReactNode;
} ) {
	return (
		<BootstrapContext.Provider value={ value }>
			{ children }
		</BootstrapContext.Provider>
	);
}

/**
 * Read the bootstrap data.
 *
 * @return Bootstrap data.
 * @throws {Error} When used outside a BootstrapProvider.
 */
export function useBootstrap(): AppBootstrap {
	const value = useContext( BootstrapContext );
	if ( null === value ) {
		throw new Error(
			'useBootstrap must be used inside BootstrapProvider.'
		);
	}
	return value;
}
