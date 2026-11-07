import { createElement, useEffect, useState } from '@wordpress/element';
import { select, useDispatch, useSelect } from '@wordpress/data';
import {
	Button,
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { listSkills, loadBankQuestion } from '../question-bank/public.mjs';
import { SkillField } from './SkillField';

/**
 * IDs of the questions placed in the quiz, in document order.
 *
 * @return {number[]} Question IDs.
 */
function questionIds() {
	/**
	 * @param {import('@wordpress/blocks').BlockInstance} block Block.
	 * @return {number[]} Question IDs in the block and its inner blocks.
	 */
	const collect = ( block ) =>
		block.attributes?.questionId
			? [ block.attributes.questionId ]
			: ( block.innerBlocks || [] ).flatMap( collect );
	return [
		...new Set(
			select( 'core/block-editor' ).getBlocks().flatMap( collect )
		),
	];
}

/**
 * Shared configuration in Gutenberg's normal save and publishing transaction.
 *
 * @param {Object}  props      Component properties.
 * @param {boolean} props.exam Whether the exam profile controls the player design.
 * @return {React.ReactElement} Timing, grading and player settings.
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
	const [ names, setNames ] = useState(
		/** @type {Record<number, {name?: string, code?: string}>} */ ( {} )
	);
	const [ gathering, setGathering ] = useState( false );
	const [ gatherNote, setGatherNote ] = useState( '' );
	useEffect( () => {
		let active = true;
		listSkills( '' )
			.then(
				( data ) =>
					active &&
					setNames(
						Object.fromEntries(
							( data.skills || [] ).map(
								/** @param {{ id: number }} skill Skill. */
								( skill ) => [ skill.id, skill ]
							)
						)
					)
			)
			.catch( () => {} );
		return () => {
			active = false;
		};
	}, [] );
	const settings = value.settings || {};
	const drip = value.drip_settings || {};
	const paper = settings.presentation === 'paper';
	/** @param {Record<string, unknown>} patch Changed quiz fields. */
	const change = ( patch ) =>
		editPost( { ohmylms_quiz: { ...value, ...patch } } );
	/**
	 * @param {string}  key  Setting.
	 * @param {unknown} next New value.
	 */
	const set = ( key, next ) =>
		change( { settings: { ...settings, [ key ]: next } } );
	/**
	 * @param {string}  key   Setting.
	 * @param {string}  field Field of the setting.
	 * @param {unknown} next  New value.
	 */
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
			<PanelBody title={ __( 'Skills', 'ohmylms' ) } initialOpen>
				<p>
					{ __(
						'The skills this quiz assesses as a whole. What a student’s answers count towards is set on each question: select a question block to choose its skill.',
						'ohmylms'
					) }
				</p>
				<SkillField
					label={ __( 'Quiz skills', 'ohmylms' ) }
					value={ value.skill_ids || [] }
					names={ names }
					onChange={ ( ids, picked ) => {
						setNames( ( current ) => ( {
							...current,
							...Object.fromEntries(
								picked.map( ( skill ) => [ skill.id, skill ] )
							),
						} ) );
						change( { skill_ids: ids } );
					} }
				/>
				<Button
					variant="tertiary"
					isBusy={ gathering }
					disabled={ gathering }
					onClick={ async () => {
						setGathering( true );
						setGatherNote( '' );
						try {
							const loaded = await Promise.all(
								questionIds().map( ( id ) =>
									loadBankQuestion( id )
								)
							);
							const found = loaded.flatMap( ( question ) =>
								Object.values( question.skill_map || {} )
									.map(
										/** @param {{ primary?: number }} roles Roles. */
										( roles ) =>
											Number( roles.primary ) || 0
									)
									.filter( Boolean )
							);
							const merged = [
								...new Set( [
									...( value.skill_ids || [] ),
									...found,
								] ),
							];
							change( { skill_ids: merged } );
							setGatherNote(
								found.length
									? __(
											'Added the primary skills of the questions.',
											'ohmylms'
										)
									: __(
											'None of the questions has a primary skill yet.',
											'ohmylms'
										)
							);
						} catch ( cause ) {
							setGatherNote(
								/** @type {{ message: string }} */ ( cause )
									.message
							);
						} finally {
							setGathering( false );
						}
					} }
				>
					{ __( 'Add the skills of the questions', 'ohmylms' ) }
				</Button>
				{ gatherNote && (
					<Notice
						status="info"
						onRemove={ () => setGatherNote( '' ) }
					>
						{ gatherNote }
					</Notice>
				) }
			</PanelBody>
		</>
	);
}
