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
import { QuestionMediaUpload } from '../question-editor/public-media.mjs';
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
 * @param {Object}                                            root0                   Field properties.
 * @param {string}                                            [root0.value]           Stored content.
 * @param {(value: string) => void}                           root0.onChange          Save content.
 * @param {boolean}                                           [root0.readOnly]        Show the content without editing.
 * @param {boolean}                                           [root0.html]            Whether the content is HTML.
 * @param {boolean}                                           [root0.compact]         Compact layout.
 * @param {import('react').ReactNode}                         [root0.help]            Help text.
 * @param {string}                                            [root0.placeholder]     Empty-field hint.
 * @param {import('react').MouseEventHandler<HTMLDivElement>} [root0.onDoubleClick]   Double-click handler.
 * @param {string}                                            [root0.label]           Accessible label.
 * @param {boolean}                                           [root0.collapseToolbar] Collapse the tools into a menu.
 * @param {boolean}                                           [root0.showToolbar]     Show the formatting tools.
 * @return {React.ReactElement} Field.
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
	const field = useRef( /** @type {HTMLDivElement|null} */ ( null ) );
	const textSelection = useRef( /** @type {Range|null} */ ( null ) );
	useEffect( () => {
		const ownerDocument = field.current?.ownerDocument || document;
		const remember = () => {
			const selection = /** @type {Window} */ (
				ownerDocument.defaultView
			).getSelection();
			if ( ! selection?.rangeCount || ! field.current ) {
				return;
			}
			const range = selection.getRangeAt( 0 );
			const node = range.commonAncestorContainer;
			// Read only for nodes inside the field, which always have an element.
			const element = /** @type {Element} */ (
				node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement
			);
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
		( /** @type {HTMLElement} */ node ) => {
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
	/** @param {string} command Document editing command. */
	const format = ( command ) => {
		const node = /** @type {HTMLDivElement} */ ( field.current );
		node.focus();
		node.ownerDocument.execCommand( command, false );
		onChange( serialize( node ) );
	};
	const toolbar = showToolbar && (
		<div role="toolbar" aria-label={ __( 'Text formatting', 'ohmylms' ) }>
			{
				/** @type {[string, string, import('@wordpress/components').IconType][]} */ (
					html
						? [
								[ 'bold', 'Bold', 'editor-bold' ],
								[ 'italic', 'Italic', 'editor-italic' ],
								[
									'underline',
									'Underline',
									'editor-underline',
								],
								[
									'insertUnorderedList',
									'Bullet list',
									'editor-ul',
								],
								[
									'superscript',
									'Superscript',
									'editor-superscript',
								],
								[
									'subscript',
									'Subscript',
									'editor-subscript',
								],
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
						onMouseDown={ (
							/** @type {import('react').MouseEvent} */ event
						) => event.preventDefault() }
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
				) )
			}
			{ html && (
				<QuestionMediaUpload
					allowedTypes={ [ 'image' ] }
					onSelect={ ( attachment ) => {
						const image =
							/** @type {{ url?: string, alt?: string }} */ (
								attachment
							);
						if ( ! /^https?:\/\//i.test( image.url || '' ) ) {
							return;
						}
						const node = document.createElement( 'img' );
						node.src = /** @type {string} */ ( image.url );
						node.alt = image.alt || '';
						const editor = /** @type {HTMLDivElement} */ (
							field.current
						);
						editor.appendChild( node );
						onChange( serialize( editor ) );
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
						/** @type {HTMLDivElement} */ (
							field.current
						).ownerDocument.execCommand(
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
