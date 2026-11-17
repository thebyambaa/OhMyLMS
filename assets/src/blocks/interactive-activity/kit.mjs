import { normalizeActivity } from './activities.mjs';

export const LABELS = {
	numberLine: 'Number line',
	point: 'Point',
	value: 'Value',
	fractions: 'Fraction',
	parts: 'Equal parts',
	shaded: 'Shaded',
	part: 'Part',
	simplified: 'Simplest form',
	decimal: 'Decimal',
	percent: 'Percent',
	clock: 'Clock',
	hours: 'Hours',
	minutes: 'Minutes',
	converter: 'Unit converter',
	quantity: 'Quantity',
	amount: 'Amount',
	from: 'From',
	to: 'To',
	length: 'Length',
	mass: 'Mass',
	time: 'Time',
	volume: 'Volume',
	chart: 'Chart',
	label: 'Label',
	total: 'Total',
	mean: 'Mean',
	median: 'Median',
	mode: 'Mode',
	noMode: 'none',
	range: 'Range',
	geometry: 'Geometry',
	side: 'Side',
	perimeter: 'Perimeter',
	area: 'Area',
	angle: 'Angle',
	radius: 'Radius',
	circumference: 'Circumference',
	noArea: 'The sides cross, so there is no single area.',
};

const SVG = 'http://www.w3.org/2000/svg';
let counter = 0;

/**
 * @return {number} A number no other activity on the page has used.
 */
export function uniqueId() {
	return ++counter;
}

/**
 * @param {Document}    doc        Owning document.
 * @param {string}      tag        Element name.
 * @param {string}      className  Class names.
 * @param {Object|null} attributes Attributes to set.
 * @param {string}      content    Text content.
 * @return {Element} New element.
 */
export function html(
	doc,
	tag,
	className = '',
	attributes = null,
	content = ''
) {
	const node = doc.createElement( tag );
	if ( className ) {
		node.className = className;
	}
	Object.entries( attributes || {} ).forEach( ( [ name, value ] ) =>
		node.setAttribute( name, String( value ) )
	);
	if ( content ) {
		node.textContent = content;
	}
	return node;
}

/**
 * @param {Document}    doc        Owning document.
 * @param {string}      tag        SVG element name.
 * @param {Object|null} attributes Attributes to set.
 * @param {string}      content    Text content.
 * @return {Element} New SVG element.
 */
export function svg( doc, tag, attributes = null, content = '' ) {
	const node = doc.createElementNS( SVG, tag );
	Object.entries( attributes || {} ).forEach( ( [ name, value ] ) =>
		node.setAttribute( name, String( value ) )
	);
	if ( content ) {
		node.textContent = content;
	}
	return node;
}

/**
 * A labelled number or text input with an id of its own.
 *
 * @param {Document} doc        Owning document.
 * @param {string}   label      Visible label.
 * @param {Object}   attributes Input attributes.
 * @param {string}   tag        `input` or `select`.
 * @return {{field: Element, input: HTMLElement}} Wrapper and control.
 */
export function field( doc, label, attributes, tag = 'input' ) {
	const id = `ohmylms-activity-field-${ uniqueId() }`;
	const wrapper = html( doc, 'div', 'ohmylms-activity-control' );
	const caption = html( doc, 'label', '', { for: id }, label );
	const input = html( doc, tag, '', { ...attributes, id } );
	wrapper.append( caption, input );
	return { field: wrapper, input };
}

/**
 * Shared shell of an activity: normalizes the configuration, owns the root element and offers the
 * update and destroy handle every activity returns.
 *
 * @param {string}                                                                                                  type      Activity type.
 * @param {Element}                                                                                                 container Empty host element.
 * @param {Object}                                                                                                  raw       Stored configuration.
 * @param {Object}                                                                                                  options   Mount options (`labels`, `preview`).
 * @param {function(Element, Object):{render:function(): void, reset?:function(): void, dispose?:function(): void}} setup     Builds the activity; `ctx.config` is always the latest configuration.
 * @return {{update: function(Object): void, destroy: function(): void}} Handle.
 */
export function createActivity( type, container, raw, options, setup ) {
	const doc = container.ownerDocument;
	const ctx = {
		doc,
		type,
		config: normalizeActivity( type, raw ),
		labels: { ...LABELS, ...( options.labels || {} ) },
		preview: !! options.preview,
	};
	const root = html( doc, 'div', `ohmylms-activity-kit is-${ type }` );
	container.replaceChildren( root );
	const activity = setup( root, ctx );
	activity.reset?.();
	activity.render();
	return {
		update( next ) {
			ctx.config = normalizeActivity( type, next );
			activity.reset?.();
			activity.render();
		},
		destroy() {
			activity.dispose?.();
			root.remove();
		},
	};
}
