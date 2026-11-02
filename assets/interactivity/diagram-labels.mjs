const { __, sprintf } = window.wp.i18n;
/** @type {WeakMap<Element, () => void>} */
const views = new WeakMap();
/** @type {WeakSet<Document>} */
const documents = new WeakSet();

/**
 * @typedef {Object} DiagramTarget
 * @property {string}        id Answer key.
 * @property {number|string} x  Left, in percent of the image.
 * @property {number|string} y  Top, in percent of the image.
 */

/**
 * @typedef {Object} DragState
 * @property {HTMLButtonElement} node    Dragged label.
 * @property {string}            choice  Its text.
 * @property {number}            x       Start X.
 * @property {number}            y       Start Y.
 * @property {boolean}           moved   Past the drag threshold.
 * @property {number}            pointer Pointer id.
 */

/**
 * Upgrade the existing label-to-target answer contract with pointer and keyboard input.
 *
 * @param {HTMLElement} root Native question root, including precreated answer fields.
 * @param {HTMLElement} stage Image-sized coordinate surface.
 * @param {{targets?: DiagramTarget[], choices?: string[]}} config Public coordinates and an unordered label bank, without keys.
 */
export function buildDiagramLabels( root, stage, config ) {
	if ( views.has( root ) ) return;
	const doc = root.ownerDocument;
	/**
	 * @template {keyof HTMLElementTagNameMap} K
	 * @param {K}      tag    Tag name.
	 * @param {string} [text] Text content.
	 * @return {HTMLElementTagNameMap[K]} Element.
	 */
	const make = ( tag, text = '' ) => {
		const node = doc.createElement( tag );
		node.textContent = text;
		return node;
	};
	/**
	 * @param {string}                     text   Label.
	 * @param {(event: MouseEvent) => void} action Click handler.
	 * @return {HTMLButtonElement} Button.
	 */
	const button = ( text, action ) => {
		const node = make( 'button', text );
		node.type = 'button';
		node.addEventListener( 'click', action );
		return node;
	};
	const targets = config.targets || [];
	const choices = [ ...new Set( config.choices || [] ) ];
	/** @type {Map<string, HTMLInputElement>} */
	const fields = new Map(
		Array.from(
			/** @type {NodeListOf<HTMLInputElement>} */ (
				root.querySelectorAll( 'input[data-answer-key]' )
			),
			( input ) =>
				/** @type {[string, HTMLInputElement]} */ ( [
					input.dataset.answerKey,
					input,
				] )
		)
	);
	const prefix = root.dataset.namePrefix;
	const storage =
		prefix && ! root.closest( '[data-autosave]' )
			? `ohmylms:diagram:${ window.location.pathname }${ window.location.search }:${ prefix }`
			: null;
	/** @type {Object<string, string>} */
	let cached = {};
	try {
		cached = storage
			? JSON.parse( sessionStorage.getItem( storage ) || '{}' )
			: {};
	} catch {
		// Draft storage is optional.
	}
	for ( const target of targets ) {
		let input = fields.get( target.id );
		if ( ! input ) {
			input = /** @type {HTMLInputElement} */ ( make( 'input' ) );
			input.type = 'hidden';
			input.dataset.answerKey = target.id;
			if ( prefix ) input.name = `${ prefix }[${ target.id }]`;
			root.append( input );
			fields.set( target.id, input );
		}
		if ( ! input.value && choices.includes( cached?.[ target.id ] ) )
			input.value = cached[ target.id ];
	}
	// Keep the original select controls until this module has loaded successfully.
	stage.querySelectorAll( 'select' ).forEach( ( select ) => select.remove() );
	const panel = make( 'div' );
	panel.className = 'ohmylms-diagram-labels';
	const instructions = make(
		'p',
		__(
			'Drag a label onto a numbered pin, or select a label and then a pin. You can also use the label menus. Labels may be reused.',
			'ohmylms'
		)
	);
	panel.append( instructions );
	const bank = make( 'div' );
	bank.className = 'ohmylms-label-bank';
	bank.setAttribute( 'role', 'group' );
	bank.setAttribute(
		'aria-label',
		__( 'Available diagram labels', 'ohmylms' )
	);
	const status = make( 'p' );
	status.setAttribute( 'role', 'status' );
	const menus = make( 'details' );
	menus.className = 'ohmylms-label-menus';
	menus.append( make( 'summary', __( 'Use label menus', 'ohmylms' ) ) );
	/** @type {string|null} */
	let selected = null;
	/** @type {DragState|null} */
	let dragging = null;
	/** @type {HTMLElement|null} */
	let hover = null;
	let suppressUntil = 0;
	// Every target gets a field above and a pin and a select below, before the first sync().
	/** @type {Map<string, HTMLButtonElement>} */
	const pins = new Map();
	/** @type {Map<string, HTMLSelectElement>} */
	const selects = new Map();
	/** @type {Map<string, HTMLButtonElement>} */
	const buttons = new Map();
	const sync = () => {
		let answered = 0;
		for ( const [ index, target ] of targets.entries() ) {
			const value = /** @type {HTMLInputElement} */ (
				fields.get( target.id )
			).value;
			if ( value ) answered++;
			const pin = /** @type {HTMLButtonElement} */ (
				pins.get( target.id )
			);
			pin.classList.toggle( 'is-filled', Boolean( value ) );
			pin.dataset.label = value;
			pin.setAttribute(
				'aria-label',
				sprintf(
					/* translators: 1: pin number, 2: currently assigned label. */
					__( 'Pin %1$d: %2$s', 'ohmylms' ),
					index + 1,
					value || __( 'Unassigned', 'ohmylms' )
				)
			);
			/** @type {HTMLSelectElement} */ (
				selects.get( target.id )
			).value = value;
		}
		for ( const [ choice, node ] of buttons )
			node.setAttribute( 'aria-pressed', String( choice === selected ) );
		status.textContent =
			selected === null
				? sprintf(
						/* translators: 1: filled pins, 2: total pins. */
						__( '%1$d / %2$d pins labelled.', 'ohmylms' ),
						answered,
						targets.length
					)
				: sprintf(
						/* translators: %s: selected label text. */ __(
							'Selected: %s. Choose a pin. Escape cancels.',
							'ohmylms'
						),
						selected
					);
	};
	/**
	 * @param {string} id    Target id.
	 * @param {string} value Label, '' to clear.
	 */
	const assign = ( id, value ) => {
		const field = fields.get( id );
		if (
			! field ||
			( value !== '' && ! choices.includes( value ) ) ||
			field.disabled
		)
			return;
		field.value = value;
		selected = null;
		root.dataset.touched = '1';
		try {
			if ( storage )
				sessionStorage.setItem(
					storage,
					JSON.stringify(
						Object.fromEntries(
							[ ...fields ].map( ( [ key, node ] ) => [
								key,
								node.value,
							] )
						)
					)
				);
		} catch {
			// Answers remain usable when browser storage is unavailable.
		}
		sync();
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};
	/**
	 * @param {PointerEvent} event Pointer position.
	 * @return {HTMLElement|null} Pin under the pointer.
	 */
	const pointTarget = ( event ) => {
		const pin = /** @type {HTMLElement|null|undefined} */ (
			doc
				.elementFromPoint( event.clientX, event.clientY )
				?.closest( '[data-label-target]' )
		);
		return pin && stage.contains( pin ) ? pin : null;
	};
	const finishDrag = () => {
		hover?.classList.remove( 'is-drop-target' );
		hover = null;
		dragging?.node.classList.remove( 'is-dragging' );
		dragging = null;
	};
	choices.forEach( ( choice ) => {
		const node = button( choice, ( event ) => {
			if ( event.detail > 0 && Date.now() < suppressUntil ) return;
			selected = selected === choice ? null : choice;
			sync();
		} );
		node.draggable = false;
		node.addEventListener( 'pointerdown', ( event ) => {
			if ( ! event.isPrimary || event.button !== 0 ) return;
			dragging = {
				node,
				choice,
				x: event.clientX,
				y: event.clientY,
				moved: false,
				pointer: event.pointerId,
			};
			node.setPointerCapture( event.pointerId );
		} );
		node.addEventListener( 'pointermove', ( event ) => {
			if ( ! dragging || dragging.pointer !== event.pointerId ) return;
			if (
				Math.hypot(
					event.clientX - dragging.x,
					event.clientY - dragging.y
				) > 6
			)
				dragging.moved = true;
			if ( ! dragging.moved ) return;
			node.classList.add( 'is-dragging' );
			hover?.classList.remove( 'is-drop-target' );
			hover = pointTarget( event );
			hover?.classList.add( 'is-drop-target' );
		} );
		node.addEventListener( 'pointerup', ( event ) => {
			if ( ! dragging || dragging.pointer !== event.pointerId ) return;
			if ( dragging.moved ) {
				suppressUntil = Date.now() + 300;
				const pin = pointTarget( event );
				if ( pin )
					assign(
						/** @type {string} */ ( pin.dataset.labelTarget ),
						choice
					);
			}
			finishDrag();
		} );
		node.addEventListener( 'pointercancel', finishDrag );
		node.addEventListener( 'lostpointercapture', finishDrag );
		buttons.set( choice, node );
		bank.append( node );
	} );
	for ( const [ index, target ] of targets.entries() ) {
		const pin = button( String( index + 1 ), () => {
			if ( selected !== null ) {
				assign( target.id, selected );
			} else {
				menus.open = true;
				/** @type {HTMLSelectElement} */ (
					selects.get( target.id )
				).focus();
			}
		} );
		pin.className = 'ohmylms-label-pin';
		pin.dataset.labelTarget = target.id;
		pin.style.left = `${ Number( target.x ) }%`;
		pin.style.top = `${ Number( target.y ) }%`;
		pin.classList.toggle( 'label-to-left', Number( target.x ) > 50 );
		pin.addEventListener( 'keydown', ( event ) => {
			if ( [ 'Backspace', 'Delete' ].includes( event.key ) ) {
				event.preventDefault();
				assign( target.id, '' );
			}
		} );
		stage.append( pin );
		pins.set( target.id, pin );
		const row = make(
			'label',
			sprintf(
				/* translators: %d: pin number. */ __( 'Pin %d', 'ohmylms' ),
				index + 1
			)
		);
		const select = make( 'select' );
		select.setAttribute(
			'aria-label',
			sprintf(
				/* translators: %d: pin number. */ __( 'Pin %d', 'ohmylms' ),
				index + 1
			)
		);
		const empty = make( 'option', __( 'Choose label', 'ohmylms' ) );
		empty.value = '';
		select.append( empty );
		choices.forEach( ( choice ) => {
			const option = make( 'option', choice );
			option.value = choice;
			select.append( option );
		} );
		select.addEventListener( 'change', () =>
			assign( target.id, select.value )
		);
		row.append( select );
		menus.append( row );
		selects.set( target.id, select );
	}
	panel.append(
		bank,
		status,
		menus,
		button( __( 'Clear labels', 'ohmylms' ), () => {
			for ( const target of targets ) assign( target.id, '' );
		} )
	);
	/** @type {HTMLElement} */ ( stage.parentElement ).append( panel );
	root.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			selected = null;
			finishDrag();
			sync();
		}
	} );
	root.addEventListener( 'change', sync );
	views.set( root, sync );
	root.dataset.labelsReady = '1';
	if ( ! documents.has( doc ) ) {
		doc.addEventListener(
			'ohmylms:answer-restored',
			( event ) => {
				/** @type {Element} */ ( event.target )
					.querySelectorAll( '[data-labels-ready]' )
					.forEach( ( node ) => views.get( node )?.() );
			},
			true
		);
		documents.add( doc );
	}
	sync();
}
