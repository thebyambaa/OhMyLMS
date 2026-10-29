import { BasicType, VALID_PARENT_TYPES } from './block-types.mjs';

/**
 * What the recovered core did differently from the published one when a block is created
 * in the designer: default values, labels and the list of valid parents. Saved emails carry
 * their own values, so these only decide how new blocks look.
 */

const SOCIAL_NAMES = {
	'https://www.facebook.com/': 'Facebook',
	'https://facebook.com/': 'Facebook',
	'https://www.linkedin.com/': 'LinkedIn',
	'https://linkedin.com/': 'LinkedIn',
	'https://www.instagram.com/': 'Instagram',
	'https://instagram.com/': 'Instagram',
	'https://twitter.com/': 'Twitter',
	'https://www.twitter.com/': 'Twitter',
	'https://x.com/': 'Twitter',
	'https://www.x.com/': 'Twitter',
};

/** Block names that the host page may translate through `MRM_Vars.mint_trans`. */
const TRANSLATED_NAMES = {
	[ BasicType.WRAPPER ]: 'Wrapper',
	[ BasicType.SECTION ]: 'Section',
	[ BasicType.COLUMN ]: 'Column',
	[ BasicType.TEXT ]: 'Text',
	[ BasicType.IMAGE ]: 'Image',
	[ BasicType.GROUP ]: 'Group',
	[ BasicType.BUTTON ]: 'Button',
	[ BasicType.DIVIDER ]: 'Divider',
	[ BasicType.SPACER ]: 'Spacer',
	[ BasicType.HERO ]: 'Hero',
	[ BasicType.NAVBAR ]: 'Navbar',
	[ BasicType.SOCIAL ]: 'Social',
	[ BasicType.RAW ]: 'Raw',
	[ BasicType.TEMPLATE ]: 'Template',
	[ BasicType.TABLE ]: 'Table',
};

function socialName( url ) {
	for ( const prefix in SOCIAL_NAMES ) {
		if ( url.startsWith( prefix ) ) {
			return SOCIAL_NAMES[ prefix ];
		}
	}
	return 'Social Media';
}

/**
 * Full default value of every block whose defaults differ from the published core.
 * Each entry returns a fresh object. `merge` says how a caller's payload is applied:
 * 'deep' is lodash merge, 'replace-arrays' is the core's mergeBlock.
 * @param root0
 * @param root0.blocks
 * @param root0.images
 * @param root0.host
 * @param root0.getVars
 */
export function createDefaults( { blocks, images, host, getVars } ) {
	const social = host.socialMedia;
	const socialElements = () =>
		Array.isArray( social ) && social.length > 0
			? social.map( ( item ) => ( {
					href: item.url,
					target: '_blank',
					src: item.icon,
					content: socialName( item.url ),
				} ) )
			: [
					[ 'IMAGE_02', 'Facebook' ],
					[ 'IMAGE_03', 'Instagram' ],
					[ 'IMAGE_04', 'Twitter' ],
				].map( ( [ image, content ] ) => ( {
					href: '#',
					target: '_blank',
					src: images.get( image ),
					content,
				} ) );
	const wrapper = () => ( {
		type: BasicType.WRAPPER,
		data: { value: {} },
		attributes: {
			'background-color': '#ffffff',
			padding: '20px 0px 20px 0px',
			border: 'none',
			direction: 'ltr',
			'text-align': 'center',
		},
		children: [],
	} );
	return {
		[ BasicType.PAGE ]: {
			merge: 'deep',
			create: () => ( {
				type: BasicType.PAGE,
				data: {
					value: {
						breakpoint: '480px',
						headAttributes: '',
						'font-size': '14px',
						'font-weight': '400',
						'line-height': '1.7',
						headStyles: [],
						fonts: [],
						responsive: true,
						'font-family': 'Arial',
						'text-color': '#000000',
					},
				},
				attributes: {
					'background-color': '#efeeea',
					width: '600px',
				},
				children: [ blocks[ BasicType.WRAPPER ].create() ],
			} ),
		},
		[ BasicType.WRAPPER ]: { merge: 'deep', create: wrapper },
		[ BasicType.TEXT ]: {
			merge: 'replace-arrays',
			create: () => ( {
				type: BasicType.TEXT,
				data: {
					value: {
						content:
							getVars()?.mint_trans?.text_block_content ||
							'Make it easy for everyone to compose emails!',
					},
				},
				attributes: {
					padding: '10px 25px 10px 25px',
					align: host.rtl ? 'right' : 'left',
				},
				children: [],
			} ),
		},
		[ BasicType.SECTION ]: {
			merge: 'deep',
			create: () => ( {
				type: BasicType.SECTION,
				data: { value: { noWrap: false } },
				attributes: {
					'background-color': '#ffffff',
					padding: '20px 0px 20px 0px',
					'background-repeat': 'repeat',
					'background-size': 'auto',
					'background-position': 'top center',
					border: 'none',
					direction: 'ltr',
					'text-align': 'center',
				},
				children: [],
			} ),
		},
		[ BasicType.COLUMN ]: {
			merge: 'deep',
			create: () => ( {
				type: BasicType.COLUMN,
				data: { value: {} },
				attributes: {
					'background-color': '#ffffff',
					padding: '0px 0px 0px 0px',
					border: 'none',
					'vertical-align': 'top',
				},
				children: [],
			} ),
		},
		[ BasicType.BUTTON ]: {
			merge: 'deep',
			create: () => ( {
				type: BasicType.BUTTON,
				data: {
					value: {
						content: getVars()?.mint_trans?.Button || 'Button',
					},
				},
				attributes: {
					align: 'center',
					'font-family': 'Arial',
					'background-color': '#414141',
					color: '#ffffff',
					'font-weight': 'normal',
					'font-style': 'normal',
					'border-radius': '3px',
					padding: '10px 25px 10px 25px',
					'inner-padding': '10px 25px 10px 25px',
					'font-size': '13px',
					'line-height': '1.2',
					target: '_blank',
					'vertical-align': 'middle',
					border: 'none',
					'text-align': 'center',
					'letter-spacing': 'normal',
					href: '#',
				},
				children: [],
			} ),
		},
		[ BasicType.HERO ]: {
			merge: 'replace-arrays',
			create: () => ( {
				type: BasicType.HERO,
				data: { value: {} },
				attributes: {
					'background-color': '#ffffff',
					'background-position': 'center center',
					mode: 'fluid-height',
					padding: '100px 0px 100px 0px',
					'vertical-align': 'top',
					'background-url': images.get( 'IMAGE_31' ),
				},
				children: [
					{
						type: 'text',
						data: { value: { content: 'Hero Text' } },
						attributes: {
							padding: '10px 25px 10px 25px',
							align: 'center',
							color: '#000000',
							'font-size': '45px',
							'line-height': '45px',
						},
						children: [],
					},
					{
						type: 'text',
						data: { value: { content: 'Hero content' } },
						attributes: {
							align: 'center',
							'background-color': '#414141',
							color: '#000000',
							'font-weight': 'normal',
							'border-radius': '3px',
							padding: '10px 25px 10px 25px',
							'inner-padding': '10px 25px 10px 25px',
							'line-height': '1.5',
							target: '_blank',
							'vertical-align': 'middle',
							border: 'none',
							'text-align': 'center',
							href: '#',
							'font-size': '14px',
						},
						children: [],
					},
					{
						type: 'button',
						data: { value: { content: 'Get Your Order Here!' } },
						attributes: {
							align: 'center',
							'background-color': '#573BFF',
							color: '#ffffff',
							'font-size': '13px',
							'font-weight': 'normal',
							'border-radius': '30px',
							padding: '10px 25px 10px 25px',
							'inner-padding': '10px 25px 10px 25px',
							'line-height': '120%',
							target: '_blank',
							'vertical-align': 'middle',
							border: 'none',
							'text-align': 'center',
							href: '#',
						},
						children: [],
					},
				],
			} ),
		},
		[ BasicType.CAROUSEL ]: {
			merge: 'replace-arrays',
			create: () => ( {
				type: BasicType.CAROUSEL,
				data: {
					value: {
						images: [ 'IMAGE_15', 'IMAGE_16', 'IMAGE_17' ].map(
							( image ) => ( {
								src: images.get( image ),
								target: '_blank',
							} )
						),
					},
				},
				attributes: {
					align: 'center',
					'left-icon': '',
					'right-icon': '',
					'icon-width': '44px',
					thumbnails: 'visible',
				},
				children: [],
			} ),
		},
		[ BasicType.SOCIAL ]: {
			merge: 'replace-arrays',
			create: () => ( {
				type: BasicType.SOCIAL,
				data: { value: { elements: socialElements() } },
				attributes: {
					align: 'center',
					color: '#333333',
					mode: 'horizontal',
					'font-size': '13px',
					'font-weight': 'normal',
					'font-style': 'normal',
					'font-family': 'Arial',
					'border-radius': '3px',
					padding: '10px 25px 10px 25px',
					'inner-padding': '4px 4px 4px 4px',
					'line-height': '1.6',
					'text-padding': '4px 4px 4px 0px',
					'icon-padding': '0px',
					'icon-size': '20px',
				},
				children: [],
			} ),
		},
	};
}

/**
 * Label of a block, as the recovered core resolved it when it loaded.
 * @param type
 * @param host
 */
export function translatedName( type, host ) {
	const english = TRANSLATED_NAMES[ type ];
	return english ? host.translations?.[ english ] || english : undefined;
}

/**
 * Valid parents of a block type, or undefined when the published list is already right.
 * @param type
 */
export function validParents( type ) {
	return VALID_PARENT_TYPES[ type ]
		? [ ...VALID_PARENT_TYPES[ type ] ]
		: undefined;
}
