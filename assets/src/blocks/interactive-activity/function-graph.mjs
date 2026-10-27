import JXG from 'jsxgraph';
import { MathfieldElement } from 'mathlive';
import { compileExpression } from './expression.mjs';
import { COLORS, MAX_FUNCTIONS, normalizeConfig } from './config.mjs';

const LABELS = {
	graph: 'Graph',
	equations: 'Equations',
	equation: 'Equation',
	addFunction: 'Add function',
	remove: 'Remove function',
	color: 'Colour',
	parameters: 'Parameters',
	invalid: 'Cannot graph this expression yet.',
};

let counter = 0;

/**
 * @param {string}      tag        Element name.
 * @param {string}      className  Class names.
 * @param {Document}    doc        Owning document.
 * @param {Object|null} attributes Attributes to set.
 * @return {Element} New element.
 */
function element( tag, className, doc, attributes = null ) {
	const node = doc.createElement( tag );
	if ( className ) {
		node.className = className;
	}
	Object.entries( attributes || {} ).forEach( ( [ name, value ] ) =>
		node.setAttribute( name, value )
	);
	return node;
}

/**
 * Mount the function-graph activity: the board on the left, equations on the right.
 *
 * @param {Element} container       Empty host element.
 * @param {Object}  rawConfig       Activity configuration.
 * @param {Object}  options         Mount options.
 * @param {Object}  options.labels  Translated interface text.
 * @param {string}  options.fonts   MathLive font directory URL.
 * @param {boolean} options.preview Show equations as text only; the editor changes them in its sidebar.
 * @return {{update: function(Object): void, destroy: function(): void}} Handle.
 */
export function mountFunctionGraph( container, rawConfig, options = {} ) {
	const doc = container.ownerDocument;
	const labels = { ...LABELS, ...( options.labels || {} ) };
	const preview = !! options.preview;
	let config = normalizeConfig( rawConfig );
	if ( options.fonts ) {
		MathfieldElement.fontsDirectory = options.fonts;
	}
	MathfieldElement.soundsDirectory = null;
	MathfieldElement.computeEngine = null;

	const id = `ohmylms-activity-board-${ ++counter }`;
	const layout = element( 'div', 'ohmylms-activity-layout', doc );
	const boardNode = element( 'div', 'ohmylms-activity-board', doc, {
		id,
		role: 'img',
		'aria-label': labels.graph,
	} );
	const panel = element( 'div', 'ohmylms-activity-panel', doc );
	const rowsNode = element( 'ul', 'ohmylms-activity-rows', doc, {
		'aria-label': labels.equations,
	} );
	const addButton = element( 'button', 'ohmylms-activity-add', doc, {
		type: 'button',
	} );
	addButton.textContent = labels.addFunction;
	const paramsNode = element( 'div', 'ohmylms-activity-params', doc, {
		role: 'group',
		'aria-label': labels.parameters,
	} );
	panel.append( rowsNode, addButton, paramsNode );
	layout.append( boardNode, panel );
	container.replaceChildren( layout );

	const { xMin, xMax, yMin, yMax } = config.view;
	const board = JXG.JSXGraph.initBoard( id, {
		// The editor canvas is an iframe, so the board must look up its container there.
		document: doc,
		boundingBox: [ xMin, yMax, xMax, yMin ],
		axis: true,
		grid: true,
		showCopyright: false,
		showNavigation: true,
		keepAspectRatio: false,
		pan: { enabled: true, needTwoFingers: true },
		zoom: { wheel: true, needShift: true, pinch: true },
	} );
	const values = {};
	const entries = new Map();

	/**
	 * Recompile after a typing or parameter-list change; a failed parse keeps the previous curve.
	 *
	 * @param {Object} entry Row entry.
	 */
	const compile = ( entry ) => {
		const result = compileExpression(
			entry.latex,
			config.params.map( ( param ) => param.name )
		);
		if ( result.fn ) {
			entry.fn = result.fn;
			entry.error.hidden = true;
			entry.error.textContent = '';
			entry.item.classList.remove( 'is-invalid' );
		} else if ( result.error === 'empty' ) {
			entry.fn = null;
			entry.error.hidden = true;
			entry.error.textContent = '';
			entry.item.classList.remove( 'is-invalid' );
		} else {
			entry.error.hidden = false;
			entry.error.textContent = labels.invalid;
			entry.item.classList.add( 'is-invalid' );
		}
	};

	const nextColor = () =>
		COLORS.find(
			( color ) =>
				! [ ...entries.values() ].some(
					( entry ) => entry.color === color
				)
		) || COLORS[ entries.size % COLORS.length ];

	const removeEntry = ( entry ) => {
		board.removeObject( entry.curve );
		entry.item.remove();
		entries.delete( entry.id );
		refreshControls();
		board.update();
	};

	const refreshControls = () => {
		const canAdd =
			! preview && config.allowAdd && entries.size < MAX_FUNCTIONS;
		addButton.hidden = ! canAdd;
		const canRemove = ! preview && config.allowAdd && entries.size > 1;
		entries.forEach( ( entry ) => {
			entry.remove.hidden = ! canRemove;
		} );
	};

	const addEntry = ( item ) => {
		const entry = {
			id: item.id,
			latex: item.latex,
			color: item.color,
			fn: null,
		};
		entry.item = element( 'li', 'ohmylms-activity-row', doc );
		entry.item.style.setProperty( '--ohmylms-activity-color', entry.color );
		const swatch = element( 'span', 'ohmylms-activity-swatch', doc, {
			'aria-hidden': 'true',
		} );
		entry.error = element( 'p', 'ohmylms-activity-error', doc, {
			role: 'status',
		} );
		entry.error.hidden = true;
		entry.remove = element( 'button', 'ohmylms-activity-remove', doc, {
			type: 'button',
			'aria-label': labels.remove,
			title: labels.remove,
		} );
		entry.remove.textContent = '×';
		entry.remove.addEventListener( 'click', () => removeEntry( entry ) );
		let field;
		if ( preview ) {
			// MathLive cannot style itself inside the editor iframe, so show the source text.
			field = element( 'code', 'ohmylms-activity-field', doc );
			field.textContent = entry.latex || '…';
		} else {
			field = new MathfieldElement();
			field.className = 'ohmylms-activity-field';
			field.setAttribute( 'aria-label', labels.equation );
			field.mathVirtualKeyboardPolicy = 'auto';
			field.smartMode = false;
			field.readOnly = ! config.allowEdit;
			field.setValue( entry.latex, {
				format: 'latex',
				silenceNotifications: true,
			} );
			field.addEventListener( 'input', () => {
				entry.latex = field.getValue( 'latex' );
				compile( entry );
				board.update();
			} );
		}
		entry.item.append( swatch, field, entry.remove, entry.error );
		rowsNode.append( entry.item );
		if ( ! preview ) {
			// MathLive only accepts menu changes once the field is in the document.
			field.menuItems = [];
		}
		entry.curve = board.create(
			'functiongraph',
			[ ( x ) => ( entry.fn ? entry.fn( x, values ) : NaN ) ],
			{
				strokeColor: entry.color,
				strokeWidth: 2.5,
				highlight: false,
				name: '',
			}
		);
		entries.set( entry.id, entry );
		compile( entry );
	};

	const renderParams = () => {
		paramsNode.replaceChildren();
		paramsNode.hidden = config.params.length === 0;
		Object.keys( values ).forEach( ( name ) => delete values[ name ] );
		config.params.forEach( ( param ) => {
			values[ param.name ] = param.value;
			const label = element( 'label', 'ohmylms-activity-param', doc );
			const name = element( 'span', 'ohmylms-activity-param-name', doc );
			name.textContent = param.name;
			const input = element( 'input', '', doc, {
				type: 'range',
				min: param.min,
				max: param.max,
				step: param.step,
			} );
			input.value = param.value;
			const output = element(
				'output',
				'ohmylms-activity-param-value',
				doc
			);
			output.textContent = String( param.value );
			input.addEventListener( 'input', () => {
				values[ param.name ] = Number( input.value );
				output.textContent = String( Number( input.value ) );
				board.update();
			} );
			label.append( name, input, output );
			paramsNode.append( label );
		} );
	};

	addButton.addEventListener( 'click', () => {
		const used = new Set( entries.keys() );
		let number = entries.size + 1;
		while ( used.has( `f${ number }` ) ) {
			number++;
		}
		addEntry( { id: `f${ number }`, latex: '', color: nextColor() } );
		refreshControls();
		[ ...entries.values() ]
			.pop()
			.item.querySelector( 'math-field' )
			?.focus();
	} );

	renderParams();
	config.functions.forEach( addEntry );
	refreshControls();
	board.update();

	return {
		/**
		 * Apply a configuration edited outside the rows (authoring sidebar).
		 *
		 * @param {Object} next Configuration.
		 */
		update( next ) {
			const incoming = normalizeConfig( next );
			const view = incoming.view;
			const sameView =
				JSON.stringify( view ) === JSON.stringify( config.view );
			const sameParams =
				JSON.stringify( incoming.params ) ===
				JSON.stringify( config.params );
			const sameFunctions =
				JSON.stringify( incoming.functions ) ===
				JSON.stringify(
					[ ...entries.values() ].map( ( entry ) => ( {
						id: entry.id,
						latex: entry.latex,
						color: entry.color,
					} ) )
				);
			config = incoming;
			if ( ! sameView ) {
				board.setBoundingBox(
					[ view.xMin, view.yMax, view.xMax, view.yMin ],
					false
				);
			}
			if ( ! sameParams ) {
				renderParams();
				entries.forEach( compile );
			}
			if ( ! sameFunctions ) {
				entries.forEach( ( entry ) => {
					board.removeObject( entry.curve );
				} );
				entries.clear();
				rowsNode.replaceChildren();
				config.functions.forEach( addEntry );
			}
			refreshControls();
			board.update();
		},
		destroy() {
			JXG.JSXGraph.freeBoard( board );
			container.replaceChildren();
		},
	};
}
