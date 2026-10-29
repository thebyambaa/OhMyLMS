import * as textAlignPackage from '@tiptap/extension-text-align';
import * as tablePackage from '@tiptap/extension-table';

// Keep the stored alignment default and omit its redundant HTML style.
const alignedText = textAlignPackage.default
	.extend( {
		addGlobalAttributes() {
			return this.parent().map( ( group ) => ( {
				...group,
				attributes: {
					...group.attributes,
					textAlign: {
						...group.attributes.textAlign,
						renderHTML: ( attrs ) =>
							attrs.textAlign === this.options.defaultAlignment
								? {}
								: { style: `text-align: ${ attrs.textAlign }` },
					},
				},
			} ) );
		},
	} )
	.configure( { defaultAlignment: 'left' } );

// Older saved tables omit minimum widths on individual unsized columns.
const compatibleTable = tablePackage.default.extend( {
	renderHTML( props ) {
		const output = this.parent( props );
		const table = output[ 0 ] === 'table' ? output : output[ 2 ];
		const colgroup = table[ 2 ];
		if ( Array.isArray( colgroup ) ) {
			for ( const column of colgroup.slice( 2 ) ) {
				if ( column[ 1 ]?.style?.startsWith( 'min-width:' ) ) {
					column[ 1 ] = {};
				}
			}
		}
		return output;
	},
} );

export const textAlign = {
	...textAlignPackage,
	default: alignedText,
	TextAlign: alignedText,
};
export const table = {
	...tablePackage,
	default: compatibleTable,
	Table: compatibleTable,
};
