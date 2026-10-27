import { createElement, useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	CheckboxControl,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { listSkills } from '../question-bank/api.mjs';
import apiFetch from '@wordpress/api-fetch';

/**
 * Shared configuration in Gutenberg's normal save and publishing transaction.
 *
 * @param {Object}  props      Component properties.
 * @param {boolean} props.exam Whether the exam profile controls the player design.
 * @return {Element} Timing, grading and player settings.
 */
export function NativeQuizConfiguration( { exam = false } ) {
	const value = useSelect(
		( getStore ) =>
			getStore( 'core/editor' ).getEditedPostAttribute(
				'ohmylms_quiz'
			) || {},
		[]
	);
	const { editPost } = useDispatch( 'core/editor' );
	const [ skills, setSkills ] = useState( [] );
	useEffect( () => {
		let active = true;
		listSkills( '', apiFetch )
			.then( ( data ) => active && setSkills( data.skills || [] ) )
			.catch( () => {} );
		return () => {
			active = false;
		};
	}, [] );
	const settings = value.settings || {};
	const drip = value.drip_settings || {};
	const paper = settings.presentation === 'paper';
	const change = ( patch ) =>
		editPost( { ohmylms_quiz: { ...value, ...patch } } );
	const set = ( key, next ) =>
		change( { settings: { ...settings, [ key ]: next } } );
	const nested = ( key, field, next ) =>
		set( key, { ...settings[ key ], [ field ]: next } );
	const booleanFields = [
		[ 'hide_answers', __( 'Hide answers on results page', 'ohmylms' ) ],
		[ 'show_correct_answers', __( 'Show correct answers', 'ohmylms' ) ],
		[ 'move_to_next_section', __( 'Continue without passing', 'ohmylms' ) ],
		[ 'randomize_questions', __( 'Shuffle question order', 'ohmylms' ) ],
		[ 'hide_question_number', __( 'Hide question numbers', 'ohmylms' ) ],
	];
	return (
		<>
			<SelectControl
				label={ __( 'Student presentation', 'ohmylms' ) }
				value={ paper ? 'paper' : 'standard' }
				options={ [
					{
						value: 'standard',
						label: __( 'Standard player', 'ohmylms' ),
					},
					{
						value: 'paper',
						label: __(
							'Paper View — all questions on one page',
							'ohmylms'
						),
					},
				] }
				help={ __(
					'Presentation does not change timing, attempts or result release. Use Student preview to try it.',
					'ohmylms'
				) }
				onChange={ ( next ) => set( 'presentation', next ) }
			/>
			<TextControl
				label={
					exam
						? __( 'Exam duration', 'ohmylms' )
						: __( 'Time allowed (0 for no limit)', 'ohmylms' )
				}
				help={
					exam
						? __(
								'Set a time limit for each student attempt.',
								'ohmylms'
							)
						: undefined
				}
				type="number"
				min="0"
				value={ settings.time_limit?.value ?? 0 }
				onChange={ ( next ) =>
					nested(
						'time_limit',
						'value',
						Math.max( 0, Number( next ) )
					)
				}
			/>
			<SelectControl
				label={ __( 'Time unit', 'ohmylms' ) }
				value={ settings.time_limit?.type || 'minutes' }
				options={ [
					{
						value: 'minutes',
						label: __( 'Minutes', 'ohmylms' ),
					},
					{ value: 'hours', label: __( 'Hours', 'ohmylms' ) },
					{ value: 'days', label: __( 'Days', 'ohmylms' ) },
				] }
				onChange={ ( next ) => nested( 'time_limit', 'type', next ) }
			/>
			<ToggleControl
				label={ __( 'Require passing grade', 'ohmylms' ) }
				checked={ Boolean( settings.passing_grade?.enabled ) }
				onChange={ ( next ) =>
					nested( 'passing_grade', 'enabled', next )
				}
			/>
			{ settings.passing_grade?.enabled && (
				<TextControl
					label={ __( 'Passing marks', 'ohmylms' ) }
					type="number"
					min="0"
					step="any"
					value={ settings.passing_grade?.value ?? 0 }
					onChange={ ( next ) =>
						nested(
							'passing_grade',
							'value',
							Math.max( 0, Number( next ) )
						)
					}
				/>
			) }
			<TextControl
				label={ __( 'Attempts allowed', 'ohmylms' ) }
				type="number"
				min="0"
				value={ Number( settings.allow_attempts ) || 1 }
				onChange={ ( next ) =>
					set(
						'allow_attempts',
						Math.max( 1, Math.trunc( Number( next ) ) )
					)
				}
			/>
			{ booleanFields.map( ( [ key, label ] ) => (
				<ToggleControl
					key={ key }
					label={ label }
					checked={ Boolean(
						key === 'show_correct_answers'
							? ( settings.show_correct_answers ?? true ) &&
									! settings.hide_answers
							: settings[ key ]
					) }
					disabled={
						key === 'show_correct_answers' &&
						Boolean( settings.hide_answers )
					}
					onChange={ ( next ) => set( key, next ) }
				/>
			) ) }
			{ ! exam && ! paper && (
				<SelectControl
					label={ __( 'Question layout', 'ohmylms' ) }
					value={ settings.layout || 'one_question_per_page' }
					options={ [
						{
							value: 'one_question_per_page',
							label: __( 'One question per page', 'ohmylms' ),
						},
						{
							value: 'all_questions_in_one_page',
							label: __( 'All questions', 'ohmylms' ),
						},
						{
							value: 'number_of_questions_per_page',
							label: __( 'Questions per page', 'ohmylms' ),
						},
					] }
					onChange={ ( next ) => set( 'layout', next ) }
				/>
			) }
			{ ! exam &&
				! paper &&
				settings.layout === 'number_of_questions_per_page' && (
					<TextControl
						label={ __( 'Questions per page', 'ohmylms' ) }
						type="number"
						min="1"
						value={ settings.question_in_one_page || 1 }
						onChange={ ( next ) =>
							set(
								'question_in_one_page',
								Math.max( 1, Number( next ) )
							)
						}
					/>
				) }
			{ [
				[
					'short_text_limit',
					__( 'Short answer character limit', 'ohmylms' ),
				],
				[
					'long_text_limit',
					__( 'Long answer character limit', 'ohmylms' ),
				],
			].map( ( [ key, label ] ) => (
				<TextControl
					key={ key }
					label={ label }
					type="number"
					min="0"
					value={ settings[ key ] ?? 0 }
					onChange={ ( next ) =>
						set( key, Math.max( 0, Number( next ) ) )
					}
				/>
			) ) }
			{ ! exam && ! paper && (
				<SelectControl
					label={ __( 'Player design', 'ohmylms' ) }
					value={ settings.player_template || 'classic' }
					options={
						window.ohmylmsPlayerTemplates || [
							{
								value: 'classic',
								label: __( 'Classic', 'ohmylms' ),
							},
						]
					}
					onChange={ ( next ) => set( 'player_template', next ) }
				/>
			) }
			{ exam && ! paper && (
				<p>
					{ __(
						'Exams use the student dashboard with a timer, question palette and review controls.',
						'ohmylms'
					) }
				</p>
			) }
			<ToggleControl
				label={ __( 'Scheduled course release', 'ohmylms' ) }
				checked={ Boolean( drip.enable ) }
				onChange={ ( next ) =>
					change( { drip_settings: { ...drip, enable: next } } )
				}
			/>
			{ drip.enable && (
				<>
					<SelectControl
						label={ __( 'Release rule', 'ohmylms' ) }
						value={ drip.type || 'enrollment-from-x-days' }
						options={ [
							{
								value: 'enrollment-from-x-days',
								label: __( 'Days after enrollment', 'ohmylms' ),
							},
							{
								value: 'cohort-from-x-days',
								label: __(
									'Days after cohort starts',
									'ohmylms'
								),
							},
							{
								value: 'cohort-start',
								label: __( 'Cohort start', 'ohmylms' ),
							},
							{
								value: 'specific-date',
								label: __( 'Specific date', 'ohmylms' ),
							},
						] }
						onChange={ ( next ) =>
							change( { drip_settings: { ...drip, type: next } } )
						}
					/>
					{ drip.type === 'specific-date' ? (
						<>
							<TextControl
								label={ __( 'Release date', 'ohmylms' ) }
								type="date"
								value={ ( drip.date || '' ).slice( 0, 10 ) }
								onChange={ ( next ) =>
									change( {
										drip_settings: { ...drip, date: next },
									} )
								}
							/>
							<TextControl
								label={ __( 'Release time', 'ohmylms' ) }
								type="time"
								value={ String( drip.time || '' )
									.replace( /^.*T/, '' )
									.slice( 0, 5 ) }
								onChange={ ( next ) =>
									change( {
										drip_settings: { ...drip, time: next },
									} )
								}
							/>
						</>
					) : (
						drip.type !== 'cohort-start' && (
							<TextControl
								label={ __( 'Days', 'ohmylms' ) }
								type="number"
								min="0"
								value={ drip.days ?? 1 }
								onChange={ ( next ) =>
									change( {
										drip_settings: {
											...drip,
											days: Math.max( 0, Number( next ) ),
										},
									} )
								}
							/>
						)
					) }
				</>
			) }
			<PanelBody
				title={ __( 'Skills', 'ohmylms' ) }
				initialOpen={ false }
			>
				<fieldset>
					<legend>{ __( 'Assessment skills', 'ohmylms' ) }</legend>
					{ skills.map( ( skill ) => (
						<CheckboxControl
							key={ skill.id }
							label={ skill.name }
							checked={ ( value.skill_ids || [] ).includes(
								skill.id
							) }
							onChange={ ( checked ) =>
								change( {
									skill_ids: checked
										? [
												...( value.skill_ids || [] ),
												skill.id,
											]
										: ( value.skill_ids || [] ).filter(
												( id ) => id !== skill.id
											),
								} )
							}
						/>
					) ) }
				</fieldset>
			</PanelBody>
		</>
	);
}
