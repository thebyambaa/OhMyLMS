import { createElement, useRef } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	hotTextFromAuthoring,
	hotTextNormalize,
	hotTextToAuthoring,
} from './hotTextModel.mjs';

/**
 * Passage-based hot text editor: select words, phrases, sentences or
 * paragraphs in the passage and make them options.
 * @param {Object}                                       props
 * @param {import('./hotTextModel.mjs').HotTextSettings} props.value    Settings.
 * @param {Function}                                     props.onChange Patch callback.
 */
export function HotTextEditor( { value, onChange } ) {
	/** @type {import('react').MutableRefObject<HTMLDivElement|null>} */
	const wrap = useRef( null );
	const settings = hotTextNormalize( value );
	const authoring = hotTextToAuthoring( settings );
	const update = ( /** @type {string} */ text ) =>
		onChange( hotTextFromAuthoring( text, settings ) );
	const textarea = () => wrap.current?.querySelector( 'textarea' );
	const addSelection = () => {
		const area = textarea();
		if ( ! area || area.selectionStart === area.selectionEnd ) {
			return;
		}
		const { selectionStart: start, selectionEnd: end } = area;
		const chosen = authoring.slice( start, end );
		if ( chosen.includes( '[[' ) || chosen.includes( ']]' ) ) {
			return;
		}
		update(
			authoring.slice( 0, start ) +
				'[[' +
				chosen.trim() +
				']]' +
				authoring.slice( end )
		);
	};
	const removeOption = ( /** @type {string} */ id ) => {
		const token = settings.tokens.find( ( t ) => t.id === id );
		const passage = settings.passage.replace(
			'[[' + id + ']]',
			token ? token.text : ''
		);
		onChange( {
			passage,
			tokens: settings.tokens.filter( ( t ) => t.id !== id ),
			correct: settings.correct.filter( ( c ) => c !== id ),
			scoring: settings.scoring,
		} );
	};
	const ordered = [
		...settings.passage.matchAll( /\[\[([A-Za-z0-9_-]+)\]\]/g ),
	]
		.map( ( m ) => settings.tokens.find( ( t ) => t.id === m[ 1 ] ) )
		.filter(
			/**
			 * @param  t
			 * @return {t is import('./hotTextModel.mjs').HotTextToken} Whether the token exists.
			 */ ( t ) => Boolean( t )
		);
	return (
		<div className="oml-hot-text-editor">
			<div ref={ wrap }>
				<TextareaControl
					label={ __( 'Passage', 'ohmylms' ) }
					help={ __(
						'Paste the passage, select a word, phrase, sentence or paragraph and choose "Add as option". Options appear as [[text]]; the rest stays plain reading text. Variables such as {{a}} work in the passage and in options.',
						'ohmylms'
					) }
					rows={ 8 }
					value={ authoring }
					onChange={ update }
				/>
			</div>
			<Button variant="secondary" onClick={ addSelection }>
				{ __( 'Add selection as option', 'ohmylms' ) }
			</Button>
			<ol className="oml-hot-text-options">
				{ ordered.map( ( token, index ) => (
					<li
						key={ token.id }
						className={ 'oml-ht-color-' + ( index % 6 ) }
					>
						<CheckboxControl
							label={ sprintf(
								/* translators: 1: option number, 2: option text. */
								__( 'Correct — option %1$d: %2$s', 'ohmylms' ),
								index + 1,
								token.text
							) }
							checked={ settings.correct.includes( token.id ) }
							onChange={ ( checked ) =>
								onChange( {
									...settings,
									correct: checked
										? [ ...settings.correct, token.id ]
										: settings.correct.filter(
												( id ) => id !== token.id
											),
								} )
							}
						/>
						<Button
							variant="link"
							isDestructive
							onClick={ () => removeOption( token.id ) }
						>
							{ sprintf(
								/* translators: %d: option number. */
								__( 'Remove option %d', 'ohmylms' ),
								index + 1
							) }
						</Button>
					</li>
				) ) }
			</ol>
			{ ordered.length < 2 && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'Add at least 2 options.', 'ohmylms' ) }
				</Notice>
			) }
			<ToggleControl
				label={ __( 'All-or-nothing scoring', 'ohmylms' ) }
				help={
					settings.scoring === 'all'
						? __(
								'Full marks only for exactly the correct options.',
								'ohmylms'
							)
						: __(
								'Partial credit: (correct picks − wrong picks) ÷ correct options, never below 0.',
								'ohmylms'
							)
				}
				checked={ settings.scoring === 'all' }
				onChange={ ( on ) =>
					onChange( { ...settings, scoring: on ? 'all' : 'partial' } )
				}
			/>
		</div>
	);
}
