/** @jsx createElement */
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { buildPuzzle } from '../../../interactivity/puzzles.mjs';
import {
	generatePuzzle,
	publicPuzzle,
	puzzleCells,
	puzzleIssues,
	puzzleLetters,
} from './puzzleModel.mjs';

/**
 * The same controls used by the learner, mounted with an answer-free projection.
 *
 *
 * @param {Object} props          Preview properties.
 *
 * @param {string} props.type     Puzzle type.
 *
 * @param {Object} props.settings Private author settings.
 *
 * @return {Element} Live preview.
 */
export function PuzzlePreview( { type, settings } ) {
	const ref = useRef( null );
	const issues = puzzleIssues( type, settings );
	useEffect( () => {
		if ( issues.length || ! ref.current ) {
			return;
		}
		return buildPuzzle( ref.current, type, publicPuzzle( type, settings ) );
	}, [ type, settings, issues.length ] );
	if ( issues.length ) {
		return (
			<p>
				{ __(
					'Complete the words and generate a layout to preview this puzzle.',
					'ohmylms'
				) }
			</p>
		);
	}
	return <div ref={ ref } />;
}

/**
 * Compact Wordwall-style answer/clue rows inside the existing question block.
 *
 *
 * @param {Object}                  props          Editor properties.
 *
 * @param {string}                  props.type     Puzzle type.
 *
 * @param {Object}                  props.value    Current settings.
 *
 * @param {(patch: Object) => void} props.onChange Persist a settings patch.
 *
 * @return {Element} Puzzle builder.
 */
function PuzzleEditor( { type, value, onChange } ) {
	const [ preview, setPreview ] = useState( false );
	const [ error, setError ] = useState( '' );
	const entries = value.entries || [];
	const issues = puzzleIssues( type, value );
	const nextId = () => {
		let id = 1;
		while ( entries.some( ( entry ) => entry.id === `w${ id }` ) ) {
			id++;
		}
		return `w${ id }`;
	};
	const update = ( index, patch, dirty = true ) =>
		onChange( {
			entries: entries.map( ( entry, i ) =>
				i === index ? { ...entry, ...patch } : entry
			),
			...( dirty ? { puzzle_version: 0 } : {} ),
		} );
	const generate = ( showPreview = true ) => {
		try {
			const patch = generatePuzzle(
				type,
				value,
				( value.layout_seed || 0 ) + 1
			);
			onChange( {
				...patch,
				layout_seed: ( value.layout_seed || 0 ) + 1,
			} );
			setError( '' );
			setPreview( showPreview );
		} catch ( failure ) {
			setError( failure.message );
			setPreview( false );
		}
	};
	const occupied = new Map();
	for ( const entry of entries ) {
		if ( Number.isInteger( entry.row ) && Number.isInteger( entry.col ) ) {
			puzzleCells( entry ).forEach( ( cell, index ) =>
				occupied.set( cell, puzzleLetters( entry.answer )[ index ] )
			);
		}
	}
	return (
		<div className="ohmylms-puzzle-editor">
			<div className="ohmylms-puzzle-toolbar">
				<Button
					variant={ ! preview ? 'primary' : 'secondary' }
					aria-pressed={ ! preview }
					onClick={ () => setPreview( false ) }
				>
					{ __( 'Edit content', 'ohmylms' ) }
				</Button>
				<Button
					variant={ preview ? 'primary' : 'secondary' }
					aria-pressed={ preview }
					onClick={ () =>
						issues.length ? generate() : setPreview( true )
					}
				>
					{ __( 'Preview puzzle', 'ohmylms' ) }
				</Button>
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ preview ? (
				<PuzzlePreview type={ type } settings={ value } />
			) : (
				<>
					{ type === 'wordsearch' && (
						<SelectControl
							label={ __( 'Word list', 'ohmylms' ) }
							value={ value.with_clues ? 'clues' : 'words' }
							options={ [
								{
									value: 'words',
									label: __(
										'Without clues — show the words',
										'ohmylms'
									),
								},
								{
									value: 'clues',
									label: __(
										'With clues — hide the words',
										'ohmylms'
									),
								},
							] }
							onChange={ ( mode ) =>
								onChange( { with_clues: mode === 'clues' } )
							}
						/>
					) }
					{ entries.map( ( entry, index ) => (
						<div key={ entry.id } className="ohmylms-puzzle-entry">
							<TextControl
								label={ sprintf(
									/* translators: %d: item number. */ __(
										'Answer %d',
										'ohmylms'
									),
									index + 1
								) }
								value={ entry.answer }
								onChange={ ( answer ) =>
									update( index, { answer } )
								}
							/>
							{ type === 'crossword' || value.with_clues ? (
								<TextControl
									label={ sprintf(
										/* translators: %d: item number. */ __(
											'Clue %d',
											'ohmylms'
										),
										index + 1
									) }
									value={ entry.clue }
									onChange={ ( clue ) =>
										update( index, { clue }, false )
									}
								/>
							) : (
								<span />
							) }
							<div>
								<Button
									icon="arrow-up-alt2"
									label={ __( 'Move item up', 'ohmylms' ) }
									disabled={ index === 0 }
									onClick={ () => {
										const list = [ ...entries ];
										[ list[ index ], list[ index - 1 ] ] = [
											list[ index - 1 ],
											list[ index ],
										];
										onChange( { entries: list } );
									} }
								/>
								<Button
									icon="trash"
									label={ __( 'Remove item', 'ohmylms' ) }
									disabled={ entries.length === 1 }
									onClick={ () =>
										onChange( {
											entries: entries.filter(
												( item ) => item.id !== entry.id
											),
											puzzle_version: 0,
										} )
									}
								/>
							</div>
						</div>
					) ) }
					<Button
						variant="secondary"
						disabled={ entries.length >= 12 }
						onClick={ () =>
							onChange( {
								entries: [
									...entries,
									{ id: nextId(), answer: '', clue: '' },
								],
								puzzle_version: 0,
							} )
						}
					>
						{ __( 'Add an item', 'ohmylms' ) }
					</Button>
					<p>
						{ __(
							'Use 1–12 words, with letters only. Preview puzzle generates the layout; the published layout stays fixed.',
							'ohmylms'
						) }
					</p>
					{ issues.length > 0 && (
						<p role="status">{ issues[ 0 ] }</p>
					) }
				</>
			) }
			<details>
				<summary>{ __( 'Layout and grading', 'ohmylms' ) }</summary>
				<TextControl
					type="number"
					min={ 4 }
					max={ 12 }
					label={ __( 'Grid size', 'ohmylms' ) }
					value={ value.size }
					onChange={ ( size ) =>
						onChange( { size: Number( size ), puzzle_version: 0 } )
					}
				/>
				<CheckboxControl
					label={ __( 'Award partial credit per word', 'ohmylms' ) }
					checked={ value.partial_credit !== false }
					onChange={ ( partialCredit ) =>
						onChange( { partial_credit: partialCredit } )
					}
				/>
				{ type === 'wordsearch' && (
					<>
						<CheckboxControl
							label={ __( 'Allow diagonal words', 'ohmylms' ) }
							checked={ Boolean( value.allow_diagonal ) }
							onChange={ ( allowDiagonal ) =>
								onChange( {
									allow_diagonal: allowDiagonal,
									puzzle_version: 0,
								} )
							}
						/>
						<CheckboxControl
							label={ __( 'Allow backward words', 'ohmylms' ) }
							checked={ Boolean( value.allow_backward ) }
							onChange={ ( allowBackward ) =>
								onChange( {
									allow_backward: allowBackward,
									puzzle_version: 0,
								} )
							}
						/>
						<TextControl
							label={ __( 'Filler alphabet', 'ohmylms' ) }
							help={ __(
								'For Mongolian, use АБВГДЕЁЖЗИЙКЛМНОӨПРСТУҮФХЦЧШЩЪЫЬЭЮЯ.',
								'ohmylms'
							) }
							value={ value.alphabet }
							onChange={ ( alphabet ) =>
								onChange( { alphabet, puzzle_version: 0 } )
							}
						/>
					</>
				) }
				<Button variant="secondary" onClick={ () => generate( false ) }>
					{ __( 'Generate another layout', 'ohmylms' ) }
				</Button>
				{ type === 'crossword' &&
					entries.map( ( entry, index ) => (
						<div key={ entry.id } className="ohmylms-puzzle-entry">
							<TextControl
								label={ sprintf(
									/* translators: %d: item number. */ __(
										'Item %d row',
										'ohmylms'
									),
									index + 1
								) }
								type="number"
								min={ 1 }
								max={ value.size }
								value={
									Number.isInteger( entry.row )
										? entry.row + 1
										: ''
								}
								onChange={ ( row ) =>
									update(
										index,
										{ row: Number( row ) - 1 },
										false
									)
								}
							/>
							<TextControl
								label={ sprintf(
									/* translators: %d: item number. */ __(
										'Item %d column',
										'ohmylms'
									),
									index + 1
								) }
								type="number"
								min={ 1 }
								max={ value.size }
								value={
									Number.isInteger( entry.col )
										? entry.col + 1
										: ''
								}
								onChange={ ( col ) =>
									update(
										index,
										{ col: Number( col ) - 1 },
										false
									)
								}
							/>
							<SelectControl
								label={ __( 'Direction', 'ohmylms' ) }
								value={ entry.direction || 'across' }
								options={ [
									{
										value: 'across',
										label: __( 'Across', 'ohmylms' ),
									},
									{
										value: 'down',
										label: __( 'Down', 'ohmylms' ),
									},
								] }
								onChange={ ( direction ) =>
									update( index, { direction }, false )
								}
							/>
						</div>
					) ) }
				{ ! issues.length && (
					<>
						<p>
							{ __( 'Answer layout — teachers only', 'ohmylms' ) }
						</p>
						<div className="ohmylms-puzzle-scroll">
							<div
								className="ohmylms-puzzle-grid"
								style={ { '--puzzle-size': value.size } }
							>
								{ Array.from(
									{ length: value.size * value.size },
									( _, i ) => {
										const row = Math.floor(
												i / value.size
											),
											col = i % value.size;
										return (
											<div
												className="ohmylms-puzzle-cell"
												key={ i }
											>
												{ type === 'wordsearch'
													? value.grid[ row ][ col ]
													: occupied.get(
															`r${ row }c${ col }`
														) || '' }
											</div>
										);
									}
								) }
							</div>
						</div>
					</>
				) }
			</details>
		</div>
	);
}

/**
 * @param {Object} props Shared answer settings.
 * @return {Element} Crossword builder.
 */
export function CrosswordEditor( props ) {
	return <PuzzleEditor { ...props } type="crossword" />;
}
/**
 * @param {Object} props Shared answer settings.
 * @return {Element} Wordsearch builder.
 */
export function WordsearchEditor( props ) {
	return <PuzzleEditor { ...props } type="wordsearch" />;
}
