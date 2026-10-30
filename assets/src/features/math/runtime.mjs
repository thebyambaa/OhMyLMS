import * as MathLive from 'mathlive';
import {
	equationPattern,
	contentEquations,
	templateLatex,
	restoreTemplateTokens,
	safeMathSource,
} from './content.mjs';

const { MathfieldElement } = MathLive;
MathfieldElement.fontsDirectory = window.ohmylmsMath.fonts;
MathfieldElement.soundsDirectory = null;
MathfieldElement.computeEngine = null;
const mounted = new Map();

/**
 * Upgrade an existing field while keeping its name, value and validation contract.
 * @param {HTMLInputElement} input Original answer field.
 * @return {() => void} Cleanup.
 */
function mountInput( input ) {
	if ( mounted.has( input ) ) {
		return mounted.get( input );
	}
	if ( ! safeMathSource( input.value ) ) {
		return () => {};
	}
	const field = new MathfieldElement();
	field.className = 'ohmylms-math-input';
	field.setAttribute(
		'aria-label',
		input.getAttribute( 'aria-label' ) ||
			input.labels?.[ 0 ]?.textContent ||
			'Math answer'
	);
	field.mathVirtualKeyboardPolicy = 'auto';
	field.smartMode = false;
	field.readOnly = input.readOnly || input.disabled;
	let template = templateLatex( input.value );
	field.value = template.latex;
	// Until a real edit, keep legacy ASCII and the author's original spelling untouched.
	const write = ( event ) => {
		event.stopPropagation();
		input.value = restoreTemplateTokens(
			field.getValue( 'latex' ),
			template.tokens
		);
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};
	const restore = () => {
		if (
			restoreTemplateTokens(
				field.getValue( 'latex' ),
				template.tokens
			) !== input.value
		) {
			template = templateLatex( input.value );
			field.setValue( template.latex, {
				format: /[\\{}]/.test( template.latex )
					? 'latex'
					: 'ascii-math',
				silenceNotifications: true,
			} );
			field
				.getPrompts()
				.forEach( ( id ) =>
					field.setPromptState( id, 'undefined', true )
				);
		}
	};
	const focus = () => field.focus();
	const invalid = ( event ) => {
		event.preventDefault();
		field.focus();
	};
	const keyboard = () => {
		window.mathVirtualKeyboard.layouts = [
			{
				label: 'Math',
				rows: [
					[ '7', '8', '9', '+', '-', 'x', 'y' ],
					[ '4', '5', '6', '\\times', '\\frac{#0}{#?}', '(', ')' ],
					[
						'1',
						'2',
						'3',
						'\\sqrt{#0}',
						'#0^{#?}',
						'\\sin',
						'\\cos',
					],
					[
						'0',
						'.',
						'=',
						'\\pi',
						'\\ln',
						'[backspace]',
						'[hide-keyboard]',
					],
				],
			},
		];
		window.mathVirtualKeyboard.editToolbar = 'none';
	};
	field.addEventListener( 'focusin', keyboard );
	field.addEventListener( 'input', write );
	input.addEventListener( 'input', restore );
	input.addEventListener( 'change', restore );
	input.addEventListener( 'focus', focus );
	input.addEventListener( 'invalid', invalid );
	const form = input.form;
	form?.addEventListener( 'ohmylms:answer-restored', restore );
	input.before( field );
	field.menuItems = [];
	field.inlineShortcuts = {
		sqrt: '\\sqrt{#0}',
		sin: '\\sin',
		cos: '\\cos',
		tan: '\\tan',
		ln: '\\ln',
		pi: '\\pi',
	};
	field.removeExtraneousParentheses = false;
	field.setValue( template.latex, {
		format: /[\\{}]/.test( template.latex ) ? 'latex' : 'ascii-math',
		silenceNotifications: true,
	} );
	field
		.getPrompts()
		.forEach( ( id ) => field.setPromptState( id, 'undefined', true ) );
	const oldTabIndex = input.getAttribute( 'tabindex' );
	input.tabIndex = -1;
	input.classList.add( 'ohmylms-math-fallback' );
	const keys = input.parentElement.querySelector(
		'.ohmylms-expression-keys'
	);
	if ( keys ) {
		keys.hidden = true;
	}
	const cleanup = () => {
		field.removeEventListener( 'input', write );
		field.removeEventListener( 'focusin', keyboard );
		input.removeEventListener( 'input', restore );
		input.removeEventListener( 'change', restore );
		input.removeEventListener( 'focus', focus );
		input.removeEventListener( 'invalid', invalid );
		form?.removeEventListener( 'ohmylms:answer-restored', restore );
		field.remove();
		input.classList.remove( 'ohmylms-math-fallback' );
		if ( oldTabIndex === null ) {
			input.removeAttribute( 'tabindex' );
		} else {
			input.setAttribute( 'tabindex', oldTabIndex );
		}
		if ( keys ) {
			keys.hidden = false;
		}
		mounted.delete( input );
	};
	mounted.set( input, cleanup );
	return cleanup;
}

/**
 * Render equation text without interpreting author HTML or changing stored source.
 * @param {Element} root Content region.
 */
function renderContent( root ) {
	const walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT );
	const nodes = [];
	while ( walker.nextNode() ) {
		const node = walker.currentNode;
		if (
			! node.parentElement.closest(
				'script,style,textarea,input,math-field,[contenteditable="true"],option,[data-ohmylms-react-math]'
			) &&
			contentEquations(
				node.textContent,
				Boolean(
					node.parentElement.closest(
						'[data-ohmylms-latex],.ohmylms-structured-question,.ohmylms-exam-player,.ohmylms-default-quiz-preview'
					)
				)
			).length
		) {
			nodes.push( node );
		}
	}
	for ( const node of nodes ) {
		const fragment = document.createDocumentFragment();
		let end = 0;
		for ( const match of contentEquations(
			node.textContent,
			Boolean(
				node.parentElement.closest(
					'[data-ohmylms-latex],.ohmylms-structured-question,.ohmylms-exam-player,.ohmylms-default-quiz-preview'
				)
			)
		) ) {
			if ( ! safeMathSource( match.source ) ) {
				continue;
			}
			fragment.append(
				document.createTextNode(
					node.textContent.slice( end, match.index )
				)
			);
			const field = new MathfieldElement();
			field.readOnly = true;
			field.tabIndex = -1;
			field.mathVirtualKeyboardPolicy = 'manual';
			field.className = `ohmylms-math-display is-${ match.mode }`;
			field.setAttribute( 'aria-label', match.source );
			field.value = templateLatex( match.source ).latex;
			fragment.append( field );
			end = match.index + match.raw.length;
		}
		if ( end ) {
			fragment.append(
				document.createTextNode( node.textContent.slice( end ) )
			);
			node.replaceWith( fragment );
		}
	}
}

/**
 * Serialize author equation widgets back to source markers, never custom-element HTML.
 * @param {Element} root Content editor.
 * @param {boolean} html Whether the existing field stores HTML.
 * @return {string} Existing content model.
 */
function authorContent( root, html = true ) {
	const clone = root.cloneNode( true );
	clone.querySelectorAll( '[data-ohmylms-equation]' ).forEach( ( node ) => {
		const source =
			node.querySelector( 'input' )?.value ?? node.dataset.source ?? '';
		const mode = node.dataset.mode === 'display' ? 'display' : 'inline';
		node.replaceWith(
			document.createTextNode(
				source
					? `[[ohmylms-math:latex:${ mode }]]${ source }[[/ohmylms-math]]`
					: ''
			)
		);
	} );
	if ( html ) {
		return clone.innerHTML;
	}
	clone.querySelectorAll( 'br' ).forEach( ( br ) => br.replaceWith( '\n' ) );
	clone.querySelectorAll( 'div, p, li' ).forEach( ( block ) => {
		if ( block.previousSibling ) {
			block.prepend( '\n' );
		}
	} );
	return clone.textContent || '';
}

/**
 * Return the text caret to either side of an author equation.
 * @param {Element} widget Equation node.
 * @param {boolean} before Whether to move before it.
 */
function authorCaret( widget, before = false ) {
	const root = widget.parentElement.closest(
		'[contenteditable="true"][role="textbox"]'
	);
	if ( ! root ) {
		return;
	}
	const range = root.ownerDocument.createRange();
	if ( before ) {
		range.setStartBefore( widget );
	} else {
		range.setStartAfter( widget );
	}
	range.collapse( true );
	root.focus();
	const selection = root.ownerDocument.defaultView.getSelection();
	selection.removeAllRanges();
	selection.addRange( range );
}

/**
 * Create an author widget; controls never become stored content.
 * @param {Element} root   Editor.
 * @param {string}  source Original LaTeX.
 * @param {string}  mode   Layout.
 * @param {Object}  labels Translated control labels.
 * @return {Element} Widget.
 */
function authorWidget( root, source, mode, labels ) {
	const widget = document.createElement( 'span' );
	widget.contentEditable = 'false';
	widget.dataset.ohmylmsEquation = 'latex';
	widget.dataset.source = source;
	widget.dataset.mode = mode;
	widget.className = `ohmylms-author-equation is-${ mode }`;
	const input = document.createElement( 'input' );
	input.type = 'text';
	input.value = source;
	input.setAttribute(
		'aria-label',
		root.getAttribute( 'aria-label' ) || 'Equation'
	);
	widget.append( input );
	const controls = document.createElement( 'span' );
	controls.className = 'ohmylms-author-equation-controls';
	const toggle = document.createElement( 'button' );
	toggle.type = 'button';
	toggle.textContent = '↔';
	const updateLabel = () => {
		toggle.setAttribute(
			'aria-label',
			widget.dataset.mode === 'inline'
				? labels.display || 'Display equation'
				: labels.inline || 'Inline equation'
		);
		toggle.title = toggle.getAttribute( 'aria-label' );
	};
	updateLabel();
	toggle.addEventListener( 'click', () => {
		const next = widget.dataset.mode === 'inline' ? 'display' : 'inline';
		widget.classList.replace(
			`is-${ widget.dataset.mode }`,
			`is-${ next }`
		);
		widget.dataset.mode = next;
		updateLabel();
		root.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	} );
	const remove = document.createElement( 'button' );
	remove.type = 'button';
	remove.textContent = '×';
	remove.setAttribute( 'aria-label', labels.remove || 'Remove equation' );
	remove.title = remove.getAttribute( 'aria-label' );
	remove.addEventListener( 'click', () => {
		authorCaret( widget, true );
		mounted.get( input )?.();
		widget.remove();
		root.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	} );
	controls.append( toggle, remove );
	widget.append( controls );
	return widget;
}

/**
 * Activate a connected author widget with navigation out to prose.
 * @param {Element} widget Equation node.
 */
function mountAuthor( widget ) {
	const input = widget.querySelector( 'input' );
	mountInput( input );
	const field = widget.querySelector( 'math-field' );
	if ( field && ! widget.dataset.navigation ) {
		widget.dataset.navigation = '1';
		field.addEventListener( 'move-out', ( event ) => {
			event.preventDefault();
			authorCaret(
				widget,
				[ 'backward', 'upward' ].includes( event.detail.direction )
			);
		} );
		field.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Tab' || event.key === 'Escape' ) {
				event.preventDefault();
				event.stopPropagation();
				authorCaret( widget, event.shiftKey );
			}
		} );
	}
}

/**
 * Insert an editable equation at the saved text selection.
 * @param {Element}    root      Editor.
 * @param {string}     source    Original LaTeX, possibly empty until authored.
 * @param {Range|null} selection Saved text selection.
 * @param {Object}     labels    Translated controls.
 * @return {Element} Inserted widget.
 */
function insertAuthor( root, source = '', selection = null, labels = {} ) {
	const range = selection?.cloneRange() || root.ownerDocument.createRange();
	if ( ! selection || ! root.contains( range.commonAncestorContainer ) ) {
		range.selectNodeContents( root );
		range.collapse( false );
	}
	range.deleteContents();
	const widget = authorWidget( root, source, 'inline', labels );
	range.insertNode( widget );
	mountAuthor( widget );
	root.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	const field = widget.querySelector( 'math-field' );
	if ( field ) {
		field.focus();
		field.position = field.lastOffset;
	} else {
		widget.querySelector( 'input' ).focus();
	}
	return widget;
}

/**
 * Editable MathLive nodes in the prompt editor; original markers remain the save contract.
 * @param {Element} root   Content editor.
 * @param {Object}  labels Translated equation control labels.
 */
function hydrateAuthor( root, labels = {} ) {
	const walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT );
	const nodes = [];
	while ( walker.nextNode() ) {
		const node = walker.currentNode;
		if (
			! node.parentElement.closest(
				'math-field,[data-ohmylms-equation]'
			) &&
			node.textContent.includes( '[[ohmylms-math:' )
		) {
			nodes.push( node );
		}
	}
	for ( const node of nodes ) {
		const fragment = document.createDocumentFragment();
		let end = 0;
		for ( const match of node.textContent.matchAll( equationPattern() ) ) {
			fragment.append(
				document.createTextNode(
					node.textContent.slice( end, match.index )
				)
			);
			const widget = authorWidget( root, match[ 2 ], match[ 1 ], labels );
			fragment.append( widget );
			end = match.index + match[ 0 ].length;
		}
		if ( end ) {
			fragment.append(
				document.createTextNode( node.textContent.slice( end ) )
			);
			node.replaceWith( fragment );
		}
	}
	root.querySelectorAll( '[data-ohmylms-equation]' ).forEach( mountAuthor );
}

/**
 * Mount a region and release every upgraded field when its owner unmounts.
 * @param {Element} root Question or preview.
 * @return {() => void} Cleanup.
 */
function mount( root ) {
	const scan = () => {
		root.querySelectorAll(
			'input.ohmylms-expression-input,input[data-ohmylms-math-input]'
		).forEach( mountInput );
		renderContent( root );
		for ( const [ input, cleanup ] of mounted ) {
			if ( ! input.isConnected ) {
				cleanup();
			}
		}
	};
	scan();
	const observer = new MutationObserver( scan );
	observer.observe( root, {
		childList: true,
		subtree: true,
		characterData: true,
	} );
	return () => {
		observer.disconnect();
		for ( const [ input, cleanup ] of mounted ) {
			if ( root.contains( input ) ) {
				cleanup();
			}
		}
	};
}

/**
 * Display a known expression in a React-owned container without changing its source.
 * @param {Element} container Host.
 * @param {string}  source    Original source.
 * @return {() => void} Cleanup.
 */
function display( container, source ) {
	if ( ! safeMathSource( source ) ) {
		return () => {};
	}
	const field = new MathfieldElement();
	field.readOnly = true;
	field.tabIndex = -1;
	field.mathVirtualKeyboardPolicy = 'manual';
	field.className = 'ohmylms-math-display';
	field.setAttribute( 'aria-label', source );
	container.append( field );
	const latex = templateLatex( source ).latex;
	field.setValue( latex, {
		format: /[\\{}]/.test( latex ) ? 'latex' : 'ascii-math',
		silenceNotifications: true,
	} );
	const fallback = container.querySelector( '[data-math-source]' );
	if ( fallback ) {
		fallback.hidden = true;
	}
	return () => {
		field.remove();
		if ( fallback ) {
			fallback.hidden = false;
		}
	};
}

window.OhMyLMSMath = {
	// The page's only MathLive; other bundles import `ohmylms/mathlive`, which webpack maps here.
	MathLive,
	mountInput,
	renderContent,
	mount,
	display,
	authorContent,
	hydrateAuthor,
	insertAuthor,
};
// Server-rendered quizzes, practice regions and reviews share the same marker renderer.
if ( window.ohmylmsMath.enabled ) {
	const cleanup = mount( document.body );
	window.addEventListener( 'pagehide', cleanup, { once: true } );
}
