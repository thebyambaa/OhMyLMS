import { createElement, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const fields = () => [
	[ 'stage', __( 'Question stage', 'ohmylms' ) ],
	[ 'prompt', __( 'Question panel', 'ohmylms' ) ],
	[ 'canvas', __( 'Editor background', 'ohmylms' ) ],
	[ 'toolbar', __( 'Toolbar background', 'ohmylms' ) ],
	[ 'toolbar_text', __( 'Toolbar text', 'ohmylms' ) ],
	[ 'accent', __( 'Buttons and progress', 'ohmylms' ) ],
	[ 'text', __( 'Question and answer text', 'ohmylms' ) ],
	[ 'answer_1', __( 'Answer card 1', 'ohmylms' ) ],
	[ 'answer_2', __( 'Answer card 2', 'ohmylms' ) ],
	[ 'answer_3', __( 'Answer card 3', 'ohmylms' ) ],
	[ 'answer_4', __( 'Answer card 4', 'ohmylms' ) ],
	[ 'correct', __( 'Correct answer control', 'ohmylms' ) ],
	[ 'player_stage', __( 'Player stage', 'ohmylms' ) ],
	[ 'player_prompt', __( 'Player question panel', 'ohmylms' ) ],
	[ 'player_toolbar', __( 'Player toolbar', 'ohmylms' ) ],
	[ 'player_toolbar_text', __( 'Player toolbar text', 'ohmylms' ) ],
	[ 'player_answer_1', __( 'Player answer card 1', 'ohmylms' ) ],
	[ 'player_answer_2', __( 'Player answer card 2', 'ohmylms' ) ],
	[ 'player_answer_3', __( 'Player answer card 3', 'ohmylms' ) ],
	[ 'player_answer_4', __( 'Player answer card 4', 'ohmylms' ) ],
];

/**
 * Shared design settings with a local preview, saved through the Design API.
 * @param store Existing settings store.
 */
export function createQuizDesignSettings( store ) {
	return function QuizDesignSettings() {
		const [ previewPlayer, setPreviewPlayer ] = useState( false );
		const [ saving, setSaving ] = useState( false );
		const [ status, setStatus ] = useState( '' );
		const settings = useSelect(
			( select ) => select( store ).getDesignSettings() || {},
			[]
		);
		const { updateDesignSettings } = useDispatch( store );
		const defaults = window.ohmylmsDesign?.defaults || {};
		const key = ( name ) => `ohmylms_quiz_${ name }_color`;
		const value = ( name ) =>
			settings[ key( name ) ]?.value || defaults[ key( name ) ];
		const safe = ( name ) =>
			/^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/i.test( value( name ) || '' )
				? value( name )
				: defaults[ key( name ) ];
		const set = ( name, color ) =>
			updateDesignSettings( { [ key( name ) ]: { value: color } } );
		const previewColor = ( name ) =>
			safe(
				previewPlayer &&
					! [ 'text', 'accent', 'correct' ].includes( name )
					? `player_${ name }`
					: name
			);
		const valid = fields().every( ( [ name ] ) =>
			/^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/i.test( value( name ) || '' )
		);
		const save = async () => {
			setSaving( true );
			setStatus( '' );
			try {
				await apiFetch( {
					path: '/ohmylms/v1/settings/design',
					method: 'POST',
					data: Object.fromEntries(
						fields().map( ( [ name ] ) => [
							key( name ),
							value( name ),
						] )
					),
				} );
				setStatus( __( 'Colors saved.', 'ohmylms' ) );
			} catch {
				setStatus(
					__(
						'Colors could not be saved. Please try again.',
						'ohmylms'
					)
				);
			} finally {
				setSaving( false );
			}
		};
		return createElement(
			'section',
			{ className: 'ohmylms-quiz-design-settings' },
			createElement(
				'h2',
				null,
				__( 'Quiz & Question Editor', 'ohmylms' )
			),
			createElement(
				'p',
				null,
				__(
					'Customize the question editor and Classic player palettes separately. Paper and Focus keep their own surfaces and use your accent color.',
					'ohmylms'
				)
			),
			createElement(
				'div',
				{ className: 'ohmylms-quiz-design-preview-switch' },
				createElement(
					Button,
					{
						variant: previewPlayer ? 'secondary' : 'primary',
						onClick: () => setPreviewPlayer( false ),
						'aria-pressed': ! previewPlayer,
					},
					__( 'Question editor', 'ohmylms' )
				),
				createElement(
					Button,
					{
						variant: previewPlayer ? 'primary' : 'secondary',
						onClick: () => setPreviewPlayer( true ),
						'aria-pressed': previewPlayer,
					},
					__( 'Quiz player', 'ohmylms' )
				)
			),
			createElement(
				'div',
				{
					className: 'ohmylms-quiz-design-preview',
					style: {
						background: previewColor( 'stage' ),
						color: safe( 'text' ),
					},
				},
				createElement(
					'div',
					{
						className: 'ohmylms-quiz-design-preview-toolbar',
						style: {
							background: previewColor( 'toolbar' ),
							color: previewColor( 'toolbar_text' ),
						},
					},
					__( 'Question preview', 'ohmylms' ),
					createElement(
						'span',
						{
							style: {
								background: safe( 'accent' ),
								color: safe( 'text' ),
							},
						},
						__( 'Next', 'ohmylms' )
					)
				),
				createElement(
					'p',
					{ style: { background: previewColor( 'prompt' ) } },
					__( 'What is 2 + 2?', 'ohmylms' )
				),
				createElement(
					'div',
					{ className: 'ohmylms-quiz-design-preview-answers' },
					[ '4', '3', '5', '6' ].map( ( answer, index ) =>
						createElement(
							'span',
							{
								key: answer,
								style: {
									background: previewColor(
										`answer_${ index + 1 }`
									),
								},
							},
							answer,
							index === 0 &&
								createElement(
									'b',
									{
										style: {
											background: safe( 'correct' ),
										},
										'aria-label': __(
											'Correct answer',
											'ohmylms'
										),
									},
									'✓'
								)
						)
					)
				)
			),
			createElement(
				'div',
				{ className: 'ohmylms-quiz-design-colors' },
				fields().map( ( [ name, label ] ) =>
					createElement(
						'div',
						{ key: name },
						createElement( 'input', {
							type: 'color',
							value: safe( name ),
							'aria-label': label,
							onChange: ( event ) =>
								set( name, event.target.value ),
						} ),
						createElement( TextControl, {
							label,
							value: value( name ) || '',
							onChange: ( color ) => set( name, color ),
						} ),
						createElement(
							Button,
							{
								variant: 'tertiary',
								onClick: () =>
									set( name, defaults[ key( name ) ] ),
							},
							__( 'Reset', 'ohmylms' )
						)
					)
				)
			),
			createElement(
				Button,
				{
					variant: 'secondary',
					onClick: () =>
						updateDesignSettings(
							Object.fromEntries(
								fields().map( ( [ name ] ) => [
									key( name ),
									{ value: defaults[ key( name ) ] },
								] )
							)
						),
				},
				__( 'Restore Wayground colors', 'ohmylms' )
			),
			createElement(
				'div',
				{ className: 'ohmylms-quiz-design-save' },
				createElement(
					Button,
					{
						variant: 'primary',
						onClick: save,
						disabled: saving || ! valid,
						isBusy: saving,
					},
					__( 'Save quiz design', 'ohmylms' )
				),
				createElement( 'span', { role: 'status' }, status ),
				! valid &&
					createElement(
						'p',
						{ role: 'alert' },
						__(
							'Enter a valid hex color in each field.',
							'ohmylms'
						)
					)
			)
		);
	};
}
