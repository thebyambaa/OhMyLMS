/** @jsx createElement */
import { createElement, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { parseInlineBlankPrompt, placeBlankToken } from './inlineBlanks.mjs';

export function InlineBlankPreview( { text } ) {
	const { parts, answers } = parseInlineBlankPrompt( text );
	const [ assigned, setAssigned ] = useState( () =>
		answers.map( () => null )
	);
	const [ selected, setSelected ] = useState( null );
	const place = ( token, index ) => {
		if ( ! answers.some( ( answer ) => answer.id === token ) ) {
			return;
		}
		setAssigned( ( old ) => placeBlankToken( old, token, index ) );
		setSelected( null );
	};
	return (
		<div className="ohmylms-inline-blank-preview">
			<p className="ohmylms-inline-blank-stem">
				{ parts.map( ( part, i ) =>
					part.blank ? (
						<button
							key={ i }
							type="button"
							className="ohmylms-blank-drop"
							aria-label={ sprintf(
								/* translators: %d: blank position. */
								__( 'Blank %d', 'ohmylms' ),
								part.index + 1
							) }
							onDragOver={ ( event ) => event.preventDefault() }
							onDrop={ ( event ) => {
								event.preventDefault();
								place(
									event.dataTransfer.getData( 'text/plain' ),
									part.index
								);
							} }
							onClick={ () =>
								selected !== null
									? place( selected, part.index )
									: setAssigned( ( old ) =>
											old.map( ( token, index ) =>
												index === part.index
													? null
													: token
											)
										)
							}
						>
							{ answers.find(
								( answer ) =>
									answer.id === assigned[ part.index ]
							)?.text || '________' }
						</button>
					) : (
						<span key={ i }>{ part.text }</span>
					)
				) }
			</p>
			<p className="ohmylms-blank-help">
				{ __(
					'Drag an answer into a blank, or select an answer and then a blank.',
					'ohmylms'
				) }
			</p>
			<div
				className="ohmylms-blank-answer-bank"
				aria-label={ __( 'Answer bank', 'ohmylms' ) }
			>
				{ [ ...answers ].reverse().map( ( answer ) => (
					<Button
						key={ answer.id }
						className={
							assigned.includes( answer.id )
								? 'ohmylms-blank-token is-placed'
								: 'ohmylms-blank-token'
						}
						disabled={ assigned.includes( answer.id ) }
						aria-hidden={ assigned.includes( answer.id ) }
						variant={
							selected === answer.id ? 'primary' : 'secondary'
						}
						aria-pressed={ selected === answer.id }
						draggable
						onDragStart={ ( event ) =>
							event.dataTransfer.setData(
								'text/plain',
								answer.id
							)
						}
						onClick={ () => setSelected( answer.id ) }
						onDoubleClick={ () =>
							place( answer.id, assigned.indexOf( null ) )
						}
						onKeyDown={ ( event ) => {
							if ( event.key === 'Enter' ) {
								event.preventDefault();
								place( answer.id, assigned.indexOf( null ) );
							}
						} }
					>
						{ answer.text }
					</Button>
				) ) }
			</div>
		</div>
	);
}
