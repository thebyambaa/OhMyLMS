// The JSX transform here compiles to createElement, so the import is required even though lint cannot see it.
import { createElement, useEffect, useState } from '@wordpress/element';
import {
	Button,
	ToggleControl,
	TextControl,
	SelectControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const XP_PATH = '/ohmylms/v1/engagement/settings/xp';
const SCORE_PATH = '/ohmylms/v1/engagement/settings/score';
const MASTERY_PATH = '/ohmylms/v1/engagement/settings/mastery';
const BAND_LABELS = [
	__( 'Getting started (0–69)', 'ohmylms' ),
	__( 'Practising (70–79)', 'ohmylms' ),
	__( 'Nearly there (80–89)', 'ohmylms' ),
	__( 'Mastering (90–100)', 'ohmylms' ),
];

/**
 * "10, 20, 30" → [ 10, 20, 30 ]; anything that is not a number is dropped.
 *
 * @param {string} text Comma or space separated numbers.
 */
export function parseGoals( text ) {
	return String( text )
		.split( /[\s,;]+/ )
		.map( ( part ) => Number( part ) )
		.filter( ( value ) => Number.isFinite( value ) && value > 0 );
}

export function XpMasterySettings() {
	const [ xp, setXp ] = useState( null );
	const [ score, setScore ] = useState( null );
	const [ mastery, setMastery ] = useState( null );
	const [ goalText, setGoalText ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( null );
	useEffect( () => {
		Promise.all( [
			window.wp.apiFetch( { path: XP_PATH } ),
			window.wp.apiFetch( { path: SCORE_PATH } ),
			window.wp.apiFetch( { path: MASTERY_PATH } ),
		] )
			.then( ( [ xpValue, scoreValue, masteryValue ] ) => {
				setMastery( masteryValue );
				setXp( xpValue );
				setGoalText( ( xpValue.goals || [] ).join( ', ' ) );
				setScore( scoreValue );
			} )
			.catch( ( error ) =>
				setMessage( { status: 'error', text: error.message } )
			);
	}, [] );
	if ( ! xp || ! score || ! mastery ) {
		return message ? (
			<Notice status="error" isDismissible={ false }>
				{ message.text }
			</Notice>
		) : (
			<Spinner />
		);
	}
	const setX = ( key, value ) =>
		setXp( ( previous ) => ( { ...previous, [ key ]: value } ) );
	const setS = ( key, value ) =>
		setScore( ( previous ) => ( { ...previous, [ key ]: value } ) );
	const setBand = ( index, key, value ) =>
		setS(
			'bands',
			score.bands.map( ( band, position ) =>
				position === index ? { ...band, [ key ]: value } : band
			)
		);
	const toNumber = ( value ) => ( value === '' ? '' : Number( value ) );
	const xpNumber = ( key, label, min, max ) => (
		<TextControl
			key={ key }
			type="number"
			label={ label }
			min={ min }
			max={ max }
			value={ xp[ key ] }
			onChange={ ( value ) => setX( key, toNumber( value ) ) }
		/>
	);
	const scoreNumber = ( key, label, min, max, step ) => (
		<TextControl
			key={ key }
			type="number"
			label={ label }
			min={ min }
			max={ max }
			step={ step }
			value={ score[ key ] }
			onChange={ ( value ) => setS( key, toNumber( value ) ) }
		/>
	);
	return (
		<form
			className="ohmylms-streak-settings ohmylms-xp-settings"
			onSubmit={ async ( event ) => {
				event.preventDefault();
				if ( saving ) {
					return;
				}
				setSaving( true );
				setMessage( null );
				try {
					await window.wp.apiFetch( {
						path: XP_PATH,
						method: 'POST',
						data: { ...xp, goals: parseGoals( goalText ) },
					} );
					await window.wp.apiFetch( {
						path: SCORE_PATH,
						method: 'POST',
						data: score,
					} );
					await window.wp.apiFetch( {
						path: MASTERY_PATH,
						method: 'POST',
						data: {
							policy: mastery.policy,
							surfaces: mastery.surfaces,
							show_score: mastery.show_score,
							ai_insights: mastery.ai_insights,
						},
					} );
					// Saving answers with a status; read back what was stored (values are normalised on save).
					const savedXp = await window.wp.apiFetch( {
						path: XP_PATH,
					} );
					const savedScore = await window.wp.apiFetch( {
						path: SCORE_PATH,
					} );
					setXp( savedXp );
					setGoalText( ( savedXp.goals || [] ).join( ', ' ) );
					setScore( savedScore );
					setMastery(
						await window.wp.apiFetch( { path: MASTERY_PATH } )
					);
					setMessage( {
						status: 'success',
						text: __( 'XP and mastery settings saved.', 'ohmylms' ),
					} );
				} catch ( error ) {
					setMessage( { status: 'error', text: error.message } );
				} finally {
					setSaving( false );
				}
			} }
		>
			<header className="ohmylms-streak-settings__header">
				<h2>{ __( 'XP and mastery score', 'ohmylms' ) }</h2>
				<p>
					{ __(
						'XP rewards effort and never gets spent; points stay your spendable currency. The mastery score (0–100) shows how well a learner knows each skill.',
						'ohmylms'
					) }
				</p>
			</header>
			{ message && (
				<Notice status={ message.status } isDismissible={ false }>
					{ message.text }
				</Notice>
			) }
			<fieldset
				disabled={ saving }
				className="ohmylms-streak-settings__fields"
			>
				<div className="ohmylms-streak-settings__grid">
					<section className="ohmylms-streak-settings__card">
						<SelectControl
							label={ __(
								'How skill levels are decided',
								'ohmylms'
							) }
							value={ mastery.policy }
							options={ ( mastery.policies || [] ).map(
								( policy ) => ( {
									label: policy.label,
									value: policy.id,
								} )
							) }
							onChange={ ( value ) =>
								setMastery( { ...mastery, policy: value } )
							}
						/>
						<p>
							{
								( mastery.policies || [] ).find(
									( policy ) => policy.id === mastery.policy
								)?.description
							}
						</p>
						<p>
							{ __(
								'Every policy reads the same answers, so you can switch at any time: levels are rebuilt from the records in the background. The step policy also lets a learner try a wrong practice answer again.',
								'ohmylms'
							) }
						</p>
						<fieldset className="ohmylms-streak-settings__fields">
							<legend>
								{ __(
									'Answers that count toward skills',
									'ohmylms'
								) }
							</legend>
							{ ( mastery.surface_options || [] ).map(
								( surface ) => (
									<ToggleControl
										key={ surface.id }
										label={ surface.label }
										checked={
											mastery.surfaces?.[ surface.id ] ??
											surface.counts
										}
										onChange={ ( value ) =>
											setMastery( {
												...mastery,
												surfaces: {
													...mastery.surfaces,
													[ surface.id ]: value,
												},
											} )
										}
									/>
								)
							) }
						</fieldset>
						<ToggleControl
							label={ __(
								'Show the mastery score beside the level',
								'ohmylms'
							) }
							checked={ !! mastery.show_score }
							onChange={ ( value ) =>
								setMastery( { ...mastery, show_score: value } )
							}
						/>
						<ToggleControl
							label={ __( 'Write AI study notes', 'ohmylms' ) }
							checked={ !! mastery.ai_insights }
							disabled={ ! mastery.ai_ready }
							onChange={ ( value ) =>
								setMastery( { ...mastery, ai_insights: value } )
							}
							help={
								mastery.ai_ready
									? __(
											'Learners and teachers can ask for a short note about a learner’s skills. The AI explains; it never changes a level or a score. Only skill names and numbers are sent.',
											'ohmylms'
										)
									: __(
											'Connect an AI provider under AI settings first.',
											'ohmylms'
										)
							}
						/>
					</section>
					<section className="ohmylms-streak-settings__card">
						<ToggleControl
							label={ __( 'Enable XP', 'ohmylms' ) }
							checked={ xp.enable }
							onChange={ ( value ) => setX( 'enable', value ) }
						/>
						<p>
							{ __(
								'Learners earn XP for completing practice lessons, with a bonus for a perfect lesson and extra XP for first-try answers in the challenge zone (mastery score 90 and above). Replayed questions never earn XP.',
								'ohmylms'
							) }
						</p>
						{ xpNumber(
							'lesson',
							__( 'XP per completed lesson', 'ohmylms' ),
							0,
							1000
						) }
						{ xpNumber(
							'perfect',
							__( 'Bonus for a perfect lesson', 'ohmylms' ),
							0,
							1000
						) }
						{ xpNumber(
							'challenge',
							__(
								'XP per first-try answer in the challenge zone',
								'ohmylms'
							),
							0,
							100
						) }
						{ xpNumber(
							'min_answered',
							__(
								'Minimum answers for a lesson to earn XP',
								'ohmylms'
							),
							1,
							50
						) }
						{ xpNumber(
							'daily_cap',
							__( 'Daily XP limit', 'ohmylms' ),
							1,
							100000
						) }
					</section>
					<section className="ohmylms-streak-settings__card">
						<h3>{ __( 'Daily goal', 'ohmylms' ) }</h3>
						<p>
							{ __(
								'Learners pick one of these goals. Reaching it once a day shows a celebration and can pay a one-time bonus.',
								'ohmylms'
							) }
						</p>
						<TextControl
							label={ __(
								'Goal choices (XP, comma separated)',
								'ohmylms'
							) }
							value={ goalText }
							onChange={ setGoalText }
						/>
						{ xpNumber(
							'default_goal',
							__( 'Default goal', 'ohmylms' ),
							1,
							100000
						) }
						{ xpNumber(
							'goal_bonus',
							__(
								'Bonus points for reaching the goal',
								'ohmylms'
							),
							0,
							10000
						) }
						<p>
							{ __(
								'Bonus points need the Bonus Point feature to be enabled.',
								'ohmylms'
							) }
						</p>
					</section>
				</div>
				<section className="ohmylms-streak-settings__card">
					<ToggleControl
						label={ __( 'Enable mastery score', 'ohmylms' ) }
						checked={ score.enable }
						onChange={ ( value ) => setS( 'enable', value ) }
					/>
					<p>
						{ __(
							'Every skill gets a score from 0 to 100, built from first-try answers. Correct answers raise it, mistakes lower it, harder questions count for more, and a hint halves the gain. The score never falls below the start of its band (70, 80, 90) so one slip cannot undo progress. Bronze, silver and gold medals mark 80, 90 and 100.',
							'ohmylms'
						) }
					</p>
					<h3>{ __( 'Score bands', 'ohmylms' ) }</h3>
					<p>
						{ __(
							'Points gained for a correct answer and lost for a mistake, before the difficulty multiplier.',
							'ohmylms'
						) }
					</p>
					{ score.bands.map( ( band, index ) => (
						<fieldset
							key={ index }
							className="ohmylms-streak-settings__milestone"
						>
							<legend>{ BAND_LABELS[ index ] }</legend>
							<div className="ohmylms-streak-settings__milestone-fields">
								<TextControl
									type="number"
									label={ __(
										'Gain per correct answer',
										'ohmylms'
									) }
									min={ 0 }
									max={ 50 }
									step={ 0.5 }
									value={ band.gain }
									onChange={ ( value ) =>
										setBand(
											index,
											'gain',
											toNumber( value )
										)
									}
								/>
								<TextControl
									type="number"
									label={ __(
										'Loss per mistake',
										'ohmylms'
									) }
									min={ 0 }
									max={ 50 }
									step={ 0.5 }
									value={ band.loss }
									onChange={ ( value ) =>
										setBand(
											index,
											'loss',
											toNumber( value )
										)
									}
								/>
							</div>
						</fieldset>
					) ) }
					{ scoreNumber(
						'hint_gain',
						__(
							'Share of the gain kept after a hint (0–1)',
							'ohmylms'
						),
						0,
						1,
						0.05
					) }
					{ scoreNumber(
						'unlock',
						__( 'Score that unlocks the next skill', 'ohmylms' ),
						1,
						100,
						1
					) }
					{ scoreNumber(
						'decay_grace_days',
						__(
							'Idle days before the score starts to fade',
							'ohmylms'
						),
						0,
						365,
						1
					) }
					{ scoreNumber(
						'decay_per_week',
						__(
							'Points lost per idle week (never below 90)',
							'ohmylms'
						),
						0,
						20,
						0.5
					) }
				</section>
				<div className="ohmylms-streak-settings__footer">
					<Button
						variant="primary"
						type="submit"
						isBusy={ saving }
						disabled={ saving }
					>
						{ __( 'Save XP and mastery settings', 'ohmylms' ) }
					</Button>
				</div>
			</fieldset>
		</form>
	);
}
