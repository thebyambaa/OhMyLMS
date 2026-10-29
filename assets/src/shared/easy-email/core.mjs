import lodash from 'lodash';
import {
	ADVANCED_ORDER,
	AdvancedType,
	BasicType,
	STANDARD_ORDER,
} from './block-types.mjs';
import { createFooterBlocks } from './footer.mjs';
import { getHostVars, readHostSettings } from './host-settings.mjs';
import { createMjmlToJson } from './mjml-to-json.mjs';
import {
	createDefaults,
	translatedName,
	validParents,
} from './recovered-defaults.mjs';
import { createConditionTag, createJsonToMjml } from './render.mjs';

/**
 * Easy Email core for the OhMyLMS designer.
 *
 * The designer was built against a modified Easy Email core that was recovered from the
 * shipped bundle. `createCompatibleCore()` takes the published `easy-email-core` and
 * restores what that copy did, so emails saved before the move render the same. The
 * differences are listed in docs/EASY-EMAIL-AUDIT.md.
 */

const EXPORTED_FROM_PACKAGE = [
	'EMAIL_BLOCK_CLASS_NAME',
	'ImageManager',
	'MERGE_TAG_CLASS_NAME',
	'Operator',
	'OperatorSymbol',
	'TemplateEngineManager',
	'ancestorOf',
	'createBlock',
	'createBlockDataByType',
	'createCustomBlock',
	'getChildIdx',
	'getIndexByIdx',
	'getNodeIdxClassName',
	'getNodeIdxFromClassName',
	'getNodeTypeClassName',
	'getNodeTypeFromClassName',
	'getPageIdx',
	'getParenRelativeByType',
	'getParentByIdx',
	'getParentByType',
	'getParentIdx',
	'getPreviewClassName',
	'getSameParent',
	'getSiblingIdx',
	'getValidChildBlocks',
	'getValueByIdx',
	'isValidBlockData',
	'mergeBlock',
	'parseReactBlockToBlockData',
	'BlockManager',
];

/**
 * Social icon images, which the recovered core served from the plugin instead of a demo site.
 * @param ImageManager
 * @param host
 */
function restoreImages( ImageManager, host ) {
	Object.assign( ImageManager.map, {
		IMAGE_01: '',
		IMAGE_02: `${ host.imagesUrl }social-facebook.png`,
		IMAGE_03: `${ host.imagesUrl }social-instagram.png`,
		IMAGE_04: `${ host.imagesUrl }social-twitter.png`,
		IMAGE_59: '',
		IMAGE_09: '',
		IMAGE_10: '',
		IMAGE_15: '',
		IMAGE_16: '',
		IMAGE_17: '',
		IMAGE_31: '',
	} );
}

/**
 * Apply the recovered core's names, defaults and valid parents to the package's blocks.
 * @param core
 * @param host
 * @param getVars
 */
function restoreBlocks( core, host, getVars ) {
	const { standardBlocks, advancedBlocks, mergeBlock, ImageManager } = core;
	const defaults = createDefaults( {
		blocks: standardBlocks,
		images: ImageManager,
		host,
		getVars,
	} );
	const apply = {
		deep: ( base, payload ) => lodash.merge( base, payload ),
		'replace-arrays': mergeBlock,
	};
	for ( const [ type, { merge, create } ] of Object.entries( defaults ) ) {
		standardBlocks[ type ].create = ( payload ) =>
			apply[ merge ]( create(), payload );
	}
	for ( const block of [
		...Object.values( standardBlocks ),
		...Object.values( advancedBlocks ),
	] ) {
		const parents = validParents( block.type );
		if ( parents ) {
			block.validParentType = parents;
		}
		const name = translatedName( block.type, host );
		if ( name ) {
			Object.defineProperty( block, 'name', {
				value: name,
				configurable: true,
				enumerable: true,
				writable: true,
			} );
		}
	}
}

/**
 * Build the exports of the recovered core on top of the published package.
 * @param core
 * @param host
 * @param getMjml
 */
export function createCompatibleCore(
	core,
	host = readHostSettings(),
	getMjml = () => globalThis.window?.ohmylmsEmailPackages?.mjml
) {
	const getVars = getHostVars;
	restoreImages( core.ImageManager, host );
	restoreBlocks( core, host, getVars );

	const { footer, advancedFooter } = createFooterBlocks( {
		core,
		hero: core.standardBlocks[ BasicType.HERO ],
		getVars,
		merge: core.mergeBlock,
		deepMerge: lodash.merge,
	} );
	const pick = ( blocks, order, extra ) =>
		Object.fromEntries(
			order.map( ( type ) => [ type, extra[ type ] || blocks[ type ] ] )
		);
	const standardBlocks = pick( core.standardBlocks, STANDARD_ORDER, {
		[ BasicType.FOOTER ]: footer,
	} );
	const advancedBlocks = pick( core.advancedBlocks, ADVANCED_ORDER, {
		[ AdvancedType.FOOTER ]: advancedFooter,
	} );

	// Register in the recovered order. This also drops advanced_table, which the recovered
	// core never had.
	const { BlockManager, TemplateEngineManager, Operator } = core;
	BlockManager.blocksMap = {};
	BlockManager.registerBlocks( { ...standardBlocks, ...advancedBlocks } );

	TemplateEngineManager.setTag( {
		name: 'condition',
		templateGenerateFn: createConditionTag( {
			Operator,
			components: core.components,
		} ),
	} );

	const components = Object.fromEntries(
		Object.entries( core.components ).filter(
			( [ name ] ) => 'BlockRenderer' !== name
		)
	);
	const exports = Object.fromEntries(
		EXPORTED_FROM_PACKAGE.map( ( name ) => [ name, core[ name ] ] )
	);
	return {
		...exports,
		AdvancedType,
		BasicType,
		advancedBlocks,
		standardBlocks,
		components,
		JsonToMjml: createJsonToMjml( core.JsonToMjml ),
		MjmlToJson: createMjmlToJson( BlockManager, getMjml ),
		getAdapterAttributesString: recoveredAttributes( core ),
		isAdvancedBlock: ( type ) =>
			Object.values( AdvancedType ).includes( type ),
	};
}

/**
 * Attribute string like the recovered core's: boolean attributes are not printed.
 * The package's blocks use their own copy of this function, which is why
 * JsonToMjml removes boolean attributes before rendering.
 * @param core
 */
function recoveredAttributes( core ) {
	return function getAdapterAttributesString( params ) {
		const withoutBooleans = {
			...params,
			data: {
				...params.data,
				attributes: Object.fromEntries(
					Object.entries( params.data.attributes ).filter(
						( [ , value ] ) => 'boolean' !== typeof value
					)
				),
			},
		};
		return core.getAdapterAttributesString( withoutBooleans );
	};
}
