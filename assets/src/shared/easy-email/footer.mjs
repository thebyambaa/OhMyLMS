import { createElement, Fragment } from '@wordpress/element';
import { AdvancedType, BasicType } from './block-types.mjs';
import {
	FOOTER_ICON_FIRST,
	FOOTER_ICON_SECOND,
	FOOTER_ICON_THIRD,
} from './footer-icons.mjs';

/**
 * Footer blocks. The published core has none: it renders a saved `footer` or
 * `advanced_footer` as nothing, while the recovered core renders both as an `mj-hero`.
 * These are the recovered definitions, built on the package's own hero block.
 */

const joinClasses = ( ...names ) =>
	names.filter( ( name ) => 'string' === typeof name ).join( ' ' );

function footerContent( getVars ) {
	const vars = getVars();
	const name = vars?.business_basic_settings?.business_name
		? vars.business_basic_settings.business_name
		: '{{business.name}}';
	const address = vars?.address ? vars.address : '{{business.address}}';
	return { name, address };
}

function createFooterDefaults( getVars ) {
	const { name, address } = footerContent( getVars );
	const text = ( content, attributes ) => ( {
		type: AdvancedType.TEXT,
		data: { value: { content } },
		attributes,
		children: [],
	} );
	return {
		type: BasicType.FOOTER,
		data: { value: {} },
		attributes: {
			'background-color': '#ffffff',
			'background-position': 'center center',
			mode: 'fluid-height',
			padding: '10px 0px 10px 0px',
			'vertical-align': 'top',
			'background-url': '',
		},
		children: [
			{
				type: AdvancedType.SOCIAL,
				data: {
					value: {
						elements: [
							FOOTER_ICON_FIRST,
							FOOTER_ICON_SECOND,
							FOOTER_ICON_THIRD,
						].map( ( src ) => ( {
							href: '#',
							target: '_blank',
							src,
							content: '',
						} ) ),
					},
				},
				attributes: {
					align: 'center',
					color: '#333333',
					mode: 'horizontal',
					'font-size': '13px',
					'font-weight': 'normal',
					'font-style': 'normal',
					'font-family': 'Arial',
					'border-radius': '3px',
					padding: '15px 0px 15px 0px',
					'inner-padding': '0px 20px 0px 0px',
					'line-height': '1.6',
					'text-padding': '4px 4px 4px 0px',
					'icon-padding': '0px',
					'icon-size': '25px',
				},
				children: [],
			},
			{
				type: AdvancedType.DIVIDER,
				data: { value: {} },
				attributes: {
					align: 'center',
					'border-width': '1px',
					'border-style': 'solid',
					'border-color': '#D3CFD8',
					padding: '0px 24px 15px 24px',
				},
				children: [],
			},
			text( name, {
				padding: '10px 25px 10px 25px',
				align: 'center',
				'font-size': '16px',
				'line-height': '15px',
				'font-weight': '500',
			} ),
			text( '© 2024 ' + name + ' ' + address, {
				padding: '12px 0px 12px 0px',
				align: 'center',
				'font-size': '14px',
				'line-height': '20px',
				color: '#908A99',
			} ),
			text(
				'<a href="{{link.preference}}" target="_blank" style="text-decoration: underline;"><font color="#0064ff">Update Preference</font></a> . <a href="{{link.unsubscribe}}" target="_blank" style="text-decoration: underline;"><font color="#0064ff">Unsubscribe</font></a>',
				{
					padding: '10px 25px 10px 25px',
					align: 'center',
					'font-size': '10px',
				}
			),
		],
	};
}

/**
 * Create the basic and advanced footer blocks.
 *
 * @param {Object}                            options
 * @param {Object}                            options.core      The published core.
 * @param {Object}                            options.hero      The published core's hero block, which draws `mj-hero`.
 * @param {() => Object}                      options.getVars   Returns the live host variables.
 * @param {( ...args: unknown[] ) => unknown} options.merge     Applies a payload like the core's mergeBlock.
 * @param {( ...args: unknown[] ) => unknown} options.deepMerge Lodash merge.
 */
export function createFooterBlocks( {
	core,
	hero,
	getVars,
	merge,
	deepMerge,
} ) {
	const { TemplateEngineManager, createBlock, components } = core;
	const footer = createBlock( {
		name: 'Footer',
		type: BasicType.FOOTER,
		create: ( payload ) =>
			merge( createFooterDefaults( getVars ), payload ),
		validParentType: [ BasicType.PAGE, BasicType.WRAPPER ],
		render: ( params ) => hero.render( params ),
	} );

	const advancedFooter = createBlock( {
		get name() {
			return footer.name;
		},
		type: AdvancedType.FOOTER,
		validParentType: [
			BasicType.WRAPPER,
			AdvancedType.WRAPPER,
			BasicType.PAGE,
		],
		create: ( payload ) =>
			deepMerge(
				{ ...footer.create(), type: AdvancedType.FOOTER },
				payload
			),
		render: ( params ) => {
			const { data, idx, mode } = params;
			const { iteration, condition } = data.data.value;
			const draw = ( index ) => {
				const className =
					'testing' === mode
						? joinClasses(
								0 === index &&
									idx &&
									core.getPreviewClassName( idx, data.type )
							)
						: '';
				const blockData = { ...data, type: BasicType.FOOTER };
				return createElement(
					components.BlockRenderer,
					{
						idx: null,
						data: {
							...blockData,
							attributes: {
								...blockData.attributes,
								'css-class': joinClasses(
									data.attributes[ 'css-class' ],
									className
								),
							},
						},
					},
					blockData.children.map( ( child, position ) =>
						createElement( components.BlockRenderer, {
							key: position,
							...params,
							index,
							data: child,
							idx: idx ? core.getChildIdx( idx, position ) : null,
						} )
					)
				);
			};
			let children = draw( 0 );
			if ( 'testing' === mode ) {
				return createElement(
					Fragment,
					null,
					createElement( Fragment, { key: 'children' }, children ),
					new Array( ( iteration?.mockQuantity || 1 ) - 1 )
						.fill( true )
						.map( ( _, index ) =>
							createElement(
								Fragment,
								{ key: index },
								draw( index + 1 )
							)
						)
				);
			}
			if ( condition?.enabled ) {
				children = TemplateEngineManager.generateTagTemplate(
					'condition'
				)( condition, children );
			}
			if ( iteration?.enabled ) {
				children = TemplateEngineManager.generateTagTemplate(
					'iteration'
				)( iteration, children );
			}
			return children;
		},
	} );
	return { footer, advancedFooter };
}
