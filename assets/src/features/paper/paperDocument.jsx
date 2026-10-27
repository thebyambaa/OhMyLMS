/** @jsx createElement */
import { createElement, RawHTML } from '@wordpress/element';
import { questionPrompt } from '../question-editor/questionPrompt.mjs';
import { PaperQuestion } from './PaperQuestion';
import {
	PaperAnswerSpaceView,
	PaperFormulaSheetView,
	PaperHeaderView,
	PaperInstructionsView,
	PaperPageBreakView,
	PaperSectionView,
	columnsProps,
	paperTextDefaults,
} from './PaperBlockViews';
import {
	limitMinutes,
	questionLabels,
	questionMarks,
	sectionTotals,
} from './paperModel.mjs';

/*
 * The paper document shared by the print page and the student's Paper View: core blocks, paper
 * blocks and questions in document order, with the renderers the editor's paper view uses.
 *
 * A context may set `wrapQuestion( item, element )` to place each question, and `continuous` to
 * leave out page breaks.
 */

/**
 * Numbers, section marks and the total, worked out in document order across nested blocks.
 *
 * @param {Array}  items    Print items.
 * @param {Object} settings Normalized paper settings.
 * @return {{labels: Map, sections: Map, total: number, questions: Array}} Derived values by
 * item, and the questions in printed order.
 */
export function annotate( items, settings ) {
	const questions = [];
	const sections = [];
	const entries = [];
	const visit = ( list ) => {
		for ( const item of list ) {
			if ( item.kind === 'question' ) {
				questions.push( item );
				entries.push( {
					kind: 'question',
					marks: questionMarks( item.question ),
				} );
			} else if ( item.kind === 'layout' ) {
				visit( item.items || [] );
			} else if ( item.kind === 'paper' ) {
				if ( item.name === 'ohmylms/paper-section' ) {
					entries.push( {
						kind: 'section',
						key: String( sections.push( item ) - 1 ),
					} );
				}
				visit( item.items || [] );
			}
		}
	};
	visit( items );
	const labels = questionLabels(
		questions.map( ( item ) => item.question.settings?.type || '' ),
		settings
	);
	const totals = sectionTotals( entries );
	return {
		labels: new Map(
			questions.map( ( item, index ) => [ item, labels[ index ] ] )
		),
		sections: new Map(
			sections.map( ( item, index ) => [ item, totals[ index ] || 0 ] )
		),
		total: entries.reduce(
			( sum, entry ) => sum + ( entry.marks || 0 ),
			0
		),
		questions,
	};
}

/**
 * Saved text of a paper block, with the block's defaults. The server keeps only bold, italic and
 * line breaks in these values.
 *
 * @param {string} name  Block name.
 * @param {Object} attrs Saved attributes.
 * @return {import('./PaperBlockViews').TextField} Text renderer.
 */
function savedText( name, attrs ) {
	const values = { ...( paperTextDefaults()[ name ] || {} ), ...attrs };
	return ( key, Tag, className ) =>
		values[ key ] ? (
			<Tag
				className={ className }
				dangerouslySetInnerHTML={ { __html: values[ key ] } }
			/>
		) : null;
}

/**
 * Printed items, each in a block wrapper that keeps it on one page and may span columns.
 *
 * @param {Object} props         Properties.
 * @param {Array}  props.items   Print items.
 * @param {Object} props.context Paper data, settings and derived values.
 * @return {Element[]} Printed blocks.
 */
export function Items( { items, context } ) {
	return items.map( ( item, index ) => (
		<div
			className={ `ohmylms-print-block${
				item.span ? ' is-ohmylms-paper-span' : ''
			}` }
			key={ index }
		>
			<Item item={ item } context={ context } />
		</div>
	) );
}

/**
 * One printed item: core block HTML, a question or a paper block.
 *
 * @param {Object} props         Properties.
 * @param {Object} props.item    Print item.
 * @param {Object} props.context Paper data, settings and derived values.
 * @return {Element|null} Printed item.
 */
function Item( { item, context } ) {
	if ( item.kind === 'html' ) {
		return <RawHTML>{ item.html }</RawHTML>;
	}
	if ( item.kind === 'layout' ) {
		// Gutenberg columns holding questions: side by side on the sheet.
		return (
			<div className="ohmylms-print-columns">
				{ ( item.items || [] ).map( ( column, index ) => (
					<div
						className="ohmylms-print-column"
						key={ index }
						style={
							column.attrs?.width
								? { flex: `0 0 ${ column.attrs.width }` }
								: undefined
						}
					>
						<Items
							items={ column.items || [] }
							context={ context }
						/>
					</div>
				) ) }
			</div>
		);
	}
	if ( item.kind === 'question' ) {
		const question = (
			<PaperQuestion
				question={ item.question }
				prompt={ questionPrompt( item.question ) }
				label={
					item.preview_label ?? context.derived.labels.get( item )
				}
				settings={ context.settings }
				showAnswers={ context.showAnswers }
				version={ context.version }
			/>
		);
		return context.wrapQuestion
			? context.wrapQuestion( item, question )
			: question;
	}
	const attrs = item.attrs || {};
	const text = savedText( item.name, attrs );
	const children = <Items items={ item.items || [] } context={ context } />;
	switch ( item.name ) {
		case 'ohmylms/paper-header':
			return (
				<PaperHeaderView
					attributes={ attrs }
					title={ context.data.title }
					minutes={ limitMinutes( context.data.timeLimit ) }
					version={
						context.settings.versions > 1 ? context.version : ''
					}
					total={ context.derived.total }
					text={ text }
				/>
			);
		case 'ohmylms/paper-instructions':
			return (
				<PaperInstructionsView text={ text }>
					{ children }
				</PaperInstructionsView>
			);
		case 'ohmylms/paper-section':
			return (
				<PaperSectionView
					attributes={ attrs }
					total={ context.derived.sections.get( item ) }
					text={ text }
				/>
			);
		case 'ohmylms/paper-columns': {
			const wrapper = columnsProps( attrs );
			return (
				<div className={ wrapper.className } style={ wrapper.style }>
					<div className="ohmylms-paper-columns-body">
						{ children }
					</div>
				</div>
			);
		}
		case 'ohmylms/paper-page-break':
			// A continuous paper on screen has no pages to break.
			return context.continuous ? null : <PaperPageBreakView />;
		case 'ohmylms/paper-answer-space':
			return <PaperAnswerSpaceView attributes={ attrs } />;
		case 'ohmylms/paper-formula-sheet':
			return (
				<PaperFormulaSheetView attributes={ attrs } text={ text }>
					{ children }
				</PaperFormulaSheetView>
			);
		default:
			return null;
	}
}
