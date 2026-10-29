import traverseModule from '@babel/traverse';
import { numericLiteral, objectProperty } from '@babel/types';
import { deferredEmailReferences } from './email-runtime-adapters.mjs';

const traverse = traverseModule.default || traverseModule;
export const emailVendorModules = [
	{ id: 3270, library: 'Arco Design React' },
	{ id: 50587, library: 'html2canvas' },
	{ id: 44098, library: 'MJML renderer' },
	{ id: 47909, library: 'Email designer UI' },
	{ id: 58088, library: 'Email editor core' },
	{ id: 43203, library: 'Email editor exports' },
	{ id: 17190, library: 'Email source editor' },
];
export const emailVendorSource = 'assets/dist/vendors/vendors.js';
export const emailVendorTarget = 'assets/dist/chunks/355.js';

/** Refuse relocation if any recovered source outside the email chunk uses these IDs. */
export function auditEmailVendors( manifest, readSource ) {
	const source = manifest.assets.find(
		( asset ) => asset.output === emailVendorSource
	);
	const target = manifest.assets.find(
		( asset ) => asset.output === emailVendorTarget
	);
	const relocatedSources = new Set(
		source.factories
			.filter( ( factory ) =>
				emailVendorModules.some(
					( module ) => Number( factory.id ) === module.id
				)
			)
			.map( ( factory ) => factory.source )
	);
	if ( ! source || ! target )
		throw new Error( 'Email vendor bundle mapping is missing' );
	return emailVendorModules.map( ( module ) => {
		const factory = source.factories.find(
			( item ) => Number( item.id ) === module.id
		);
		if ( ! factory )
			throw new Error( `Missing email vendor factory ${ module.id }` );
		const references = [];
		// Scan literals conservatively, including dynamic-import IDs and context maps.
		// An unrelated occurrence blocks the build instead of risking an early import.
		const pattern = new RegExp( `\\b${ module.id }\\b` );
		for ( const asset of manifest.assets ) {
			if (
				asset.source.endsWith( '.js' ) &&
				! [ emailVendorSource, emailVendorTarget ].includes(
					asset.output
				) &&
				pattern.test( readSource( asset.source ) )
			) {
				throw new Error(
					`Email vendor ${ module.id } is needed outside the lazy email chunk: ${ asset.source }`
				);
			}
			for ( const item of asset.factories || [] ) {
				for ( const name of item.fragments || [ item.source ] ) {
					if (
						name === factory.source ||
						! pattern.test( readSource( name ) )
					)
						continue;
					const deferred =
						deferredEmailReferences[ module.id ]?.includes( name );
					if (
						deferred &&
						(
							readSource( name ).match(
								new RegExp( `\\b${ module.id }\\b`, 'g' )
							) || []
						).length !== 1
					)
						throw new Error(
							`Unexpected eager email references in ${ name }`
						);
					if (
						asset.output !== emailVendorTarget &&
						! relocatedSources.has( name ) &&
						! deferred
					) {
						throw new Error(
							`Email vendor ${ module.id } is needed outside the lazy email chunk: ${ name }`
						);
					}
					references.push( name );
				}
			}
		}
		if ( ! references.length )
			throw new Error(
				`Email vendor ${ module.id } has no email consumers`
			);
		return { ...module, source: factory.source, references };
	} );
}

/** Move factory registration only; preserve the functions and the existing chunk loader. */
export function relocateEmailVendors( ast, output, factories ) {
	if ( ! [ emailVendorSource, emailVendorTarget ].includes( output ) ) return;
	let tables = 0;
	traverse( ast, {
		ObjectExpression( table ) {
			const properties = table.node.properties;
			if (
				! properties.length ||
				! properties.every(
					( property ) =>
						property.type === 'ObjectProperty' &&
						property.key.type === 'NumericLiteral' &&
						[
							'ArrowFunctionExpression',
							'FunctionExpression',
						].includes( property.value.type )
				)
			)
				return;
			tables++;
			for ( const [ id, factory ] of factories ) {
				const position = properties.findIndex(
					( property ) => property.key.value === id
				);
				if ( output === emailVendorSource ) {
					if ( position < 0 )
						throw new Error( `Missing shared vendor ${ id }` );
					properties.splice( position, 1 );
				} else {
					if ( position >= 0 )
						throw new Error( `Duplicate lazy vendor ${ id }` );
					properties.push(
						objectProperty( numericLiteral( id ), factory )
					);
				}
			}
			table.skip();
		},
	} );
	if ( tables !== 1 )
		throw new Error( `Expected one vendor factory table in ${ output }` );
}
