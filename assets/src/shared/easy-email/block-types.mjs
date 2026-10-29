/**
 * Block type names of the recovered Easy Email core.
 *
 * The published core has no footer blocks and does have an advanced table block.
 * Saved emails and the editor rely on this exact set and order, so the lists are authored
 * here instead of being taken from the package.
 */
export const BasicType = {
	PAGE: 'page',
	SECTION: 'section',
	COLUMN: 'column',
	GROUP: 'group',
	TEXT: 'text',
	IMAGE: 'image',
	DIVIDER: 'divider',
	SPACER: 'spacer',
	BUTTON: 'button',
	WRAPPER: 'wrapper',
	RAW: 'raw',
	ACCORDION: 'accordion',
	ACCORDION_ELEMENT: 'accordion-element',
	ACCORDION_TITLE: 'accordion-title',
	ACCORDION_TEXT: 'accordion-text',
	HERO: 'hero',
	CAROUSEL: 'carousel',
	NAVBAR: 'navbar',
	SOCIAL: 'social',
	TABLE: 'table',
	FOOTER: 'footer',
	TEMPLATE: 'template',
};

export const AdvancedType = {
	TEXT: 'advanced_text',
	IMAGE: 'advanced_image',
	DIVIDER: 'advanced_divider',
	SPACER: 'advanced_spacer',
	BUTTON: 'advanced_button',
	NAVBAR: 'advanced_navbar',
	SOCIAL: 'advanced_social',
	ACCORDION: 'advanced_accordion',
	CAROUSEL: 'advanced_carousel',
	FOOTER: 'advanced_footer',
	WRAPPER: 'advanced_wrapper',
	SECTION: 'advanced_section',
	COLUMN: 'advanced_column',
	GROUP: 'advanced_group',
	HERO: 'advanced_hero',
};

/** Advanced block type the published core registers but the recovered core never had. */
export const UNSUPPORTED_ADVANCED_TYPE = 'advanced_table';

/** Registration order of the recovered core's standard blocks. */
export const STANDARD_ORDER = [
	BasicType.PAGE,
	BasicType.SECTION,
	BasicType.COLUMN,
	BasicType.TEXT,
	BasicType.IMAGE,
	BasicType.GROUP,
	BasicType.BUTTON,
	BasicType.DIVIDER,
	BasicType.WRAPPER,
	BasicType.SPACER,
	BasicType.RAW,
	BasicType.CAROUSEL,
	BasicType.HERO,
	BasicType.NAVBAR,
	BasicType.SOCIAL,
	BasicType.FOOTER,
	BasicType.TEMPLATE,
	BasicType.ACCORDION,
	BasicType.ACCORDION_ELEMENT,
	BasicType.ACCORDION_TITLE,
	BasicType.ACCORDION_TEXT,
	BasicType.TABLE,
];

/** Registration order of the recovered core's advanced blocks. */
export const ADVANCED_ORDER = [
	AdvancedType.TEXT,
	AdvancedType.BUTTON,
	AdvancedType.IMAGE,
	AdvancedType.DIVIDER,
	AdvancedType.SPACER,
	AdvancedType.NAVBAR,
	AdvancedType.ACCORDION,
	AdvancedType.CAROUSEL,
	AdvancedType.SOCIAL,
	AdvancedType.WRAPPER,
	AdvancedType.SECTION,
	AdvancedType.GROUP,
	AdvancedType.COLUMN,
	AdvancedType.HERO,
	AdvancedType.FOOTER,
];

/** Where each content block may be placed. Footers were added to the published lists. */
const CONTENT_PARENTS = [
	BasicType.PAGE,
	BasicType.WRAPPER,
	BasicType.COLUMN,
	BasicType.GROUP,
	BasicType.HERO,
	BasicType.FOOTER,
	AdvancedType.WRAPPER,
	AdvancedType.COLUMN,
	AdvancedType.GROUP,
	AdvancedType.HERO,
	AdvancedType.FOOTER,
];
const BASIC_CONTENT_PARENTS = [
	BasicType.COLUMN,
	BasicType.HERO,
	BasicType.FOOTER,
];

/** Valid parents that differ from the published core, by block type. */
export const VALID_PARENT_TYPES = {
	[ BasicType.TEXT ]: BASIC_CONTENT_PARENTS,
	[ BasicType.IMAGE ]: BASIC_CONTENT_PARENTS,
	[ BasicType.DIVIDER ]: BASIC_CONTENT_PARENTS,
	[ BasicType.SPACER ]: BASIC_CONTENT_PARENTS,
	[ BasicType.BUTTON ]: BASIC_CONTENT_PARENTS,
	[ BasicType.NAVBAR ]: BASIC_CONTENT_PARENTS,
	[ BasicType.RAW ]: [
		BasicType.PAGE,
		BasicType.WRAPPER,
		BasicType.SECTION,
		BasicType.GROUP,
		BasicType.COLUMN,
		BasicType.HERO,
		BasicType.FOOTER,
	],
	[ AdvancedType.TEXT ]: CONTENT_PARENTS,
	[ AdvancedType.IMAGE ]: CONTENT_PARENTS,
	[ AdvancedType.DIVIDER ]: CONTENT_PARENTS,
	[ AdvancedType.SPACER ]: CONTENT_PARENTS,
	[ AdvancedType.BUTTON ]: CONTENT_PARENTS,
	[ AdvancedType.NAVBAR ]: CONTENT_PARENTS,
	[ AdvancedType.SOCIAL ]: CONTENT_PARENTS,
	[ AdvancedType.ACCORDION ]: CONTENT_PARENTS,
	[ AdvancedType.CAROUSEL ]: CONTENT_PARENTS,
};
