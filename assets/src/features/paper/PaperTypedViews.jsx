/** @jsx createElement */
import { createElement, RawHTML } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { VisualFigure } from './PaperVisualViews';

/**
 * Printed bodies of the question types built in paperTypes.mjs. The statement and marks are
 * printed by PaperQuestion; these add the answer space, and the key when the model has one.
 */

/**
 * Answer key lines.
 *
 * @param {Object}   props       Properties.
 * @param {string[]} props.lines Key lines.
 * @return {Element|null} Key.
 */
function KeyLines( { lines } ) {
	if ( ! lines?.length ) {
		return null;
	}
	return (
		<div className="ohmylms-print-key">
			{ lines.map( ( line, index ) => (
				<p key={ index }>{ line }</p>
			) ) }
		</div>
	);
}

/**
 * Option text with an optional image.
 *
 * @param {Object} props      Properties.
 * @param {Object} props.item Item with text and image.
 * @return {Element} Option content.
 */
function ItemContent( { item } ) {
	return (
		<span className="ohmylms-print-option">
			<RawHTML>{ item.text }</RawHTML>
			{ item.image && <img src={ item.image } alt="" /> }
		</span>
	);
}

/**
 * A blank with its label, for table cells.
 *
 * @param {Object} props       Properties.
 * @param {string} props.label Blank label.
 * @param {string} props.unit  Unit after the blank.
 * @return {Element} Blank.
 */
function Blank( { label, unit } ) {
	return (
		<>
			<span className="ohmylms-print-blank">({ label })</span>
			{ unit && ` ${ unit }` }
		</>
	);
}

/**
 * Body of a typed question.
 *
 * @param {Object} props       Properties.
 * @param {Object} props.paper Paper model.
 * @return {Element|null} Body.
 */
export function TypedBody( { paper } ) {
	switch ( paper.layout ) {
		case 'matching':
			return (
				<>
					<div className="ohmylms-print-matching">
						<ol>
							{ paper.left.map( ( item ) => (
								<li key={ item.number }>
									<span className="ohmylms-print-letter">
										{ item.number }
									</span>
									<ItemContent item={ item } />
								</li>
							) ) }
						</ol>
						<ol>
							{ paper.right.map( ( item ) => (
								<li key={ item.letter }>
									<span className="ohmylms-print-letter">
										{ item.letter }
									</span>
									<ItemContent item={ item } />
								</li>
							) ) }
						</ol>
					</div>
					<p className="ohmylms-print-boxes">
						{ paper.left.map( ( item ) => (
							<span key={ item.number }>
								{ item.number }
								<span className="ohmylms-print-box" />
							</span>
						) ) }
					</p>
					<KeyLines lines={ paper.key } />
				</>
			);
		case 'reorder':
			return (
				<>
					<ol className="ohmylms-print-options is-ordering">
						{ paper.items.map( ( item ) => (
							<li key={ item.letter }>
								<span className="ohmylms-print-box" />
								<span className="ohmylms-print-letter">
									{ item.letter }
								</span>
								<ItemContent item={ item } />
							</li>
						) ) }
					</ol>
					<p className="ohmylms-print-instruction">
						{ __(
							'Number the boxes to show the correct order.',
							'ohmylms'
						) }
					</p>
					<KeyLines lines={ paper.key } />
				</>
			);
		case 'blanks':
			return null;
		case 'visual':
			return <VisualFigure figure={ paper.figure } />;
		case 'dropdown':
			return (
				<>
					<ol className="ohmylms-print-slots">
						{ paper.slots.map( ( slot ) => (
							<li key={ slot.id }>
								<span className="ohmylms-print-letter">
									({ slot.number })
								</span>
								{ slot.choices.map( ( choice, index ) => (
									<span
										className="ohmylms-print-choice"
										key={ index }
									>
										<span
											className={ `ohmylms-print-bubble${
												slot.answers?.includes( choice )
													? ' is-filled'
													: ''
											}` }
											aria-hidden="true"
										/>
										{ choice }
									</span>
								) ) }
							</li>
						) ) }
					</ol>
					<KeyLines lines={ paper.key } />
				</>
			);
		case 'multi-blank':
			return (
				<>
					{ paper.table && (
						<table className="ohmylms-print-table">
							{ paper.table.columns.length > 0 && (
								<thead>
									<tr>
										{ paper.table.columns.map(
											( column, index ) => (
												<th key={ index }>
													{ column }
												</th>
											)
										) }
									</tr>
								</thead>
							) }
							<tbody>
								{ paper.table.rows.map( ( row, rowIndex ) => (
									<tr key={ rowIndex }>
										{ row.map( ( parts, cellIndex ) => (
											<td key={ cellIndex }>
												{ parts.map( ( part, index ) =>
													part.id ? (
														<Blank
															key={ index }
															label={ part.id }
															unit={
																paper.units[
																	part.id
																]
															}
														/>
													) : (
														part.text
													)
												) }
											</td>
										) ) }
									</tr>
								) ) }
							</tbody>
						</table>
					) }
					<KeyLines lines={ paper.key } />
				</>
			);
		case 'categorize':
			return (
				<>
					<ol className="ohmylms-print-items">
						{ paper.items.map( ( item ) => (
							<li key={ item.letter }>
								<span className="ohmylms-print-letter">
									{ item.letter }
								</span>
								<ItemContent item={ item } />
							</li>
						) ) }
					</ol>
					<table className="ohmylms-print-table is-groups">
						<thead>
							<tr>
								{ paper.groups.map( ( group ) => (
									<th key={ group.id }>{ group.label }</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							<tr>
								{ paper.groups.map( ( group ) => (
									<td key={ group.id } />
								) ) }
							</tr>
						</tbody>
					</table>
					<KeyLines lines={ paper.key } />
				</>
			);
		case 'tiles':
			return (
				<>
					<p className="ohmylms-print-tiles">
						{ paper.tiles.map( ( tile, index ) => (
							<span key={ index }>{ tile }</span>
						) ) }
					</p>
					<div className="ohmylms-print-answer-line">
						<span className="ohmylms-print-line" />
					</div>
					<KeyLines lines={ paper.key } />
				</>
			);
		case 'crossword':
			return (
				<div className="ohmylms-print-puzzle">
					<table
						className="ohmylms-print-grid is-crossword"
						aria-hidden="true"
					>
						<tbody>
							{ Array.from(
								{ length: paper.size },
								( _, row ) => (
									<tr key={ row }>
										{ Array.from(
											{ length: paper.size },
											( __unused, col ) => {
												const cell = `r${ row }c${ col }`;
												return paper.cells.has(
													cell
												) ? (
													<td key={ col }>
														{ paper.numbers[
															cell
														] && (
															<span className="ohmylms-print-cell-number">
																{
																	paper
																		.numbers[
																		cell
																	]
																}
															</span>
														) }
														{
															paper.letters[
																cell
															]
														}
													</td>
												) : (
													<td
														key={ col }
														className="is-empty"
													/>
												);
											}
										) }
									</tr>
								)
							) }
						</tbody>
					</table>
					<div className="ohmylms-print-clues">
						{ [
							[ __( 'Across', 'ohmylms' ), paper.across ],
							[ __( 'Down', 'ohmylms' ), paper.down ],
						]
							.filter( ( [ , clues ] ) => clues.length )
							.map( ( [ heading, clues ] ) => (
								<div key={ heading }>
									<p className="ohmylms-print-clues-heading">
										{ heading }
									</p>
									<ol>
										{ clues.map( ( clue ) => (
											<li key={ clue.number }>
												<strong>{ clue.number }</strong>{ ' ' }
												{ clue.text } ({ clue.length })
											</li>
										) ) }
									</ol>
								</div>
							) ) }
					</div>
				</div>
			);
		case 'wordsearch':
			return (
				<div className="ohmylms-print-puzzle">
					<table
						className="ohmylms-print-grid is-wordsearch"
						aria-hidden="true"
					>
						<tbody>
							{ paper.grid.map( ( row, rowIndex ) => (
								<tr key={ rowIndex }>
									{ row.map( ( letter, col ) => (
										<td
											key={ col }
											className={
												paper.marked.has(
													`r${ rowIndex }c${ col }`
												)
													? 'is-marked'
													: undefined
											}
										>
											{ letter }
										</td>
									) ) }
								</tr>
							) ) }
						</tbody>
					</table>
					<ul className="ohmylms-print-words">
						{ paper.words.map( ( word, index ) => (
							<li key={ index }>{ word }</li>
						) ) }
					</ul>
				</div>
			);
		default:
			return null;
	}
}

/** Layouts rendered by TypedBody. */
export const TYPED_LAYOUTS = [
	'matching',
	'reorder',
	'blanks',
	'dropdown',
	'multi-blank',
	'categorize',
	'tiles',
	'crossword',
	'wordsearch',
	'visual',
];
