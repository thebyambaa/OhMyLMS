import {
	createElement,
	useEffect,
	useRef,
	useState,
	useId,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Dashicon, DropdownMenu } from '@wordpress/components';

/** The words behind a validation code, for fields that save as you leave them. */
export const MESSAGES = {
	required: () => __( 'Enter a name.', 'ohmylms' ),
	'too-long': () => __( 'That is too long.', 'ohmylms' ),
};

/**
 * A text field that saves when you leave it (or press Enter, for a single line). Escape puts the saved text
 * back. `onSave(text)` answers whether it worked; the text stays as typed when it did not, so nothing is lost.
 * While you are typing, a change coming from the server never overwrites your text.
 * @param root0
 * @param root0.id
 * @param root0.label
 * @param root0.value
 * @param root0.onSave
 * @param root0.onDraftChange
 * @param root0.validate
 * @param root0.multiline
 * @param root0.hideLabel
 * @param root0.className
 * @param root0.inputClassName
 * @param root0.placeholder
 * @param root0.help
 * @param root0.disabled
 * @param root0.suggestions
 */
export function SaveField( {
	id,
	label,
	value,
	onSave,
	onDraftChange,
	validate,
	multiline = false,
	hideLabel = false,
	className = '',
	inputClassName = '',
	placeholder = '',
	help = '',
	disabled = false,
	suggestions = [],
} ) {
	const [ draft, setDraft ] = useState( value ?? '' );
	const [ problem, setProblem ] = useState( '' );
	const [ state, setState ] = useState( 'idle' );
	const editing = useRef( false );
	const cancelled = useRef( false );
	useEffect( () => {
		if ( ! editing.current ) {
			setDraft( value ?? '' );
		}
	}, [ value ] );
	useEffect( () => {
		if ( state !== 'saved' ) {
			return undefined;
		}
		const timer = setTimeout( () => setState( 'idle' ), 2000 );
		return () => clearTimeout( timer );
	}, [ state ] );
	async function commit() {
		editing.current = false;
		if ( cancelled.current ) {
			cancelled.current = false;
			setDraft( value ?? '' );
			setProblem( '' );
			return;
		}
		if ( draft === ( value ?? '' ) ) {
			setProblem( '' );
			return;
		}
		const found = validate ? validate( draft ) : '';
		setProblem( found );
		if ( found ) {
			return;
		}
		setState( 'saving' );
		const ok = await onSave( draft );
		setState( ok ? 'saved' : 'error' );
	}
	const Field = multiline ? 'textarea' : 'input';
	const note =
		problem ||
		( state === 'error' ? __( 'Not saved. Try again.', 'ohmylms' ) : '' );
	return (
		<div className={ `ohmylms-ws-field ${ className }`.trim() }>
			<label
				htmlFor={ id }
				className={
					hideLabel ? 'screen-reader-text' : 'ohmylms-ws-label'
				}
			>
				{ label }
			</label>
			<Field
				id={ id }
				className={ inputClassName }
				value={ draft }
				placeholder={ placeholder }
				rows={ multiline ? 3 : undefined }
				disabled={ disabled }
				list={
					! multiline && suggestions.length
						? `${ id }-suggestions`
						: undefined
				}
				aria-invalid={ Boolean( note ) }
				aria-describedby={ note || help ? `${ id }-note` : undefined }
				onFocus={ () => {
					editing.current = true;
				} }
				onChange={ ( event ) => {
					setDraft( event.target.value );
					onDraftChange?.( event.target.value );
				} }
				onBlur={ commit }
				onKeyDown={ ( event ) => {
					if ( event.key === 'Enter' && ! multiline ) {
						event.preventDefault();
						event.currentTarget.blur();
					} else if ( event.key === 'Escape' ) {
						cancelled.current = true;
						onDraftChange?.( value ?? '' );
						event.currentTarget.blur();
					}
				} }
			/>
			{ ! multiline && suggestions.length > 0 && (
				<datalist id={ `${ id }-suggestions` }>
					{ suggestions.map( ( text ) => (
						<option key={ text } value={ text } />
					) ) }
				</datalist>
			) }
			<span
				id={ `${ id }-note` }
				className={ `ohmylms-ws-note${ note ? ' is-error' : '' }` }
				role="status"
			>
				{ note ||
					( state === 'saving' && __( 'Saving…', 'ohmylms' ) ) ||
					( state === 'saved' && __( 'Saved', 'ohmylms' ) ) ||
					help }
			</span>
		</div>
	);
}

/**
 * Add one thing after another without leaving the keyboard: type the code, Tab, type the name, Enter. It
 * stays open after each add and returns to the first field, so a list of chapters or skills goes in quickly.
 * @param root0
 * @param root0.nameLabel
 * @param root0.codeLabel
 * @param root0.submitLabel
 * @param root0.codeOnly
 * @param root0.pending
 * @param root0.onAdd
 * @param root0.onCancel
 * @param root0.help
 */
export function QuickAdd( {
	nameLabel,
	codeLabel,
	submitLabel,
	codeOnly = false,
	pending,
	onAdd,
	onCancel,
	help,
} ) {
	const _controlId = useId();
	const [ draft, setDraft ] = useState( {
		name: '',
		code: '',
	} );
	const [ problem, setProblem ] = useState( '' );
	const first = useRef( null );
	useEffect( () => {
		first.current?.focus();
	}, [] );
	async function submit( event ) {
		event.preventDefault();
		const name = draft.name.trim();
		const code = draft.code.trim();
		if ( ! name && ! ( codeOnly && code ) ) {
			setProblem(
				codeOnly
					? __( 'Enter a name or a code.', 'ohmylms' )
					: __( 'Enter a name.', 'ohmylms' )
			);
			return;
		}
		setProblem( '' );
		if ( pending ) {
			return;
		}
		const ok = await onAdd( {
			name,
			code,
		} );
		if ( ok ) {
			setDraft( {
				name: '',
				code: '',
			} );
			first.current?.focus();
		}
	}
	return (
		<form className="ohmylms-ws-quickadd" onSubmit={ submit } noValidate>
			<div className="ohmylms-ws-quickadd-fields">
				<label
					className="ohmylms-ws-quickadd-code"
					htmlFor={ _controlId + '-field-1' }
				>
					<span className="ohmylms-ws-label">{ codeLabel }</span>
					<input
						ref={ first }
						value={ draft.code }
						onChange={ ( event ) =>
							setDraft( {
								...draft,
								code: event.target.value,
							} )
						}
						id={ _controlId + '-field-1' }
					/>
				</label>
				<label
					className="ohmylms-ws-quickadd-name"
					htmlFor={ _controlId + '-field-2' }
				>
					<span className="ohmylms-ws-label">{ nameLabel }</span>
					<input
						value={ draft.name }
						aria-invalid={ Boolean( problem ) }
						onChange={ ( event ) =>
							setDraft( {
								...draft,
								name: event.target.value,
							} )
						}
						id={ _controlId + '-field-2' }
					/>
				</label>
				<div className="ohmylms-ws-quickadd-actions">
					<Button
						variant="primary"
						type="submit"
						isBusy={ pending }
						disabled={ pending }
					>
						{ submitLabel }
					</Button>
					<Button variant="secondary" onClick={ onCancel }>
						{ __( 'Done', 'ohmylms' ) }
					</Button>
				</div>
			</div>
			<p
				className={ `ohmylms-ws-note${ problem ? ' is-error' : '' }` }
				role="status"
			>
				{ problem || help }
			</p>
		</form>
	);
}
const ICONS = {
	topic: 'category',
	chapter: 'category',
	skill: 'awards',
	lesson: 'media-document',
	quiz: 'editor-help',
	assignment: 'clipboard',
	syllabus: 'welcome-learn-more',
};
export function KindIcon( { kind, icon, color } ) {
	if ( kind === 'topic' || kind === 'chapter' ) {
		return (
			<svg
				className={ `ohmylms-ws-kind is-${ kind }` }
				width="20"
				height="20"
				viewBox="0 0 24 24"
				fill="none"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinejoin="round"
				aria-hidden="true"
				focusable="false"
			>
				<path d="M3 7V5a1 1 0 0 1 1-1h5l2 3h9a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7Z" />
				<path d="M3 8h18" />
			</svg>
		);
	}
	return (
		<Dashicon
			icon={ icon || ICONS[ kind ] || 'marker' }
			className={ `ohmylms-ws-kind is-${ kind }` }
			style={
				color
					? {
							color,
						}
					: undefined
			}
			aria-hidden="true"
		/>
	);
}

/**
 * The small label that says what a row is: Chapter, Skill, Lesson…
 * @param root0
 * @param root0.children
 * @param root0.tone
 */
export function Tag( { children, tone = '' } ) {
	return (
		<span className={ `ohmylms-ws-tag${ tone ? ` is-${ tone }` : '' }` }>
			{ children }
		</span>
	);
}

/**
 * The "⋯" menu of a row or a pane. `controls` are `{title, onClick, isDisabled}`.
 * @param root0
 * @param root0.label
 * @param root0.controls
 * @param root0.disabled
 * @param root0.icon
 */
export function RowMenu( { label, controls, disabled, icon = 'ellipsis' } ) {
	return (
		<DropdownMenu
			className="ohmylms-ws-menu"
			icon={ icon }
			label={ label }
			controls={ controls }
			disabled={ disabled }
			popoverProps={ {
				placement: 'bottom-end',
			} }
		/>
	);
}

/**
 * One line of a list in the right pane: an icon, a title, what it is, a little detail and a menu. The
 * title opens it (a button, or a link when it goes to another screen).
 * @param root0
 * @param root0.kind
 * @param root0.icon
 * @param root0.iconColor
 * @param root0.title
 * @param root0.tag
 * @param root0.tagTone
 * @param root0.meta
 * @param root0.onOpen
 * @param root0.href
 * @param root0.menu
 * @param root0.notes
 * @param root0.status
 * @param root0.children
 * @param root0.reorder
 */
export function Row( {
	kind,
	icon,
	iconColor,
	title,
	tag,
	tagTone,
	meta,
	onOpen,
	href,
	menu,
	notes,
	status,
	children,
	reorder,
} ) {
	const body = (
		<>
			<KindIcon kind={ kind } icon={ icon } color={ iconColor } />
			<span className="ohmylms-ws-row-title">
				{ title }
				{ notes && (
					<span className="ohmylms-ws-row-notes">{ notes }</span>
				) }
			</span>
			{ tag && <Tag tone={ tagTone }>{ tag }</Tag> }
			{ status }
			{ meta && <span className="ohmylms-ws-row-meta">{ meta }</span> }
		</>
	);
	return (
		<li
			className={ `ohmylms-ws-row${ ( () => {
				if ( reorder?.target ) {
					if ( reorder.after ) {
						return ' is-drop-after';
					}
					return ' is-drop-before';
				}
				return '';
			} )() }` }
			onDragOver={ reorder?.onDragOver }
			onDrop={ reorder?.onDrop }
		>
			<div className="ohmylms-ws-row-line">
				{ reorder && (
					<button
						type="button"
						className="ohmylms-ws-drag-handle"
						draggable={ ! reorder.disabled }
						disabled={ reorder.disabled }
						aria-label={ sprintf(
							/* translators: %s: curriculum item name. */ __(
								'Reorder %s. Use Alt and arrow keys to move.',
								'ohmylms'
							),
							title
						) }
						title={ __(
							'Drag to reorder; Alt + Up/Down also works',
							'ohmylms'
						) }
						onDragStart={ reorder.onDragStart }
						onDragEnd={ reorder.onDragEnd }
						onKeyDown={ reorder.onKeyDown }
					>
						<span
							className="ohmylms-ws-drag-grip"
							aria-hidden="true"
						/>
					</button>
				) }
				{ href ? (
					<a className="ohmylms-ws-row-main" href={ href }>
						{ body }
					</a>
				) : (
					<button
						type="button"
						className="ohmylms-ws-row-main"
						onClick={ onOpen }
					>
						{ body }
					</button>
				) }
				{ menu }
			</div>
			{ children }
		</li>
	);
}

/**
 * Where the selection sits in the syllabus, each step a button that selects it.
 * @param root0
 * @param root0.nodes
 * @param root0.labelOf
 * @param root0.onSelect
 */
export function Breadcrumb( { nodes, labelOf, onSelect } ) {
	return (
		<nav
			className="ohmylms-ws-crumbs"
			aria-label={ __( 'Where you are in the syllabus', 'ohmylms' ) }
		>
			<ol>
				{ nodes.map( ( node, index ) => (
					<li key={ node.key }>
						{ index === nodes.length - 1 ? (
							<span aria-current="page">{ labelOf( node ) }</span>
						) : (
							<button
								type="button"
								onClick={ () => onSelect( node.key ) }
							>
								{ labelOf( node ) }
							</button>
						) }
					</li>
				) ) }
			</ol>
		</nav>
	);
}
