import { MatchTableGridEditor } from './MatchTableGridEditor';
import { RichContentControl } from '../math/RichContentControl';
import { createElement, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	FormTokenField,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { StructuredEditor } from './MathEditors';
import { questionTypePatch } from './questionBlocks.mjs';
import { QuestionMediaUpload } from './QuestionMediaUpload';
import { DiagramPopup } from './DiagramPopup';
import { CHOICE_GAME_TYPES, videoSource } from './extendedModel.mjs';
/**
 * Explain what a pasted video link will do.
 * @param link The saved video link.
 */
function videoHelp( link ) {
	const { provider } = videoSource( link );
	switch ( provider ) {
		case 'youtube':
			return __(
				'YouTube video. Checkpoints pause the video at their time.',
				'ohmylms'
			);
		case 'drive':
			return __(
				'Google Drive video. Share the file so anyone with the link can view it. Drive cannot report the playback time, so learners answer each checkpoint while they watch.',
				'ohmylms'
			);
		case 'onedrive':
			return __(
				'OneDrive video. Learners answer each checkpoint while they watch, because OneDrive cannot report the playback time.',
				'ohmylms'
			);
		case 'onedrive-short':
			return __(
				'This short OneDrive link cannot be embedded. In OneDrive choose Share, then Embed, and paste the embed code or its address here.',
				'ohmylms'
			);
		case 'invalid':
			return link
				? __(
						'This link is not recognised. Use a video file, YouTube, Google Drive or OneDrive link.',
						'ohmylms'
					)
				: __(
						'Paste a YouTube, Google Drive or OneDrive link, or choose an uploaded video.',
						'ohmylms'
					);
		default:
			return __(
				'Video file. Checkpoints pause the video at their time.',
				'ohmylms'
			);
	}
}
/**
 * Authoring controls for additional response formats.
 * @param root0
 * @param root0.type
 * @param root0.value
 * @param root0.onChange
 * @param root0.mainStatement
 */
export function ExtendedEditor( {
	type,
	value,
	onChange,
	mainStatement = false,
} ) {
	const [ selected, setSelected ] = useState( 0 );
	const [ popup, setPopup ] = useState( false );
	const set = ( patch ) => onChange( patch );
	const rows = ( key ) => value[ key ] || [];
	const update = ( key, index, patch ) =>
		set( {
			[ key ]: rows( key ).map( ( row, i ) =>
				i === index ? { ...row, ...patch } : row
			),
		} );
	const diagramKey = type === 'labeling' ? 'targets' : 'zones';
	const place = ( event, index = selected ) => {
		const bounds = event.currentTarget
			.closest( '.ohmylms-author-diagram' )
			.getBoundingClientRect();
		const percentage = ( position, start, size ) =>
			Math.round(
				Math.max(
					0,
					Math.min( 100, ( ( position - start ) / size ) * 100 )
				)
			);
		update( diagramKey, index, {
			x:
				event.detail === 0 && event.type === 'click'
					? 50
					: percentage( event.clientX, bounds.left, bounds.width ),
			y:
				event.detail === 0 && event.type === 'click'
					? 50
					: percentage( event.clientY, bounds.top, bounds.height ),
		} );
	};
	const number = ( key, label, min = 0 ) =>
		createElement( TextControl, {
			label,
			type: 'number',
			min,
			step: 'any',
			value: value[ key ] ?? '',
			onChange: ( next ) => set( { [ key ]: Number( next ) } ),
		} );
	if ( type === 'passage' ) {
		return (
			<div className="ohmylms-interactive-editor">
				{ mainStatement ? (
					<p>
						{ __(
							'Write the reading passage in the Question field above, then add its questions below.',
							'ohmylms'
						) }
					</p>
				) : (
					<RichContentControl
						compact
						html={ false }
						label={ __( 'Reading passage', 'ohmylms' ) }
						rows={ 6 }
						value={ value.passage || '' }
						onChange={ ( passage ) => set( { passage } ) }
					/>
				) }
				<StructuredEditor
					value={ value }
					onChange={ ( patch ) =>
						set( {
							...patch,
							score: {
								enabled: true,
								value: (
									patch.parts ||
									value.parts ||
									[]
								).reduce(
									( sum, part ) =>
										sum + Number( part.marks || 0 ),
									0
								),
							},
						} )
					}
				/>
			</div>
		);
	}
	if (
		[
			'draw',
			'audio-response',
			'video-response',
			'word-cloud',
			'discussion-board',
			'slide',
		].includes( type )
	) {
		return (
			<div className="ohmylms-interactive-editor">
				<p>
					{
						{
							slide: __(
								'Learners read the question text and continue. A slide has no answer and no marks.',
								'ohmylms'
							),
							draw: __(
								'Learners draw on a canvas. A teacher reviews the drawing.',
								'ohmylms'
							),
							'audio-response': __(
								'Learners record or upload audio, or provide an audio link. A teacher marks it.',
								'ohmylms'
							),
							'video-response': __(
								'Learners record or upload video, or provide a video link. A teacher marks it.',
								'ohmylms'
							),
							'word-cloud': __(
								'Learners submit words. Responses are not scored.',
								'ohmylms'
							),
							'discussion-board': __(
								'Learners write a response for teacher review.',
								'ohmylms'
							),
						}[ type ]
					}
				</p>
				{ [ 'audio-response', 'video-response' ].includes( type ) &&
					number(
						'max_seconds',
						__( 'Maximum recording seconds', 'ohmylms' ),
						1
					) }
				{ type === 'video-response' &&
					[
						[ 'camera', __( 'Allow camera recording', 'ohmylms' ) ],
						[ 'screen', __( 'Allow screen recording', 'ohmylms' ) ],
					].map( ( [ source, label ] ) => {
						const sources = value.sources || [ 'camera', 'screen' ];
						return (
							<CheckboxControl
								key={ source }
								label={ label }
								checked={ sources.includes( source ) }
								// At least one way to record must stay on.
								disabled={
									sources.includes( source ) &&
									sources.length === 1
								}
								onChange={ ( checked ) =>
									set( {
										sources: checked
											? [ ...sources, source ]
											: sources.filter(
													( item ) => item !== source
												),
									} )
								}
							/>
						);
					} ) }
				{ [ 'audio-response', 'video-response' ].includes( type ) && (
					<div className="ohmylms-response-parts">
						<p className="description">
							{ __(
								'Need an image or other questions with this recording? Convert it to a multi-part question. The recording becomes the first part and keeps its settings.',
								'ohmylms'
							) }
						</p>
						<Button
							variant="secondary"
							onClick={ () => {
								const child = questionTypePatch( {}, type );
								set( {
									type: 'structured',
									max_seconds: undefined,
									sources: undefined,
									parts: [
										{
											id: 'p1',
											label: '(a)',
											kind: 'question',
											marks: 1,
											prompt: '',
											question: {
												...child,
												settings: {
													...child.settings,
													max_seconds:
														value.max_seconds ?? 60,
													...( type ===
													'video-response'
														? {
																sources:
																	value.sources || [
																		'camera',
																		'screen',
																	],
															}
														: {} ),
												},
											},
										},
									],
								} );
							} }
						>
							{ __( 'Add other questions or images', 'ohmylms' ) }
						</Button>
					</div>
				) }
			</div>
		);
	}
	return (
		<div className="ohmylms-interactive-editor">
			{ [ 'labeling', 'hotspot' ].includes( type ) && (
				<>
					<QuestionMediaUpload
						allowedTypes={ [ 'image' ] }
						onSelect={ ( image ) =>
							set( { image_url: image.url } )
						}
						render={ ( { open } ) => (
							<Button variant="secondary" onClick={ open }>
								{ __( 'Choose diagram image', 'ohmylms' ) }
							</Button>
						) }
					/>
					<TextControl
						label={ __( 'Diagram image URL', 'ohmylms' ) }
						value={ value.image_url || '' }
						onChange={ ( imageUrl ) =>
							set( { image_url: imageUrl } )
						}
					/>
					{ value.image_url && (
						<>
							<p>
								{ __(
									'Select a marker, then click the image or drag its marker to position it. You can also enter coordinates below.',
									'ohmylms'
								) }
							</p>
							<Button
								variant="primary"
								onClick={ () => setPopup( true ) }
							>
								{ type === 'hotspot'
									? __(
											'Place hotspots full screen',
											'ohmylms'
										)
									: __(
											'Place labels full screen',
											'ohmylms'
										) }
							</Button>
							{ popup && (
								<DiagramPopup
									type={ type }
									imageUrl={ value.image_url }
									rows={ rows( diagramKey ) }
									selected={ selected }
									onSelect={ setSelected }
									onChange={ ( next ) =>
										set( { [ diagramKey ]: next } )
									}
									onClose={ () => setPopup( false ) }
								/>
							) }
							<SelectControl
								label={ __( 'Marker to move', 'ohmylms' ) }
								value={ selected }
								options={ rows( diagramKey ).map(
									( row, index ) => ( {
										value: index,
										label:
											row.answer || String( index + 1 ),
									} )
								) }
								onChange={ ( next ) =>
									setSelected( Number( next ) )
								}
							/>
							{ /* Inline styles: the player stylesheet is not loaded in the editor, so a large image would overflow the question. */ }
							<div
								className="ohmylms-author-diagram"
								style={ {
									position: 'relative',
									width: 'fit-content',
									maxWidth: '100%',
								} }
							>
								<button
									type="button"
									className="ohmylms-diagram-place"
									onClick={ place }
									aria-label={ __(
										'Place selected marker on diagram',
										'ohmylms'
									) }
									style={ {
										display: 'block',
										maxWidth: '100%',
										padding: 0,
										border: 0,
										background: 'none',
										cursor: 'crosshair',
									} }
								>
									<img
										src={ value.image_url }
										alt={ __( 'Diagram', 'ohmylms' ) }
										style={ {
											display: 'block',
											maxWidth: '100%',
											height: 'auto',
										} }
									/>
								</button>
								{ rows( diagramKey ).map( ( row, index ) => (
									<button
										type="button"
										key={ row.id || index }
										className="ohmylms-author-marker"
										draggable
										onDragStart={ () =>
											setSelected( index )
										}
										onDragEnd={ ( event ) =>
											place( event, index )
										}
										onClick={ () => setSelected( index ) }
										aria-pressed={ selected === index }
										aria-label={ `${ __( 'Move marker', 'ohmylms' ) } ${ index + 1 }` }
										style={ {
											position: 'absolute',
											left: `${ row.x }%`,
											top: `${ row.y }%`,
											width: 32,
											height: 32,
											padding: 0,
											border: '2px solid #fff',
											borderRadius: '50%',
											color: '#fff',
											background: '#432474',
											transform: 'translate(-50%, -50%)',
											cursor: 'move',
											outline:
												selected === index
													? '3px solid #dba617'
													: 'none',
										} }
									>
										{ index + 1 }
									</button>
								) ) }
							</div>
						</>
					) }
				</>
			) }
			{ type === 'graphing' && (
				<>
					<SelectControl
						label={ __( 'Graph answer', 'ohmylms' ) }
						value={ value.mode || 'points' }
						options={ [
							{
								value: 'points',
								label: __( 'Plot points', 'ohmylms' ),
							},
							{
								value: 'line',
								label: __( 'Draw a straight line', 'ohmylms' ),
							},
						] }
						onChange={ ( mode ) =>
							set( {
								mode,
								points:
									mode === 'line'
										? [
												{ x: 0, y: 1 },
												{ x: 1, y: 2 },
											]
										: [ { x: 2, y: 3 } ],
							} )
						}
					/>
					{ number( 'min', __( 'Axis minimum', 'ohmylms' ), -100 ) }
					{ number( 'max', __( 'Axis maximum', 'ohmylms' ), -99 ) }
					{ number(
						'tolerance',
						__( 'Coordinate tolerance', 'ohmylms' )
					) }
					{ rows( 'points' ).map( ( point, index ) => (
						<fieldset
							key={ index }
							className="ohmylms-interactive-row"
						>
							<legend>
								{ __( 'Expected point', 'ohmylms' ) }{ ' ' }
								{ index + 1 }
							</legend>
							{ [ 'x', 'y' ].map( ( axis ) => (
								<TextControl
									key={ axis }
									label={ axis.toUpperCase() }
									type="number"
									step="any"
									value={ point[ axis ] }
									onChange={ ( next ) =>
										update( 'points', index, {
											[ axis ]: Number( next ),
										} )
									}
								/>
							) ) }
							{ value.mode !== 'line' && (
								<Button
									onClick={ () =>
										set( {
											points: rows( 'points' ).filter(
												( _, i ) => i !== index
											),
										} )
									}
								>
									{ __( 'Remove point', 'ohmylms' ) }
								</Button>
							) }
						</fieldset>
					) ) }
					{ value.mode !== 'line' && (
						<Button
							onClick={ () =>
								set( {
									points: [
										...rows( 'points' ),
										{ x: 0, y: 0 },
									],
								} )
							}
						>
							{ __( 'Add point', 'ohmylms' ) }
						</Button>
					) }
				</>
			) }
			{ type === 'hot-text' && (
				<>
					<RichContentControl
						compact
						html={ false }
						label={ __(
							'Selectable words (one per line)',
							'ohmylms'
						) }
						value={ rows( 'tokens' )
							.map( ( token ) => token.text )
							.join( '\n' ) }
						onChange={ ( text ) =>
							set( {
								tokens: text
									.split( '\n' )
									.map( ( word, index ) => ( {
										id: 't' + ( index + 1 ),
										text: word,
									} ) ),
								correct: [],
							} )
						}
					/>
					{ rows( 'tokens' ).map( ( token ) => (
						<CheckboxControl
							key={ token.id }
							label={ token.text }
							checked={ ( value.correct || [] ).includes(
								token.id
							) }
							onChange={ ( checked ) =>
								set( {
									correct: checked
										? [
												...( value.correct || [] ),
												token.id,
											]
										: ( value.correct || [] ).filter(
												( id ) => id !== token.id
											),
								} )
							}
						/>
					) ) }
				</>
			) }
			{ type === 'match-table-grid' && (
				<MatchTableGridEditor value={ value } onChange={ onChange } />
			) }
			{ type === 'labeling' && (
				<>
					<p>
						{ __(
							'Learners drag labels onto the numbered pins, or use tap and keyboard controls. Move the pins on the diagram and enter the correct label for each pin below.',
							'ohmylms'
						) }
					</p>
					<FormTokenField
						label={ __( 'Distractor labels', 'ohmylms' ) }
						value={ value.distractors || [] }
						onChange={ ( distractors ) => set( { distractors } ) }
						__experimentalShowHowTo={ true }
					/>
					{ rows( 'targets' ).map( ( target, index ) => (
						<fieldset
							key={ target.id }
							className="ohmylms-interactive-row"
						>
							<legend>
								{ __( 'Label', 'ohmylms' ) } { index + 1 }
							</legend>
							{ [ 'x', 'y' ].map( ( axis ) => (
								<TextControl
									key={ axis }
									label={ axis.toUpperCase() + ' (%)' }
									type="number"
									min={ 0 }
									max={ 100 }
									value={ target[ axis ] }
									onChange={ ( next ) =>
										update( 'targets', index, {
											[ axis ]: Number( next ),
										} )
									}
								/>
							) ) }
							<TextControl
								label={ __( 'Correct label', 'ohmylms' ) }
								value={ target.answer }
								onChange={ ( answer ) =>
									update( 'targets', index, { answer } )
								}
							/>
							<Button
								onClick={ () =>
									set( {
										targets: rows( 'targets' ).filter(
											( _, i ) => i !== index
										),
									} )
								}
							>
								{ __( 'Remove label', 'ohmylms' ) }
							</Button>
						</fieldset>
					) ) }
					<Button
						onClick={ () =>
							set( {
								targets: [
									...rows( 'targets' ),
									{
										id: 'l' + Date.now(),
										x: 50,
										y: 50,
										answer: '',
									},
								],
							} )
						}
					>
						{ __( 'Add label', 'ohmylms' ) }
					</Button>
				</>
			) }
			{ type === 'hotspot' && (
				<>
					{ rows( 'zones' ).map( ( zone, index ) => (
						<fieldset
							key={ index }
							className="ohmylms-interactive-row"
						>
							<legend>
								{ __( 'Correct target region', 'ohmylms' ) }{ ' ' }
								{ index + 1 }
							</legend>
							{ [ 'x', 'y', 'radius' ].map( ( key ) => (
								<TextControl
									key={ key }
									label={
										key === 'radius'
											? __( 'Radius (%)', 'ohmylms' )
											: key.toUpperCase() + ' (%)'
									}
									type="number"
									min={ 0 }
									max={ 100 }
									value={ zone[ key ] }
									onChange={ ( next ) =>
										update( 'zones', index, {
											[ key ]: Number( next ),
										} )
									}
								/>
							) ) }
							<Button
								onClick={ () =>
									set( {
										zones: rows( 'zones' ).filter(
											( _, i ) => i !== index
										),
									} )
								}
							>
								{ __( 'Remove region', 'ohmylms' ) }
							</Button>
						</fieldset>
					) ) }
					<Button
						onClick={ () =>
							set( {
								zones: [
									...rows( 'zones' ),
									{ x: 50, y: 50, radius: 10 },
								],
							} )
						}
					>
						{ __( 'Add correct region', 'ohmylms' ) }
					</Button>
				</>
			) }
			{ [
				'open-box',
				'speaking-cards',
				'flip-tiles',
				'flashcards',
			].includes( type ) && (
				<>
					<p className="ohmylms-editor-field-label">
						{ __(
							'Cards (one per line: front | back)',
							'ohmylms'
						) }
					</p>
					<RichContentControl
						compact
						html={ false }
						label={ __( 'Cards', 'ohmylms' ) }
						help={ __(
							'Write the front, then | and the back. The back is optional for Open the box and Speaking cards. Learners are not graded.',
							'ohmylms'
						) }
						value={ rows( 'cards' )
							.map( ( card ) =>
								card.back
									? card.text + ' | ' + card.back
									: card.text
							)
							.join( '\n' ) }
						onChange={ ( text ) =>
							set( {
								cards: text
									.split( '\n' )
									.map( ( line, index ) => {
										const [ front, ...rest ] =
											line.split( '|' );
										const back = rest.join( '|' ).trim();
										return {
											id: 'c' + ( index + 1 ),
											text: front.trim(),
											...( back ? { back } : {} ),
										};
									} ),
							} )
						}
					/>
				</>
			) }
			{ type === 'spin-wheel' && (
				<>
					<p className="ohmylms-editor-field-label">
						{ __( 'Cards on the wheel (one per line)', 'ohmylms' ) }
					</p>
					<RichContentControl
						compact
						html={ false }
						label={ __( 'Wheel cards (one per line)', 'ohmylms' ) }
						help={ __(
							'Each spin draws one card without repeating until the deck is used. Learners are not graded.',
							'ohmylms'
						) }
						value={ rows( 'cards' )
							.map( ( card ) => card.text )
							.join( '\n' ) }
						onChange={ ( text ) =>
							set( {
								cards: text
									.split( '\n' )
									.map( ( line, index ) => ( {
										id: 'c' + ( index + 1 ),
										text: line,
									} ) ),
							} )
						}
					/>
				</>
			) }
			{ CHOICE_GAME_TYPES.includes( type ) && (
				<>
					<p className="ohmylms-editor-field-label">
						{ __(
							'Answer choices (one per line, 2–6)',
							'ohmylms'
						) }
					</p>
					<RichContentControl
						compact
						html={ false }
						label={ __(
							'Answer choices (one per line, 2–6)',
							'ohmylms'
						) }
						value={ rows( 'choices' )
							.map( ( choice ) => choice.text )
							.join( '\n' ) }
						onChange={ ( text ) =>
							set( {
								choices: text
									.split( '\n' )
									.map( ( line, index ) => ( {
										id: 'c' + ( index + 1 ),
										text: line,
									} ) ),
							} )
						}
					/>
					<SelectControl
						label={ __( 'Correct answer', 'ohmylms' ) }
						value={ value.correct || '' }
						options={ rows( 'choices' ).map( ( choice ) => ( {
							value: choice.id,
							label: choice.text,
						} ) ) }
						onChange={ ( correct ) => set( { correct } ) }
					/>
					{ number(
						'seconds',
						__( 'Seconds to answer (5–120)', 'ohmylms' ),
						5
					) }
				</>
			) }
			{ type === 'hangman' && (
				<>
					<p className="ohmylms-editor-field-label">
						{ __(
							'Word or phrase to guess (extra lines are accepted spellings)',
							'ohmylms'
						) }
					</p>
					<RichContentControl
						compact
						html={ false }
						label={ __(
							'Word or phrase to guess (first line is shown as blanks; extra lines are accepted spellings)',
							'ohmylms'
						) }
						help={ __(
							'Put the clue in the question. Learners choose letters; too many wrong letters fails the question.',
							'ohmylms'
						) }
						value={ ( value.accepted || [] ).join( '\n' ) }
						onChange={ ( text ) =>
							set( {
								accepted: text
									.split( '\n' )
									.filter( ( line ) => line.trim() ),
							} )
						}
					/>
					{ number(
						'max_misses',
						__( 'Wrong letters allowed (1–10)', 'ohmylms' ),
						1
					) }
				</>
			) }
			{ type === 'poll' && (
				<RichContentControl
					compact
					html={ false }
					label={ __( 'Poll choices (one per line)', 'ohmylms' ) }
					value={ rows( 'choices' )
						.map( ( choice ) => choice.text )
						.join( '\n' ) }
					onChange={ ( text ) =>
						set( {
							choices: text
								.split( '\n' )
								.map( ( word, index ) => ( {
									id: 'c' + ( index + 1 ),
									text: word,
								} ) ),
						} )
					}
				/>
			) }
			{ type === 'interactive-video' && (
				<>
					<QuestionMediaUpload
						allowedTypes={ [ 'video' ] }
						onSelect={ ( video ) =>
							set( { video_url: video.url } )
						}
						render={ ( { open } ) => (
							<Button variant="secondary" onClick={ open }>
								{ __( 'Choose video', 'ohmylms' ) }
							</Button>
						) }
					/>
					<TextControl
						label={ __( 'Video link', 'ohmylms' ) }
						help={ videoHelp( value.video_url ) }
						value={ value.video_url || '' }
						// Pasted embed code is reduced to its address.
						onChange={ ( videoUrl ) =>
							set( {
								video_url: /<iframe/i.test( videoUrl )
									? videoSource( videoUrl ).embed || videoUrl
									: videoUrl,
							} )
						}
					/>
					{ rows( 'checkpoints' ).map( ( checkpoint, index ) => (
						<fieldset
							key={ checkpoint.id }
							className="ohmylms-interactive-row"
						>
							<legend>
								{ __( 'Video checkpoint', 'ohmylms' ) }{ ' ' }
								{ index + 1 }
							</legend>
							<TextControl
								label={ __( 'Time (seconds)', 'ohmylms' ) }
								type="number"
								min={ 0 }
								value={ checkpoint.at }
								onChange={ ( next ) =>
									update( 'checkpoints', index, {
										at: Number( next ),
									} )
								}
							/>
							<RichContentControl
								compact
								html={ false }
								label={ __( 'Checkpoint question', 'ohmylms' ) }
								value={ checkpoint.prompt }
								onChange={ ( prompt ) =>
									update( 'checkpoints', index, { prompt } )
								}
							/>
							<TextControl
								label={ __( 'Correct answer', 'ohmylms' ) }
								value={ checkpoint.answer }
								onChange={ ( answer ) =>
									update( 'checkpoints', index, { answer } )
								}
							/>
							<Button
								onClick={ () =>
									set( {
										checkpoints: rows(
											'checkpoints'
										).filter( ( _, i ) => i !== index ),
									} )
								}
							>
								{ __( 'Remove checkpoint', 'ohmylms' ) }
							</Button>
						</fieldset>
					) ) }
					<Button
						onClick={ () =>
							set( {
								checkpoints: [
									...rows( 'checkpoints' ),
									{
										id: 'v' + Date.now(),
										at: 0,
										prompt: '',
										answer: '',
									},
								],
							} )
						}
					>
						{ __( 'Add checkpoint', 'ohmylms' ) }
					</Button>
				</>
			) }
		</div>
	);
}
