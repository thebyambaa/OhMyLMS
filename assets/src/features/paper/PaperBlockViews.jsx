/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { answerSpace, columnsLayout } from './paperModel.mjs';

/**
 * Printed views of the paper blocks, shared by the editor (with editable text) and the print page.
 * `text( key, tagName, className, placeholder )` renders one text attribute: a RichText field in the
 * editor, the saved value on the print page.
 */

/**
 * Renders one text attribute of a paper block.
 *
 * @callback TextField
 * @param {string} key         Attribute name.
 * @param {string} tagName     Element to render.
 * @param {string} className   Class name.
 * @param {string} placeholder Editor placeholder.
 * @return {Element|null} Text element.
 */

export const CANDIDATE_FIELDS = [ 'name', 'class', 'number', 'date' ];

/**
 * Default text of paper blocks. Block documents do not store unchanged defaults, so the editor
 * and the print page both read them from here.
 *
 * @return {Object<string, Object<string, string>>} Defaults by block name.
 */
export function paperTextDefaults() {
	return {
		'ohmylms/paper-instructions': {
			heading: __( 'Instructions', 'ohmylms' ),
		},
		'ohmylms/paper-formula-sheet': {
			heading: __( 'Formula sheet', 'ohmylms' ),
		},
	};
}

/**
 * Translated label of a candidate field.
 *
 * @param {string} field Field key.
 * @return {string} Label.
 */
export function candidateLabel( field ) {
	return {
		name: __( 'Name', 'ohmylms' ),
		class: __( 'Class', 'ohmylms' ),
		number: __( 'Candidate number', 'ohmylms' ),
		date: __( 'Date', 'ohmylms' ),
	}[ field ];
}

/**
 * Exam header: school, exam title, details, time and marks, and boxes for the candidate.
 *
 * @param {Object}    props            Properties.
 * @param {Object}    props.attributes Block attributes.
 * @param {string}    props.title      Exam title.
 * @param {number}    props.minutes    Time allowed, 0 for none.
 * @param {number}    props.total      Total marks.
 * @param {TextField} props.text       Text field renderer.
 * @param {string}    props.version    Version letter, empty when the paper has one version.
 * @return {Element} Header.
 */
export function PaperHeaderView( {
	attributes,
	title,
	minutes,
	total,
	text,
	version = '',
} ) {
	const fields = (
		attributes.fields || [ 'name', 'class', 'number' ]
	).filter( ( field ) => CANDIDATE_FIELDS.includes( field ) );
	const details = [
		version &&
			sprintf(
				/* translators: %s: paper version letter. */
				__( 'Version %s', 'ohmylms' ),
				version
			),
		attributes.showTime !== false &&
			minutes > 0 &&
			sprintf(
				/* translators: %d: minutes allowed. */
				_n( 'Time: %d minute', 'Time: %d minutes', minutes, 'ohmylms' ),
				minutes
			),
		attributes.showMarks !== false &&
			total > 0 &&
			sprintf(
				/* translators: %d: total marks. */
				_n( 'Total: %d mark', 'Total: %d marks', total, 'ohmylms' ),
				total
			),
	].filter( Boolean );
	return (
		<div className="ohmylms-paper-header">
			<div className="ohmylms-paper-header-main">
				{ text(
					'school',
					'p',
					'ohmylms-paper-header-school',
					__( 'School or organisation', 'ohmylms' )
				) }
				<p className="ohmylms-paper-header-title">{ title }</p>
				{ text(
					'subtitle',
					'p',
					'ohmylms-paper-header-subtitle',
					__( 'Subject, paper code or session', 'ohmylms' )
				) }
				{ details.length > 0 && (
					<p className="ohmylms-paper-header-details">
						{ details.join( ' · ' ) }
					</p>
				) }
			</div>
			{ fields.length > 0 && (
				<div className="ohmylms-paper-header-fields">
					{ fields.map( ( field ) => (
						<div
							className={ `ohmylms-paper-header-field is-${ field }` }
							key={ field }
						>
							<span>{ candidateLabel( field ) }</span>
							{ field === 'number' ? (
								<span
									className="ohmylms-paper-boxes"
									aria-hidden="true"
								>
									{ [ 0, 1, 2, 3, 4, 5 ].map( ( box ) => (
										<span key={ box } />
									) ) }
								</span>
							) : (
								<span
									className="ohmylms-paper-field-line"
									aria-hidden="true"
								/>
							) }
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}

/**
 * Instructions: a heading and the instruction list.
 *
 * @param {Object}    props          Properties.
 * @param {TextField} props.text     Text field renderer.
 * @param {Element}   props.children Instruction content.
 * @return {Element} Instructions.
 */
export function PaperInstructionsView( { text, children } ) {
	return (
		<div className="ohmylms-paper-instructions">
			{ text(
				'heading',
				'p',
				'ohmylms-paper-instructions-heading',
				__( 'Instructions', 'ohmylms' )
			) }
			{ children }
		</div>
	);
}

/**
 * Section heading with the marks of its questions.
 *
 * @param {Object}    props            Properties.
 * @param {Object}    props.attributes Block attributes.
 * @param {number}    props.total      Marks up to the next section.
 * @param {TextField} props.text       Text field renderer.
 * @return {Element} Section heading.
 */
export function PaperSectionView( { attributes, total, text } ) {
	return (
		<div className="ohmylms-paper-section">
			{ text(
				'title',
				'p',
				'ohmylms-paper-section-title',
				__( 'Section A', 'ohmylms' )
			) }
			{ attributes.showTotal !== false && total > 0 && (
				<span className="ohmylms-paper-section-total">
					{ sprintf(
						/* translators: %d: marks in the section. */
						_n( '[%d mark]', '[%d marks]', total, 'ohmylms' ),
						total
					) }
				</span>
			) }
		</div>
	);
}

/**
 * Class names and style of a columns section.
 *
 * @param {Object} attributes Block attributes.
 * @return {{className: string, style: Object}} Wrapper properties.
 */
export function columnsProps( attributes ) {
	const layout = columnsLayout( attributes );
	return {
		className: `ohmylms-paper-columns is-${ layout.flow }${
			layout.rule ? ' has-rule' : ''
		}`,
		style: { '--ohmylms-paper-columns': layout.columns },
	};
}

/**
 * Page break; the label shows only in the editor.
 *
 * @return {Element} Page break.
 */
export function PaperPageBreakView() {
	return (
		<div className="ohmylms-paper-page-break">
			<span>{ __( 'Page break', 'ohmylms' ) }</span>
		</div>
	);
}

/**
 * Extra answer space: ruled lines, a squared grid or a blank box.
 *
 * @param {Object} props            Properties.
 * @param {Object} props.attributes Block attributes.
 * @return {Element} Answer space.
 */
export function PaperAnswerSpaceView( { attributes } ) {
	const space = answerSpace( attributes );
	return (
		<div
			className={ `ohmylms-paper-answer-space is-${ space.style }` }
			style={ { '--ohmylms-paper-lines': space.lines } }
			aria-hidden="true"
		/>
	);
}

/**
 * Formula sheet: a heading and free content, starting on a new page when set.
 *
 * @param {Object}    props            Properties.
 * @param {Object}    props.attributes Block attributes.
 * @param {TextField} props.text       Text field renderer.
 * @param {Element}   props.children   Sheet content.
 * @return {Element} Formula sheet.
 */
export function PaperFormulaSheetView( { attributes, text, children } ) {
	return (
		<div
			className={ `ohmylms-paper-formula-sheet${
				attributes.newPage !== false ? ' is-new-page' : ''
			}` }
		>
			{ text(
				'heading',
				'p',
				'ohmylms-paper-formula-heading',
				__( 'Formula sheet', 'ohmylms' )
			) }
			{ children }
		</div>
	);
}
