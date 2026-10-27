/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import {
	InnerBlocks,
	InspectorControls,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
	CheckboxControl,
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import {
	CANDIDATE_FIELDS,
	paperTextDefaults,
	PaperAnswerSpaceView,
	PaperFormulaSheetView,
	PaperHeaderView,
	PaperInstructionsView,
	PaperPageBreakView,
	PaperSectionView,
	candidateLabel,
	columnsProps,
} from './PaperBlockViews';
import { answerSpace, columnsLayout } from './paperModel.mjs';
import {
	useExamDetails,
	usePaperSettings,
	usePaperTotals,
} from './PaperCanvas';

/** Attribute a block inside a columns section carries to span every column (PaperBlocks::SPAN). */
export const SPAN = 'ohmylmsPaperSpan';
const COLUMNS = 'ohmylms/paper-columns';

/**
 * Editable text fields for a paper block view: plain text with bold and italic only.
 *
 * @param {Object}                  attributes    Block attributes.
 * @param {(patch: Object) => void} setAttributes Attribute setter.
 * @return {import('./PaperBlockViews').TextField} Text field renderer for the shared views.
 */
function editableText( attributes, setAttributes ) {
	return ( key, tagName, className, placeholder ) => (
		<RichText
			tagName={ tagName }
			className={ className }
			value={ attributes[ key ] || '' }
			placeholder={ placeholder }
			allowedFormats={ [ 'core/bold', 'core/italic' ] }
			onChange={ ( value ) => setAttributes( { [ key ]: value } ) }
		/>
	);
}

const text = ( defaultValue = '' ) => ( {
	type: 'string',
	default: defaultValue,
} );

const definitions = {
	'ohmylms/paper-header': {
		title: __( 'Paper header', 'ohmylms' ),
		description: __(
			'Exam title, time, total marks and boxes for the candidate’s details.',
			'ohmylms'
		),
		icon: 'id',
		attributes: {
			school: text(),
			subtitle: text(),
			showTime: { type: 'boolean', default: true },
			showMarks: { type: 'boolean', default: true },
			fields: {
				type: 'array',
				default: [ 'name', 'class', 'number' ],
			},
		},
		edit: function Edit( { attributes, setAttributes } ) {
			const { total } = usePaperTotals();
			const { title, minutes } = useExamDetails();
			// The editor shows version A; other versions differ only in printed order.
			const versions = usePaperSettings().versions;
			const fields = attributes.fields || [];
			return (
				<div { ...useBlockProps() }>
					<PaperHeaderView
						attributes={ attributes }
						title={ title }
						minutes={ minutes }
						total={ total }
						version={ versions > 1 ? 'A' : '' }
						text={ editableText( attributes, setAttributes ) }
					/>
					<InspectorControls>
						<PanelBody title={ __( 'Header', 'ohmylms' ) }>
							<p>
								{ __(
									'The title is the exam title. Time comes from Quiz settings; total marks add up as you add questions.',
									'ohmylms'
								) }
							</p>
							<ToggleControl
								label={ __( 'Show time allowed', 'ohmylms' ) }
								checked={ attributes.showTime !== false }
								onChange={ ( showTime ) =>
									setAttributes( { showTime } )
								}
							/>
							<ToggleControl
								label={ __( 'Show total marks', 'ohmylms' ) }
								checked={ attributes.showMarks !== false }
								onChange={ ( showMarks ) =>
									setAttributes( { showMarks } )
								}
							/>
							{ CANDIDATE_FIELDS.map( ( field ) => (
								<CheckboxControl
									key={ field }
									label={ candidateLabel( field ) }
									checked={ fields.includes( field ) }
									onChange={ ( checked ) =>
										setAttributes( {
											fields: CANDIDATE_FIELDS.filter(
												( item ) =>
													item === field
														? checked
														: fields.includes(
																item
															)
											),
										} )
									}
								/>
							) ) }
						</PanelBody>
					</InspectorControls>
				</div>
			);
		},
	},
	'ohmylms/paper-instructions': {
		title: __( 'Instructions', 'ohmylms' ),
		description: __(
			'Instructions for candidates, as an editable list.',
			'ohmylms'
		),
		icon: 'editor-ul',
		attributes: {
			heading: text(
				paperTextDefaults()[ 'ohmylms/paper-instructions' ].heading
			),
		},
		container: true,
		edit: function Edit( { attributes, setAttributes } ) {
			const inner = useInnerBlocksProps(
				{ className: 'ohmylms-paper-instructions-body' },
				{
					template: [
						[
							'core/list',
							{},
							[
								__( 'Answer all questions.', 'ohmylms' ),
								__(
									'Write your answers in the spaces provided.',
									'ohmylms'
								),
								__(
									'The number of marks for each question or part is shown in brackets [ ].',
									'ohmylms'
								),
							].map( ( content ) => [
								'core/list-item',
								{ content },
							] ),
						],
					],
				}
			);
			return (
				<div { ...useBlockProps() }>
					<PaperInstructionsView
						text={ editableText( attributes, setAttributes ) }
					>
						<div { ...inner } />
					</PaperInstructionsView>
				</div>
			);
		},
	},
	'ohmylms/paper-section': {
		title: __( 'Paper section', 'ohmylms' ),
		description: __(
			'A section heading with the marks of the questions under it.',
			'ohmylms'
		),
		icon: 'heading',
		attributes: {
			title: text(),
			showTotal: { type: 'boolean', default: true },
		},
		edit: function Edit( { attributes, setAttributes, clientId } ) {
			const { sections } = usePaperTotals();
			return (
				<div { ...useBlockProps() }>
					<PaperSectionView
						attributes={ attributes }
						total={ sections[ clientId ] || 0 }
						text={ editableText( attributes, setAttributes ) }
					/>
					<InspectorControls>
						<PanelBody title={ __( 'Section', 'ohmylms' ) }>
							<ToggleControl
								label={ __( 'Show section marks', 'ohmylms' ) }
								help={ __(
									'Adds up the questions after this heading, up to the next section.',
									'ohmylms'
								) }
								checked={ attributes.showTotal !== false }
								onChange={ ( showTotal ) =>
									setAttributes( { showTotal } )
								}
							/>
						</PanelBody>
					</InspectorControls>
				</div>
			);
		},
	},
	[ COLUMNS ]: {
		title: __( 'Columns section', 'ohmylms' ),
		description: __(
			'Lay questions out in two or three columns on the printed paper.',
			'ohmylms'
		),
		icon: 'columns',
		attributes: {
			columns: { type: 'number', default: 2 },
			flow: { type: 'string', default: 'flow' },
			rule: { type: 'boolean', default: false },
		},
		container: true,
		edit: function Edit( { attributes, setAttributes } ) {
			const layout = columnsLayout( attributes );
			const wrapper = columnsProps( attributes );
			const inner = useInnerBlocksProps(
				{ className: 'ohmylms-paper-columns-body' },
				{ renderAppender: InnerBlocks.ButtonBlockAppender }
			);
			return (
				<div { ...useBlockProps( wrapper ) }>
					<div { ...inner } />
					<InspectorControls>
						<PanelBody title={ __( 'Columns', 'ohmylms' ) }>
							<RangeControl
								label={ __( 'Columns', 'ohmylms' ) }
								min={ 1 }
								max={ 3 }
								value={ layout.columns }
								onChange={ ( columns ) =>
									setAttributes( { columns } )
								}
							/>
							<SelectControl
								label={ __( 'Layout', 'ohmylms' ) }
								value={ layout.flow }
								options={ [
									{
										value: 'flow',
										label: __(
											'Flow: fill each column in turn',
											'ohmylms'
										),
									},
									{
										value: 'grid',
										label: __(
											'Side by side: one row at a time',
											'ohmylms'
										),
									},
								] }
								onChange={ ( flow ) =>
									setAttributes( { flow } )
								}
							/>
							{ layout.flow === 'flow' && (
								<ToggleControl
									label={ __(
										'Line between columns',
										'ohmylms'
									) }
									checked={ layout.rule }
									onChange={ ( rule ) =>
										setAttributes( { rule } )
									}
								/>
							) }
							<p>
								{ __(
									'Questions are never split between columns. Select a wide item inside the section to make it span all columns.',
									'ohmylms'
								) }
							</p>
						</PanelBody>
					</InspectorControls>
				</div>
			);
		},
	},
	'ohmylms/paper-page-break': {
		title: __( 'Page break', 'ohmylms' ),
		description: __(
			'Start the next content on a new printed page.',
			'ohmylms'
		),
		icon: 'editor-insertmore',
		attributes: {},
		edit: function Edit() {
			return (
				<div { ...useBlockProps() }>
					<PaperPageBreakView />
				</div>
			);
		},
	},
	'ohmylms/paper-answer-space': {
		title: __( 'Answer space', 'ohmylms' ),
		description: __(
			'Extra space for working: ruled lines, a squared grid or a blank box.',
			'ohmylms'
		),
		icon: 'editor-justify',
		attributes: {
			style: { type: 'string', default: 'lines' },
			lines: { type: 'number', default: 6 },
		},
		edit: function Edit( { attributes, setAttributes } ) {
			const space = answerSpace( attributes );
			return (
				<div { ...useBlockProps() }>
					<PaperAnswerSpaceView attributes={ attributes } />
					<InspectorControls>
						<PanelBody title={ __( 'Answer space', 'ohmylms' ) }>
							<SelectControl
								label={ __( 'Style', 'ohmylms' ) }
								value={ space.style }
								options={ [
									{
										value: 'lines',
										label: __( 'Ruled lines', 'ohmylms' ),
									},
									{
										value: 'grid',
										label: __( 'Squared grid', 'ohmylms' ),
									},
									{
										value: 'blank',
										label: __( 'Blank box', 'ohmylms' ),
									},
								] }
								onChange={ ( style ) =>
									setAttributes( { style } )
								}
							/>
							<RangeControl
								label={ __(
									'Height (lines of 8 mm)',
									'ohmylms'
								) }
								min={ 1 }
								max={ 30 }
								value={ space.lines }
								onChange={ ( lines ) =>
									setAttributes( { lines } )
								}
							/>
						</PanelBody>
					</InspectorControls>
				</div>
			);
		},
	},
	'ohmylms/paper-formula-sheet': {
		title: __( 'Formula sheet', 'ohmylms' ),
		description: __(
			'Formulas, tables or images for candidates, usually on a page of its own.',
			'ohmylms'
		),
		icon: 'editor-table',
		attributes: {
			heading: text(
				paperTextDefaults()[ 'ohmylms/paper-formula-sheet' ].heading
			),
			newPage: { type: 'boolean', default: true },
		},
		container: true,
		edit: function Edit( { attributes, setAttributes } ) {
			const inner = useInnerBlocksProps(
				{ className: 'ohmylms-paper-formula-body' },
				{
					template: [
						[
							'core/paragraph',
							{
								placeholder: __(
									'Add formulas, tables or images.',
									'ohmylms'
								),
							},
						],
					],
				}
			);
			return (
				<div { ...useBlockProps() }>
					<PaperFormulaSheetView
						attributes={ attributes }
						text={ editableText( attributes, setAttributes ) }
					>
						<div { ...inner } />
					</PaperFormulaSheetView>
					<InspectorControls>
						<PanelBody title={ __( 'Formula sheet', 'ohmylms' ) }>
							<ToggleControl
								label={ __( 'Start on a new page', 'ohmylms' ) }
								checked={ attributes.newPage !== false }
								onChange={ ( newPage ) =>
									setAttributes( { newPage } )
								}
							/>
						</PanelBody>
					</InspectorControls>
				</div>
			);
		},
	},
};

/**
 * Let any block inside a columns section span every column.
 *
 * @param {Object} settings Block type settings.
 * @return {Object} Settings with the span attribute.
 */
function addSpanAttribute( settings ) {
	return {
		...settings,
		attributes: {
			...settings.attributes,
			[ SPAN ]: { type: 'boolean', default: false },
		},
	};
}

/**
 * Whether a block sits directly in a flowing columns section.
 *
 * @param {string} clientId Block.
 * @return {boolean} In a flowing columns section.
 */
function useInFlowingColumns( clientId ) {
	return useSelect(
		( getStore ) => {
			const editor = getStore( 'core/block-editor' );
			const parent = editor.getBlockRootClientId( clientId );
			return (
				Boolean( parent ) &&
				editor.getBlockName( parent ) === COLUMNS &&
				columnsLayout( editor.getBlockAttributes( parent ) ).flow ===
					'flow'
			);
		},
		[ clientId ]
	);
}

const withSpanControl = createHigherOrderComponent(
	( BlockEdit ) =>
		function SpanControl( props ) {
			const inColumns = useInFlowingColumns( props.clientId );
			return (
				<>
					<BlockEdit { ...props } />
					{ inColumns && props.isSelected && (
						<InspectorControls>
							<PanelBody
								title={ __( 'Printed paper', 'ohmylms' ) }
							>
								<ToggleControl
									label={ __(
										'Span all columns',
										'ohmylms'
									) }
									help={ __(
										'For wide items such as tables, figures and long answers.',
										'ohmylms'
									) }
									checked={ Boolean(
										props.attributes[ SPAN ]
									) }
									onChange={ ( span ) =>
										props.setAttributes( {
											[ SPAN ]: span,
										} )
									}
								/>
							</PanelBody>
						</InspectorControls>
					) }
				</>
			);
		},
	'withOhmylmsPaperSpanControl'
);

const withSpanClass = createHigherOrderComponent(
	( BlockListBlock ) =>
		function SpanClass( props ) {
			if ( ! props.attributes?.[ SPAN ] ) {
				return <BlockListBlock { ...props } />;
			}
			return (
				<BlockListBlock
					{ ...props }
					className={ `${
						props.className || ''
					} is-ohmylms-paper-span`.trim() }
				/>
			);
		},
	'withOhmylmsPaperSpanClass'
);

/**
 * Register the paper blocks and the span option. Only exam screens call this: the print links
 * are localized only there, and the server registers the blocks for the exam inserter.
 */
export function registerPaperBlocks() {
	addFilter(
		'blocks.registerBlockType',
		'ohmylms/paper-span',
		addSpanAttribute
	);
	addFilter( 'editor.BlockEdit', 'ohmylms/paper-span', withSpanControl );
	addFilter( 'editor.BlockListBlock', 'ohmylms/paper-span', withSpanClass );
	for ( const [ name, definition ] of Object.entries( definitions ) ) {
		if ( getBlockType( name ) ) {
			continue;
		}
		const { container, ...settings } = definition;
		registerBlockType( name, {
			apiVersion: 3,
			category: 'design',
			supports: { html: false, reusable: false },
			...settings,
			// Content lives in attributes; containers keep their inner blocks.
			save: container ? () => <InnerBlocks.Content /> : () => null,
		} );
	}
}

if ( typeof window !== 'undefined' && window.ohmylmsNativeLesson?.printUrls ) {
	registerPaperBlocks();
}
