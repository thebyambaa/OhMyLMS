/** @jsx createElement */
import {
	createElement,
	useRef,
	useLayoutEffect,
	useEffect,
	useCallback,
	RawHTML,
} from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { QuestionMediaUpload } from '../question-editor/QuestionMediaUpload';
import { EquationAction } from './MathInput';
import { loadMath, mathEnabled } from './loader.mjs';
import { RichMathToolbar } from './RichMathToolbar';

const equationLabels = () => ( {
	remove: __( 'Remove equation', 'ohmylms' ),
	display: __( 'Display equation', 'ohmylms' ),
	inline: __( 'Inline equation', 'ohmylms' ),
} );

/**
 * Small rich-text field that preserves existing HTML without a block editor.
 * @param root0
 * @param root0.value
 * @param root0.onChange
 * @param root0.readOnly
 * @param root0.html
 * @param root0.compact
 * @param root0.help
 * @param root0.placeholder
 * @param root0.onDoubleClick
 * @param root0.label
 * @param root0.collapseToolbar
 * @param root0.showToolbar
 */
export function RichContentControl( {
	value,
	onChange,
	readOnly,
	label = __( 'Description / instructions', 'ohmylms' ),
	html = true,
	compact = false,
	showToolbar = false,
	collapseToolbar = false,
	help,
	onDoubleClick,
	placeholder = __( 'Type here …', 'ohmylms' ),
} ) {
	const field = useRef( null );
	const textSelection = useRef( null );
	useEffect( () => {
		const ownerDocument = field.current?.ownerDocument || document;
		const remember = () => {
			const selection = ownerDocument.defaultView.getSelection();
			if ( ! selection?.rangeCount || ! field.current ) {
				return;
			}
			const range = selection.getRangeAt( 0 );
			const node = range.commonAncestorContainer;
			const element =
				node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
			if (
				field.current.contains( node ) &&
				! element.closest( '[data-ohmylms-equation]' )
			) {
				textSelection.current = range.cloneRange();
			}
		};
		ownerDocument.addEventListener( 'selectionchange', remember );
		return () =>
			ownerDocument.removeEventListener( 'selectionchange', remember );
	}, [] );
	const insertEquation = ( source = '' ) => {
		loadMath()
			.then( ( runtime ) => {
				if ( field.current?.isConnected ) {
					runtime.insertAuthor(
						field.current,
						source,
						textSelection.current,
						equationLabels()
					);
				}
			} )
			.catch( () => {} );
	};
	const serialize = useCallback(
		( node ) => {
			if ( window.OhMyLMSMath ) {
				return window.OhMyLMSMath.authorContent( node, html );
			}
			const content = node.innerHTML;
			if ( html ) {
				return content;
			}
			const copy = document.createElement( 'div' );
			copy.innerHTML = content;
			copy.querySelectorAll( 'br' ).forEach( ( br ) =>
				br.replaceWith( '\n' )
			);
			copy.querySelectorAll( 'div, p, li' ).forEach( ( block ) => {
				if ( block.previousSibling ) {
					block.prepend( '\n' );
				}
			} );
			return copy.textContent || '';
		},
		[ html ]
	);
	useLayoutEffect( () => {
		const content = field.current && serialize( field.current );
		if ( field.current && content !== ( value || '' ) ) {
			if ( html ) {
				field.current.innerHTML = value || '';
			} else {
				field.current.textContent = value || '';
			}
		}
		let disposed = false;
		if (
			field.current &&
			mathEnabled() &&
			( value || '' ).includes( '[[ohmylms-math:' )
		) {
			loadMath()
				.then( ( runtime ) => {
					if ( ! disposed && field.current ) {
						runtime.hydrateAuthor(
							field.current,
							equationLabels()
						);
					}
				} )
				.catch( () => {} );
		}
		return () => {
			disposed = true;
		};
	}, [ value, html, serialize ] );
	if ( readOnly ) {
		return html ? <RawHTML>{ value || '' }</RawHTML> : <p>{ value }</p>;
	}
	const format = ( command ) => {
		field.current.focus();
		field.current.ownerDocument.execCommand( command, false );
		onChange( serialize( field.current ) );
	};
	const toolbar = showToolbar && (
		<div role="toolbar" aria-label={ __( 'Text formatting', 'ohmylms' ) }>
			{ ( html
				? [
						[ 'bold', 'Bold', 'editor-bold' ],
						[ 'italic', 'Italic', 'editor-italic' ],
						[ 'underline', 'Underline', 'editor-underline' ],
						[ 'insertUnorderedList', 'Bullet list', 'editor-ul' ],
						[ 'superscript', 'Superscript', 'editor-superscript' ],
						[ 'subscript', 'Subscript', 'editor-subscript' ],
					]
				: []
			).map( ( [ command, formatLabel, icon ] ) => (
				<Button
					key={ command }
					icon={
						[ 'superscript', 'subscript' ].includes( command )
							? undefined
							: icon
					}
					label={ formatLabel }
					onMouseDown={ ( event ) => event.preventDefault() }
					onClick={ () => format( command ) }
				>
					{ 'superscript' === command && (
						<span>
							x<sup>2</sup>
						</span>
					) }
					{ 'subscript' === command && (
						<span>
							x<sub>2</sub>
						</span>
					) }
				</Button>
			) ) }
			{ html && (
				<QuestionMediaUpload
					allowedTypes={ [ 'image' ] }
					onSelect={ ( image ) => {
						if ( ! /^https?:\/\//i.test( image.url || '' ) ) {
							return;
						}
						const node = document.createElement( 'img' );
						node.src = image.url;
						node.alt = image.alt || '';
						field.current.appendChild( node );
						onChange( serialize( field.current ) );
					} }
					render={ ( { open } ) => (
						<Button
							icon="format-image"
							label={ __( 'Add question image', 'ohmylms' ) }
							onClick={ open }
						/>
					) }
				/>
			) }
			<RichMathToolbar onInsert={ insertEquation } />
		</div>
	);
	return (
		<div
			className={
				compact
					? 'ohmylms-rich-content-control'
					: 'ohmylms-form-description'
			}
		>
			<span className="ohmylms-prompt-label">{ label }</span>
			{ showToolbar &&
				( collapseToolbar ? (
					<details className="ohmylms-field-format-menu">
						<summary
							aria-label={
								label +
								': ' +
								__( 'Formatting and equations', 'ohmylms' )
							}
						>
							{ __( 'Format', 'ohmylms' ) }
						</summary>
						{ toolbar }
					</details>
				) : (
					toolbar
				) ) }
			<div
				ref={ field }
				role="textbox"
				tabIndex={ 0 }
				aria-label={ label }
				aria-multiline="true"
				data-placeholder={ placeholder }
				contentEditable
				onDoubleClick={ onDoubleClick }
				suppressContentEditableWarning
				onCopy={ ( event ) => event.stopPropagation() }
				onCut={ ( event ) => event.stopPropagation() }
				onPaste={ ( event ) => {
					// Keep field clipboard edits inside this question, not core's block selection.
					event.stopPropagation();
					if ( ! html ) {
						event.preventDefault();
						field.current.ownerDocument.execCommand(
							'insertText',
							false,
							event.clipboardData.getData( 'text/plain' )
						);
					}
				} }
				onKeyDown={ ( event ) => {
					if (
						mathEnabled() &&
						( event.ctrlKey || event.metaKey ) &&
						event.key.toLowerCase() === 'm'
					) {
						event.preventDefault();
						insertEquation();
					}
				} }
				onInput={ ( event ) =>
					onChange( serialize( event.currentTarget ) )
				}
			/>
			{ help && <p className="description">{ help }</p> }
			<EquationAction
				value={ value }
				html={ html }
				onChange={ onChange }
				showInsert={ ! mathEnabled() }
			/>
		</div>
	);
}
