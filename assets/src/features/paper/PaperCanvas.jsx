/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { select, useDispatch, useSelect } from '@wordpress/data';
import {
	Button,
	Notice,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { PAPER_STORE } from './store';
import { saveQuizForPreview } from '../learning/quizPreview.mjs';
import {
	PAPER_VERSIONS,
	limitMinutes,
	paginate,
	paperSettings,
	questionLabel,
	questionMarks,
	sectionTotals,
	sheetMetrics,
} from './paperModel.mjs';

/** Space between sheets on the canvas, in CSS pixels. */
const SHEET_GAP = 32;
const QUESTION_BLOCKS = [
	'ohmylms/shared-multiple-choice',
	'ohmylms/quiz-question-reference',
];

/**
 * Normalized paper settings of the open exam, including unsaved edits.
 *
 * @return {Object} Paper settings.
 */
export function usePaperSettings() {
	const raw = useSelect(
		( getStore ) =>
			getStore( 'core/editor' ).getEditedPostAttribute( 'ohmylms_quiz' )
				?.settings?.paper,
		[]
	);
	return paperSettings( raw );
}

/**
 * Whether the canvas currently shows the printable paper. Only exams and quizzes have one.
 *
 * @return {boolean} Paper view.
 */
export function usePaperView() {
	return useSelect(
		// Shared question editors also run in the LMS app, which has no post editor store.
		( getStore ) =>
			getStore( 'core/editor' )?.getCurrentPostType?.() ===
				'ohmylms-quiz' && getStore( PAPER_STORE ).isPaperView(),
		[]
	);
}

/**
 * Every block in document order, nested blocks in place. Core's getClientIdsWithDescendants()
 * lists top-level blocks before nested ones, which would misnumber questions in a section.
 *
 * @param {Object} editor Block editor selectors.
 * @param {string} root   Parent block, empty for the document.
 * @return {string[]} Block IDs.
 */
function documentOrder( editor, root = '' ) {
	return editor
		.getBlockOrder( root )
		.flatMap( ( id ) => [ id, ...documentOrder( editor, id ) ] );
}

/**
 * The printed number of a question block: its position among numbered questions in
 * document order, nested blocks included.
 *
 * @param {string} clientId Question block.
 * @return {number} Zero-based index, or -1 for an unnumbered statement.
 */
export function usePaperIndex( clientId ) {
	return useSelect(
		( getStore ) => {
			const editor = getStore( 'core/block-editor' );
			const paper = getStore( PAPER_STORE );
			let index = 0;
			for ( const id of documentOrder( editor ) ) {
				if ( ! QUESTION_BLOCKS.includes( editor.getBlockName( id ) ) ) {
					continue;
				}
				const statement = paper.getQuestionType( id ) === 'statement';
				if ( id === clientId ) {
					return statement ? -1 : index;
				}
				index += statement ? 0 : 1;
			}
			return -1;
		},
		[ clientId ]
	);
}

/**
 * Everything a question block needs to show its printed form, and registration of its type
 * and marks so numbering skips statements and headers and sections can total the marks.
 *
 * @param {string} clientId Question block.
 * @param {Object} question Editor question, or null while loading.
 * @return {{view: boolean, label: string, settings: Object, answers: boolean}} Paper state.
 */
export function usePaperQuestion( clientId, question ) {
	const type = question?.settings?.type || '';
	const marks = question ? questionMarks( question ) : 0;
	const view = usePaperView();
	const settings = usePaperSettings();
	const index = usePaperIndex( clientId );
	const answers = useSelect(
		( getStore ) => getStore( PAPER_STORE ).showsAnswers(),
		[]
	);
	const { setQuestion } = useDispatch( PAPER_STORE );
	useEffect( () => {
		setQuestion( clientId, type, marks );
	}, [ clientId, type, marks, setQuestion ] );
	return {
		view,
		settings,
		answers,
		label: index < 0 ? '' : questionLabel( index, settings ),
	};
}

/**
 * Where each person's last paper view choice for an exam is remembered on this device.
 *
 * @param {number} postId Exam ID.
 * @return {string} Storage key.
 */
const viewKey = ( postId ) => `ohmylms-paper-view:${ postId }`;

/**
 * Switch paper view and remember the choice for this exam.
 *
 * @return {(view: boolean) => void} Setter.
 */
function useSetPaperView() {
	const postId = useSelect(
		( getStore ) => getStore( 'core/editor' ).getCurrentPostId(),
		[]
	);
	const { setView } = useDispatch( PAPER_STORE );
	return ( next ) => {
		setView( next );
		try {
			window.localStorage.setItem( viewKey( postId ), next ? '1' : '0' );
		} catch {
			// The view still works for this session without storage.
		}
	};
}

/**
 * Totals for paper blocks: the exam's total marks and each section's marks, in document order.
 *
 * @return {{total: number, sections: Object<string, number>}} Marks.
 */
export function usePaperTotals() {
	return useSelect( ( getStore ) => {
		const editor = getStore( 'core/block-editor' );
		const paper = getStore( PAPER_STORE );
		const entries = [];
		for ( const id of documentOrder( editor ) ) {
			const name = editor.getBlockName( id );
			if ( QUESTION_BLOCKS.includes( name ) ) {
				entries.push( {
					kind: 'question',
					marks: paper.getQuestionMarks( id ),
				} );
			} else if ( name === 'ohmylms/paper-section' ) {
				entries.push( { kind: 'section', key: id } );
			}
		}
		return {
			total: entries.reduce(
				( sum, entry ) => sum + ( entry.marks || 0 ),
				0
			),
			sections: sectionTotals( entries ),
		};
	}, [] );
}

/**
 * The exam title and minutes allowed, including unsaved edits.
 *
 * @return {{title: string, minutes: number}} Exam details.
 */
export function useExamDetails() {
	return useSelect( ( getStore ) => {
		const editor = getStore( 'core/editor' );
		return {
			title: editor.getEditedPostAttribute( 'title' ) || '',
			minutes: limitMinutes(
				editor.getEditedPostAttribute( 'ohmylms_quiz' )?.settings
					?.time_limit
			),
		};
	}, [] );
}

/**
 * Header button that switches the canvas between normal editing and the paper sheet.
 *
 * @return {Element} Toggle button.
 */
export function PaperViewToggle() {
	const view = usePaperView();
	const setView = useSetPaperView();
	return (
		<Button
			variant="tertiary"
			icon="media-document"
			isPressed={ view }
			aria-pressed={ view }
			onClick={ () => setView( ! view ) }
			label={ __( 'Show the exam as a printable paper', 'ohmylms' ) }
		>
			{ __( 'Paper view', 'ohmylms' ) }
		</Button>
	);
}

/**
 * Locate the editor canvas: the iframe document when core iframes it, else the page itself.
 *
 * @return {{doc: Document, wrapper: Element, root: Element}|null} Canvas parts.
 */
function findCanvas() {
	const frame = document.querySelector( 'iframe[name="editor-canvas"]' );
	const doc = frame ? frame.contentDocument : document;
	const wrapper = doc?.querySelector( '.editor-styles-wrapper' );
	const root = wrapper?.querySelector( '.is-root-container' );
	return root ? { doc, wrapper, root } : null;
}

/**
 * Lay the open canvas out as sheets: size and margins from the paper settings, blocks that
 * would straddle a sheet edge moved to the next sheet, and a page number on each sheet.
 *
 * @param {Object}  canvas   Canvas parts from findCanvas().
 * @param {Object}  settings Normalized paper settings.
 * @param {boolean} answers  Whether the answer key is shown.
 * @return {() => void} Cleanup restoring the normal canvas.
 */
function layoutSheets( canvas, settings, answers ) {
	const { doc, wrapper, root } = canvas;
	const view = doc.defaultView;
	const sheet = { ...sheetMetrics( settings ), gap: SHEET_GAP };
	const style = doc.createElement( 'style' );
	style.id = 'ohmylms-paper-layout';
	doc.head.appendChild( style );
	const overlay = doc.createElement( 'div' );
	overlay.className = 'ohmylms-paper-pages';
	overlay.setAttribute( 'aria-hidden', 'true' );
	doc.body.appendChild( overlay );
	wrapper.classList.add( 'is-ohmylms-paper' );
	wrapper.classList.toggle( 'is-ohmylms-paper-key', answers );
	wrapper.classList.toggle(
		'is-ohmylms-paper-serif',
		'serif' === settings.font
	);
	wrapper.classList.toggle( 'has-ohmylms-paper-marks', settings.show_marks );
	const vars = {
		'--ohmylms-paper-width': `${ sheet.width }px`,
		'--ohmylms-paper-height': `${ sheet.height }px`,
		'--ohmylms-paper-margin': `${ sheet.margin }px`,
		'--ohmylms-paper-gap': `${ sheet.gap }px`,
		'--ohmylms-paper-font-size': `${ settings.font_size }pt`,
	};
	for ( const [ name, value ] of Object.entries( vars ) ) {
		wrapper.style.setProperty( name, value );
	}
	let frame = 0;
	let css = '';
	const measure = () => {
		frame = 0;
		const blocks = [ ...root.children ].filter( ( child ) =>
			child.hasAttribute( 'data-block' )
		);
		const afters = blocks.map(
			( block ) =>
				parseFloat( view.getComputedStyle( block ).marginBottom ) || 0
		);
		const heights = blocks.map(
			( block, index ) => block.offsetHeight + afters[ index ]
		);
		// A page break ends its sheet; a formula sheet set to start a new page ends the one before.
		const breaks = blocks.map(
			( block, index ) =>
				block.getAttribute( 'data-type' ) ===
					'ohmylms/paper-page-break' ||
				Boolean(
					blocks[ index + 1 ]?.querySelector(
						':scope > .ohmylms-paper-formula-sheet.is-new-page'
					)
				)
		);
		const layout = paginate( heights, sheet, breaks );
		const rules = blocks.map( ( block, index ) => {
			const id = block.getAttribute( 'data-block' );
			const spacer = layout.spacers[ index ];
			// Vertical margins collapse, so the spacer also restates the previous block's margin.
			const before = spacer + ( index ? afters[ index - 1 ] : 0 );
			return [
				spacer
					? `[data-block="${ id }"]{margin-top:${ before }px !important}`
					: '',
				layout.overflow[ index ]
					? `[data-block="${ id }"]{outline:2px dashed #d63638;outline-offset:4px}`
					: '',
			].join( '' );
		} );
		const pitch = sheet.height + sheet.gap;
		rules.push(
			`.is-ohmylms-paper .is-root-container{min-height:${
				layout.pages * pitch - sheet.gap
			}px}`
		);
		const next = rules.join( '' );
		if ( next !== css ) {
			css = next;
			style.textContent = next;
		}
		const box = root.getBoundingClientRect();
		const left = box.left + view.scrollX;
		const top = box.top + view.scrollY;
		overlay.replaceChildren(
			...Array.from( { length: layout.pages }, ( _, page ) => {
				const label = doc.createElement( 'span' );
				label.className = 'ohmylms-paper-page-number';
				label.style.left = `${ left }px`;
				label.style.width = `${ box.width }px`;
				label.style.top = `${
					top + page * pitch + sheet.height - sheet.margin / 2
				}px`;
				label.textContent = settings.page_numbers
					? sprintf(
							/* translators: 1: page number, 2: page count. */
							__( 'Page %1$d of %2$d', 'ohmylms' ),
							page + 1,
							layout.pages
						)
					: '';
				return label;
			} )
		);
	};
	const schedule = () => {
		if ( ! frame ) {
			frame = view.requestAnimationFrame( measure );
		}
	};
	const sizes = new view.ResizeObserver( schedule );
	const watch = () => {
		sizes.disconnect();
		sizes.observe( root );
		for ( const child of root.children ) {
			sizes.observe( child );
		}
		schedule();
	};
	const children = new view.MutationObserver( watch );
	children.observe( root, { childList: true } );
	view.addEventListener( 'resize', schedule );
	watch();
	return () => {
		if ( frame ) {
			view.cancelAnimationFrame( frame );
		}
		sizes.disconnect();
		children.disconnect();
		view.removeEventListener( 'resize', schedule );
		style.remove();
		overlay.remove();
		wrapper.classList.remove(
			'is-ohmylms-paper',
			'is-ohmylms-paper-key',
			'is-ohmylms-paper-serif',
			'has-ohmylms-paper-marks'
		);
		for ( const name of Object.keys( vars ) ) {
			wrapper.style.removeProperty( name );
		}
	};
}

/**
 * Keep the editor canvas laid out as sheets while paper view is on, including after core
 * replaces the canvas iframe.
 *
 * @return {null} Renders nothing.
 */
export function PaperCanvas() {
	const view = usePaperView();
	const answers = useSelect(
		( getStore ) => getStore( PAPER_STORE ).showsAnswers(),
		[]
	);
	const settings = usePaperSettings();
	const postId = useSelect(
		( getStore ) => getStore( 'core/editor' ).getCurrentPostId(),
		[]
	);
	const { setView } = useDispatch( PAPER_STORE );
	const started = useRef( false );
	const key = viewKey( postId );
	useEffect( () => {
		if ( started.current || ! postId ) {
			return;
		}
		started.current = true;
		let stored = null;
		try {
			stored = window.localStorage.getItem( key );
		} catch {
			// Storage may be unavailable; fall back to the exam's default view.
		}
		setView( null === stored ? settings.default_view : '1' === stored );
	}, [ postId, key, settings.default_view, setView ] );
	const signature = JSON.stringify( settings );
	useEffect( () => {
		if ( ! view ) {
			return undefined;
		}
		let canvas = null;
		let cleanup = null;
		const attach = () => {
			const found = findCanvas();
			if ( found?.root === canvas?.root ) {
				return;
			}
			cleanup?.();
			cleanup = null;
			canvas = found;
			if ( canvas ) {
				cleanup = layoutSheets(
					canvas,
					JSON.parse( signature ),
					answers
				);
			}
		};
		// Core mounts and replaces the canvas iframe and its root container asynchronously.
		const timer = window.setInterval( attach, 500 );
		attach();
		return () => {
			window.clearInterval( timer );
			cleanup?.();
		};
	}, [ view, answers, signature ] );
	return null;
}

/**
 * Print the saved paper or its answer key. Unsaved changes are saved first, because the print
 * page reads the saved exam; the tab opens at once so the browser does not block it.
 *
 * @param  root0
 * @param  root0.versions
 * @return {Element} Print buttons.
 */
function PrintButtons( { versions } ) {
	const urls = window.ohmylmsNativeLesson?.printUrls;
	const { savePost } = useDispatch( 'core/editor' );
	const [ error, setError ] = useState( '' );
	const [ chosen, setVersion ] = useState( 'A' );
	if ( ! urls ) {
		return null;
	}
	const version = PAPER_VERSIONS.indexOf( chosen ) < versions ? chosen : 'A';
	/**
	 * Print link for a variant of the chosen version; the nonce covers every variant of the exam.
	 *
	 * @param {string} variant Print variant.
	 * @return {string} Print URL.
	 */
	const link = ( variant ) => {
		const url = new URL( urls.paper );
		url.searchParams.set( 'variant', variant );
		if ( version !== 'A' ) {
			url.searchParams.set( 'version', version );
		}
		return url.href;
	};
	const open = async ( url ) => {
		const tab = window.open( 'about:blank', '_blank' );
		if ( tab ) {
			tab.opener = null;
		}
		setError( '' );
		try {
			const ready = await saveQuizForPreview( {
				editor: select( 'core/editor' ),
				savePost,
			} );
			if ( ! ready ) {
				throw new Error( 'paper-not-saved' );
			}
			const target = `${ url }&print=1`;
			if ( tab ) {
				tab.location.replace( target );
			} else {
				window.location.assign( target );
			}
		} catch {
			tab?.close();
			setError(
				__(
					'The exam could not be saved for printing. Complete any unfinished questions and try again.',
					'ohmylms'
				)
			);
		}
	};
	return (
		<div className="ohmylms-paper-print-actions">
			{ versions > 1 && (
				<SelectControl
					label={ __( 'Version to print', 'ohmylms' ) }
					value={ version }
					options={ PAPER_VERSIONS.slice( 0, versions ).map(
						( letter ) => ( {
							value: letter,
							label: sprintf(
								/* translators: %s: version letter. */
								__( 'Version %s', 'ohmylms' ),
								letter
							),
						} )
					) }
					onChange={ setVersion }
				/>
			) }
			<Button variant="primary" onClick={ () => open( link( 'paper' ) ) }>
				{ __( 'Print paper', 'ohmylms' ) }
			</Button>
			<Button variant="secondary" onClick={ () => open( link( 'key' ) ) }>
				{ __( 'Print answer key', 'ohmylms' ) }
			</Button>
			<Button
				variant="secondary"
				onClick={ () => open( link( 'sheet' ) ) }
			>
				{ __( 'Print answer sheet', 'ohmylms' ) }
			</Button>
			<Button
				variant="secondary"
				onClick={ () => open( link( 'sheet-key' ) ) }
			>
				{ __( 'Print marking sheet', 'ohmylms' ) }
			</Button>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
		</div>
	);
}

/**
 * Document sidebar panel with the paper layout settings, saved with the exam.
 *
 * @return {Element} Settings controls.
 */
export function PaperSettingsPanel() {
	const value = useSelect(
		( getStore ) =>
			getStore( 'core/editor' ).getEditedPostAttribute(
				'ohmylms_quiz'
			) || {},
		[]
	);
	const view = usePaperView();
	const answers = useSelect(
		( getStore ) => getStore( PAPER_STORE ).showsAnswers(),
		[]
	);
	const setView = useSetPaperView();
	const { setAnswers } = useDispatch( PAPER_STORE );
	const { editPost } = useDispatch( 'core/editor' );
	const paper = paperSettings( value.settings?.paper );
	const set = ( field, next ) =>
		editPost( {
			ohmylms_quiz: {
				...value,
				settings: {
					...( value.settings || {} ),
					paper: { ...paper, [ field ]: next },
				},
			},
		} );
	return (
		<>
			<PrintButtons versions={ paper.versions } />
			<ToggleControl
				label={ __( 'Paper view', 'ohmylms' ) }
				help={ __(
					'Show the exam on paper-sized sheets, as it prints. Select a question to edit it in the sidebar.',
					'ohmylms'
				) }
				checked={ view }
				onChange={ setView }
			/>
			<ToggleControl
				label={ __( 'Show answer key', 'ohmylms' ) }
				help={ __(
					'Only on this screen. The student paper never includes answers.',
					'ohmylms'
				) }
				checked={ answers }
				disabled={ ! view }
				onChange={ setAnswers }
			/>
			<SelectControl
				label={ __( 'Paper size', 'ohmylms' ) }
				value={ paper.size }
				options={ [
					{ value: 'a4', label: __( 'A4', 'ohmylms' ) },
					{ value: 'letter', label: __( 'US Letter', 'ohmylms' ) },
				] }
				onChange={ ( next ) => set( 'size', next ) }
			/>
			<RangeControl
				label={ __( 'Margins (mm)', 'ohmylms' ) }
				min={ 10 }
				max={ 30 }
				value={ paper.margin }
				onChange={ ( next ) => set( 'margin', next ) }
			/>
			<RangeControl
				label={ __( 'Font size (pt)', 'ohmylms' ) }
				min={ 9 }
				max={ 14 }
				value={ paper.font_size }
				onChange={ ( next ) => set( 'font_size', next ) }
			/>
			<SelectControl
				label={ __( 'Font', 'ohmylms' ) }
				value={ paper.font }
				options={ [
					{ value: 'sans', label: __( 'Sans serif', 'ohmylms' ) },
					{ value: 'serif', label: __( 'Serif', 'ohmylms' ) },
				] }
				onChange={ ( next ) => set( 'font', next ) }
			/>
			<SelectControl
				label={ __( 'Question numbers', 'ohmylms' ) }
				value={ paper.numbering }
				options={ [
					{ value: 'number', label: __( '1, 2, 3', 'ohmylms' ) },
					{ value: 'q', label: __( 'Q1, Q2, Q3', 'ohmylms' ) },
				] }
				onChange={ ( next ) => set( 'numbering', next ) }
			/>
			<ToggleControl
				label={ __( 'Show marks', 'ohmylms' ) }
				checked={ paper.show_marks }
				onChange={ ( next ) => set( 'show_marks', next ) }
			/>
			<RangeControl
				label={ __( 'Answer lines per mark', 'ohmylms' ) }
				help={ __( 'For long answers and written parts.', 'ohmylms' ) }
				min={ 1 }
				max={ 4 }
				value={ paper.lines_per_mark }
				onChange={ ( next ) => set( 'lines_per_mark', next ) }
			/>
			<RangeControl
				label={ __( 'Versions', 'ohmylms' ) }
				help={ __(
					'Version A is the paper as shown here. Versions B to D shuffle the questions within each section and the options of choice questions, each with its own answer key.',
					'ohmylms'
				) }
				min={ 1 }
				max={ 4 }
				value={ paper.versions }
				onChange={ ( next ) => set( 'versions', next ) }
			/>
			<ToggleControl
				label={ __( 'Page numbers', 'ohmylms' ) }
				checked={ paper.page_numbers }
				onChange={ ( next ) => set( 'page_numbers', next ) }
			/>
			<ToggleControl
				label={ __( 'Open this exam in paper view', 'ohmylms' ) }
				help={ __(
					'For exams made to be printed. Each person’s last choice on this screen still wins.',
					'ohmylms'
				) }
				checked={ paper.default_view }
				onChange={ ( next ) => set( 'default_view', next ) }
			/>
		</>
	);
}
