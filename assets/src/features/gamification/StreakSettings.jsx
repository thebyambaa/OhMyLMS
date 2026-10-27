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
export function StreakSettings( { BadgeEditor } ) {
	const [ settings, setSettings ] = useState( null );
	const [ badges, setBadges ] = useState( [] );
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const [ badgeMilestone, setBadgeMilestone ] = useState( null );
	const refreshBadges = async () => {
		try {
			setBadges(
				await window.wp.apiFetch( {
					path: '/ohmylms/v1/engagement/badges?include_streak=1',
				} )
			);
		} catch ( error ) {
			setMessage( {
				status: 'error',
				text: error.message,
			} );
		}
	};
	const set = ( key, value ) =>
		setSettings( ( previous ) => ( {
			...previous,
			[ key ]: value,
		} ) );
	useEffect( () => {
		Promise.all( [
			window.wp.apiFetch( {
				path: '/ohmylms/v1/engagement/settings/streak',
			} ),
			window.wp.apiFetch( {
				path: '/ohmylms/v1/engagement/badges?include_streak=1',
			} ),
		] )
			.then( ( [ value, available ] ) => {
				setSettings( value );
				setBadges( available );
			} )
			.catch( ( error ) =>
				setMessage( {
					status: 'error',
					text: error.message,
				} )
			);
	}, [] );
	if ( ! settings ) {
		return message ? (
			<Notice status="error" isDismissible={ false }>
				{ message.text }
			</Notice>
		) : (
			<Spinner />
		);
	}
	const number = ( key, label, min, max ) => (
		<TextControl
			key={ key }
			type="number"
			label={ label }
			min={ min }
			max={ max }
			value={ settings[ key ] }
			onChange={ ( value ) =>
				set( key, value === '' ? '' : Number( value ) )
			}
		/>
	);
	return (
		<form
			className="ohmylms-streak-settings"
			onSubmit={ async ( event ) => {
				event.preventDefault();
				if ( saving ) {
					return;
				}
				setSaving( true );
				setMessage( null );
				try {
					await window.wp.apiFetch( {
						path: '/ohmylms/v1/engagement/settings/streak',
						method: 'POST',
						data: settings,
					} );
					setMessage( {
						status: 'success',
						text: __( 'Streak settings saved.', 'ohmylms' ),
					} );
				} catch ( error ) {
					setMessage( {
						status: 'error',
						text: error.message,
					} );
				} finally {
					setSaving( false );
				}
			} }
		>
			<header className="ohmylms-streak-settings__header">
				<h2>{ __( 'Daily learning streaks', 'ohmylms' ) }</h2>
				<p>
					{ __(
						'Help learners build a daily habit across all courses and course bundles.',
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
				<section className="ohmylms-streak-settings__card">
					<ToggleControl
						label={ __( 'Enable learning streaks', 'ohmylms' ) }
						checked={ settings.enable }
						onChange={ ( value ) => set( 'enable', value ) }
					/>
					<p>
						{ __(
							'Learners earn one streak day by completing any qualifying activity. Incorrect answers count as participation. Signing in or opening content does not count.',
							'ohmylms'
						) }
					</p>
				</section>
				<div className="ohmylms-streak-settings__grid">
					<section className="ohmylms-streak-settings__card">
						<h3>{ __( 'Qualifying activities', 'ohmylms' ) }</h3>
						<p>
							{ __(
								'Choose which learning activities count toward a streak.',
								'ohmylms'
							) }
						</p>
						{ [
							[
								'lesson',
								__( 'Verified lesson completion', 'ohmylms' ),
							],
							[
								'quiz',
								__(
									'Submitted quiz with answers attempted',
									'ohmylms'
								),
							],
							[
								'practice',
								__(
									'Completed skill practice session',
									'ohmylms'
								),
							],
						].map( ( [ key, label ] ) => (
							<ToggleControl
								key={ key }
								label={ label }
								checked={ settings[ key ] }
								onChange={ ( value ) => set( key, value ) }
							/>
						) ) }
						{ number(
							'practice_minimum',
							__( 'Minimum practice answers', 'ohmylms' ),
							1,
							30
						) }
						<p>
							{ __(
								'Applies to completed skill practice sessions.',
								'ohmylms'
							) }
						</p>
					</section>
					<section className="ohmylms-streak-settings__card">
						<h3>{ __( 'Streak freezes', 'ohmylms' ) }</h3>
						<p>
							{ __(
								'Protect a streak when a learner misses a day.',
								'ohmylms'
							) }
						</p>
						<ToggleControl
							label={ __( 'Enable freezes', 'ohmylms' ) }
							checked={ settings.freezes }
							onChange={ ( value ) => set( 'freezes', value ) }
						/>
						<fieldset
							disabled={ ! settings.freezes }
							className="ohmylms-streak-settings__fields ohmylms-streak-settings__freeze-fields"
						>
							{ number(
								'initial_freezes',
								__( 'Starting freezes', 'ohmylms' ),
								0,
								10
							) }
							{ number(
								'maximum_freezes',
								__( 'Maximum freezes', 'ohmylms' ),
								0,
								10
							) }
							{ number(
								'refill_days',
								__(
									'Learning days to earn a freeze',
									'ohmylms'
								),
								1,
								365
							) }
						</fieldset>
						<details className="ohmylms-streak-settings__details">
							<summary>
								{ __( 'How freezes work', 'ohmylms' ) }
							</summary>
							<p>
								{ __(
									'One freeze protects one missed date without increasing the streak. Freezes are used in date order and must already be available. Only qualifying learning dates earn replacements; no refill credits accumulate while full. Lowering the cap trims inventory; raising initial inventory does not refill existing accounts.',
									'ohmylms'
								) }
							</p>
						</details>
					</section>
				</div>
				<section className="ohmylms-streak-settings__card">
					<h3>{ __( 'Timezone and history', 'ohmylms' ) }</h3>
					<p>
						{ __(
							'The learner timezone defaults to the site timezone and stays fixed during an active streak. It can change after a streak ends and 24 hours after the last activity. Past dates are preserved. Enabling streaks starts fresh; no historical activity is imported.',
							'ohmylms'
						) }
					</p>
				</section>
				<section className="ohmylms-streak-settings__card">
					<div className="ohmylms-streak-settings__section-heading">
						<h3>
							{ __( 'One-time milestone rewards', 'ohmylms' ) }
						</h3>
					</div>
					<p>
						{ __(
							'Each milestone is awarded once per learner. Badge rewards use the existing badge system. Optional points require the Bonus Point feature to be enabled; pending rewards retry after it is enabled.',
							'ohmylms'
						) }
					</p>
					{ settings.milestones.map( ( milestone, index ) => {
						const update = ( key, value ) =>
							set(
								'milestones',
								settings.milestones.map( ( item, position ) =>
									position === index
										? {
												...item,
												[ key ]: value,
											}
										: item
								)
							);
						return (
							<fieldset
								key={ index }
								className="ohmylms-streak-settings__milestone"
							>
								<legend>
									{ __( 'Milestone', 'ohmylms' ) }{ ' ' }
									{ index + 1 }
								</legend>
								<div className="ohmylms-streak-settings__milestone-fields">
									<TextControl
										type="number"
										label={ __( 'Streak days', 'ohmylms' ) }
										min={ 1 }
										max={ 10000 }
										value={ milestone.days }
										onChange={ ( value ) =>
											update(
												'days',
												value === ''
													? ''
													: Number( value )
											)
										}
									/>
									<div className="ohmylms-streak-settings__badge-field">
										<SelectControl
											label={ __( 'Badge', 'ohmylms' ) }
											value={ milestone.badge }
											onChange={ ( value ) =>
												update( 'badge', value )
											}
											options={ [
												{
													label: __(
														'No badge',
														'ohmylms'
													),
													value: '',
												},
												...badges.map( ( badge ) => ( {
													label: badge.name,
													value: badge.slug,
												} ) ),
											] }
										/>
										<Button
											variant="link"
											disabled={ saving }
											onClick={ () =>
												setBadgeMilestone( index )
											}
										>
											{ __( 'Add new badge', 'ohmylms' ) }
										</Button>
									</div>
									<TextControl
										type="number"
										label={ __(
											'Bonus points',
											'ohmylms'
										) }
										min={ 0 }
										max={ 100000 }
										value={ milestone.points }
										onChange={ ( value ) =>
											update(
												'points',
												value === ''
													? ''
													: Number( value )
											)
										}
									/>
									<Button
										variant="tertiary"
										isDestructive
										label={ `${ __( 'Remove milestone', 'ohmylms' ) } ${ index + 1 }` }
										onClick={ () =>
											set(
												'milestones',
												settings.milestones.filter(
													( _, position ) =>
														position !== index
												)
											)
										}
									>
										{ __( 'Remove milestone', 'ohmylms' ) }
									</Button>
								</div>
							</fieldset>
						);
					} ) }
					<Button
						variant="secondary"
						disabled={ settings.milestones.length >= 20 }
						onClick={ () =>
							set( 'milestones', [
								...settings.milestones,
								{
									days:
										Math.max(
											0,
											...settings.milestones.map(
												( item ) =>
													Number( item.days ) || 0
											)
										) + 7,
									badge: '',
									points: 0,
								},
							] )
						}
					>
						{ __( 'Add milestone', 'ohmylms' ) }
					</Button>
				</section>
				<div className="ohmylms-streak-settings__footer">
					<Button
						variant="primary"
						type="submit"
						isBusy={ saving }
						disabled={ saving }
					>
						{ __( 'Save streak settings', 'ohmylms' ) }
					</Button>
				</div>
			</fieldset>
			{ badgeMilestone !== null && BadgeEditor && (
				<BadgeEditor
					isOpen
					awardSource="streak"
					onClose={ () => setBadgeMilestone( null ) }
					badgeList={ badges }
					setItems={ () => {} }
					fetchData={ refreshBadges }
					onCreated={ ( badge ) => {
						if ( ! badge?.slug ) {
							return;
						}
						setBadges( ( previous ) => [ ...previous, badge ] );
						setSettings( ( previous ) => ( {
							...previous,
							milestones: previous.milestones.map(
								( item, index ) =>
									index === badgeMilestone
										? {
												...item,
												badge: badge.slug,
											}
										: item
							),
						} ) );
					} }
				/>
			) }
		</form>
	);
}
