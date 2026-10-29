import { parse, parseExpression } from '@babel/parser';
import traverseModule from '@babel/traverse';
const traverse = traverseModule.default || traverseModule;

export const vendorPackages = {
	'assets/dist/vendors/chartjs.js': {
		60068: {
			group: 'charts',
			names: {
				PP: 'CategoryScale',
				t1: 'Chart',
				s$: 'Legend',
				ZT: 'LineController',
				No: 'LineElement',
				kc: 'LinearScale',
				FN: 'PointElement',
				hE: 'Title',
				m_: 'Tooltip',
			},
		},
		44731: { group: 'charts', names: { N1: 'Line' } },
	},
	'assets/dist/vendors/emotion.js': {
		11456: {
			group: 'emotion',
			names: { AH: 'css', Y: 'jsx', i7: 'keyframes' },
		},
		51893: { group: 'emotion', names: { A: 'createCache' } },
	},
};
// These factories only serve the replaced Chart.js/Emotion implementations.
export const retiredVendorPackages = [
	46447, 40390, 19735, 24534, 50483, 49503, 73716,
];

export function auditVendorPackages( manifest, readSource ) {
	for ( const [ output, mappings ] of Object.entries( vendorPackages ) ) {
		const asset = manifest.assets.find(
			( item ) => item.output === output
		);
		if (
			! asset ||
			asset.factories.length !== Object.keys( mappings ).length ||
			asset.factories.some( ( factory ) => ! mappings[ factory.id ] )
		)
			throw new Error( `Unmapped vendor package in ${ output }` );
	}
	const allowed = new Set( [ ...retiredVendorPackages, 60068, 51893 ] );
	const retired = new Set( retiredVendorPackages );
	for ( const asset of manifest.assets ) {
		const sources = [
			{ source: asset.source },
			...( asset.factories || [] ),
		];
		for ( const factory of sources ) {
			if ( allowed.has( Number( factory.id ) ) ) continue;
			for ( const source of factory.fragments || [ factory.source ] ) {
				const code = readSource( source );
				if (
					! retiredVendorPackages.some( ( id ) =>
						new RegExp( `\\b${ id }\\b` ).test( code )
					)
				)
					continue;
				traverse( parse( code, { sourceType: 'unambiguous' } ), {
					NumericLiteral( literal ) {
						if ( ! retired.has( literal.node.value ) ) return;
						// Factory declarations in runtime templates are removed by the adapter.
						if (
							literal.parentPath.isObjectProperty() &&
							literal.key === 'key' &&
							literal.parentPath.node.value.name?.startsWith(
								'__OHMYLMS_FACTORY_'
							)
						)
							return;
						throw new Error(
							`Retired vendor ${ literal.node.value } is consumed by ${ source }`
						);
					},
				} );
			}
		}
	}
}

export function adaptVendorPackages( ast, output ) {
	const mappings = vendorPackages[ output ];
	const removals =
		output === 'assets/dist/vendors/vendors.js'
			? retiredVendorPackages
			: [];
	if ( ! mappings && ! removals.length ) return;
	let replaced = 0;
	let removed = 0;
	traverse( ast, {
		ObjectProperty( factory ) {
			if (
				! [ 'FunctionExpression', 'ArrowFunctionExpression' ].includes(
					factory.node.value.type
				)
			)
				return;
			const id = factory.node.key.value;
			if ( mappings?.[ id ] ) {
				const { group, names } = mappings[ id ];
				factory.node.value =
					parseExpression( `(module,exports,require)=>{
					const runtime=window.ohmylmsVendorPackages?.[${ JSON.stringify( group ) }];
					if(!runtime)throw new Error('OhMyLMS npm ${ group } runtime must load before vendor consumers');
					require.d(exports,Object.fromEntries(Object.entries(${ JSON.stringify( names ) }).map(([key,name])=>{
						if(!(name in runtime))throw new Error('Missing npm vendor export '+name);
						return [key,()=>runtime[name]];
					})));
				}` );
				replaced++;
				factory.skip();
			} else if ( removals.includes( id ) ) {
				factory.remove();
				removed++;
			}
		},
	} );
	if (
		replaced !== Object.keys( mappings || {} ).length ||
		removed !== removals.length
	)
		throw new Error( `Vendor package layout changed: ${ output }` );
}
