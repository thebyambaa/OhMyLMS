import { createElement, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	RangeControl,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	COLOR_FIELDS,
	DENSITIES,
	RADIUS_PRESETS,
	applyPreset,
	radiusChoice,
	textOn,
	whitePassesAA,
	previewVariables,
	withColor,
	withDensity,
	withRadius,
} from './designSystem.mjs';
import { HubIconSettings } from './HubIconSettings';

const PATH = '/ohmylms/v1/design-system';

function colorLabels() {
	return {
		primary: __( 'Primary (main buttons)', 'ohmylms' ),
		secondary: __( 'Secondary (button outline)', 'ohmylms' ),
		success: __( 'Success', 'ohmylms' ),
		danger: __( 'Danger (delete)', 'ohmylms' ),
		warning: __( 'Warning', 'ohmylms' ),
		link: __( 'Links', 'ohmylms' ),
		heading: __( 'Headings', 'ohmylms' ),
		text: __( 'Body text', 'ohmylms' ),
		muted: __( 'Muted text', 'ohmylms' ),
	};
}

function roundnessLabels() {
	return {
		square: __( 'Square', 'ohmylms' ),
		slight: __( 'Slightly rounded', 'ohmylms' ),
		rounded: __( 'Rounded', 'ohmylms' ),
		pill: __( 'Pill', 'ohmylms' ),
	};
}

function densityLabels() {
	return {
		compact: __( 'Compact', 'ohmylms' ),
		comfortable: __( 'Comfortable', 'ohmylms' ),
		spacious: __( 'Spacious', 'ohmylms' ),
	};
}

function ColorField( { id, label, value, onChange } ) {
	const [ draft, setDraft ] = useState( value );
	useEffect( () => setDraft( value ), [ value ] );
	const commit = ( next ) => {
		setDraft( next );
		onChange( next );
	};
	return (
		<div className="ohmylms-ds-color">
			<label htmlFor={ id }>{ label }</label>
			<div className="ohmylms-ds-color-inputs">
				<input
					type="color"
					id={ id }
					value={ value }
					onChange={ ( event ) => commit( event.target.value ) }
					aria-label={ label }
				/>
				<input
					type="text"
					value={ draft }
					maxLength={ 7 }
					spellCheck={ false }
					onChange={ ( event ) => commit( event.target.value ) }
					onBlur={ () => setDraft( value ) }
					aria-label={ label + ' (hex)' }
				/>
			</div>
		</div>
	);
}

function Preview( { settings } ) {
	return (
		<div
			className="ohmylms-ds-preview"
			style={ previewVariables( settings ) }
			aria-label={ __( 'Preview', 'ohmylms' ) }
		>
			<h3>{ __( 'Preview', 'ohmylms' ) }</h3>
			<div className="ohmylms-ds-preview-row">
				<Button variant="primary">
					{ __( 'Primary', 'ohmylms' ) }
				</Button>
				<Button variant="secondary">
					{ __( 'Secondary', 'ohmylms' ) }
				</Button>
				<Button variant="tertiary">
					{ __( 'Tertiary', 'ohmylms' ) }
				</Button>
				<Button variant="link">{ __( 'Link', 'ohmylms' ) }</Button>
				<Button variant="primary" isDestructive>
					{ __( 'Delete', 'ohmylms' ) }
				</Button>
				<Button variant="primary" disabled>
					{ __( 'Disabled', 'ohmylms' ) }
				</Button>
			</div>
			<div className="ohmylms-ds-preview-row">
				<button type="button" className="button button-primary">
					{ __( 'WordPress primary', 'ohmylms' ) }
				</button>
				<button type="button" className="button">
					{ __( 'WordPress button', 'ohmylms' ) }
				</button>
				<input
					type="text"
					readOnly
					value={ __( 'Text input', 'ohmylms' ) }
					aria-label={ __( 'Text input example', 'ohmylms' ) }
				/>
			</div>
			<div className="card">
				<strong>{ __( 'Card heading', 'ohmylms' ) }</strong>
				<p>
					{ __(
						'Cards, tables and notices use the same colors, spacing and roundness.',
						'ohmylms'
					) }
				</p>
			</div>
			<div className="notice notice-success inline">
				<p>{ __( 'Saved successfully.', 'ohmylms' ) }</p>
			</div>
			<table className="widefat striped">
				<thead>
					<tr>
						<th>{ __( 'Course', 'ohmylms' ) }</th>
						<th>{ __( 'Status', 'ohmylms' ) }</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>{ __( 'Fractions', 'ohmylms' ) }</td>
						<td>{ __( 'Published', 'ohmylms' ) }</td>
					</tr>
					<tr>
						<td>{ __( 'Geometry', 'ohmylms' ) }</td>
						<td>{ __( 'Draft', 'ohmylms' ) }</td>
					</tr>
				</tbody>
			</table>
		</div>
	);
}

export function DesignSystemSettings() {
	const [ data, setData ] = useState( null );
	const [ form, setForm ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ dirty, setDirty ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const [ fieldErrors, setFieldErrors ] = useState( {} );

	const load = ( snapshot ) => {
		const { presets, on_primary: onPrimary, ...settings } = snapshot;
		setData( { presets, onPrimary } );
		setForm( {
			preset: settings.preset,
			colors: settings.colors,
			button_radius: settings.button_radius,
			density: settings.density,
		} );
		setDirty( false );
		setFieldErrors( {} );
	};

	useEffect( () => {
		let active = true;
		apiFetch( { path: PATH } )
			.then( ( snapshot ) => active && load( snapshot ) )
			.catch(
				( error ) =>
					active &&
					setMessage( { status: 'error', text: error.message } )
			);
		return () => {
			active = false;
		};
	}, [] );

	const change = ( next ) => {
		setForm( next );
		setDirty( true );
		setMessage( null );
	};

	const run = async ( action ) => {
		if ( busy ) {
			return;
		}
		setBusy( action );
		setMessage( null );
		try {
			if ( action === 'save' ) {
				load(
					await apiFetch( { path: PATH, method: 'POST', data: form } )
				);
				setMessage( {
					status: 'success',
					text: __(
						'Design system saved. Reload any admin screen to see it.',
						'ohmylms'
					),
				} );
			} else {
				load( await apiFetch( { path: PATH, method: 'DELETE' } ) );
				setMessage( {
					status: 'success',
					text: __(
						'Reset to the Khan Academy defaults.',
						'ohmylms'
					),
				} );
			}
		} catch ( error ) {
			setFieldErrors( error?.data?.fields || {} );
			setMessage( { status: 'error', text: error.message } );
		} finally {
			setBusy( '' );
		}
	};

	if ( ! data || ! form ) {
		return message ? (
			<Notice status="error" isDismissible={ false }>
				{ message.text }
			</Notice>
		) : (
			createElement( Spinner )
		);
	}

	const labels = colorLabels();
	const radiusNames = roundnessLabels();
	const densityNames = densityLabels();
	const choice = radiusChoice( form.button_radius );
	const presetOptions = [
		...data.presets.map( ( preset ) => ( {
			value: preset.preset,
			label:
				preset.preset === 'khan'
					? __( 'Khan Academy (default)', 'ohmylms' )
					: preset.label,
		} ) ),
		{ value: 'custom', label: __( 'Custom', 'ohmylms' ) },
	];
	const primary = form.colors.primary;

	return (
		<div className="ohmylms-streak-settings ohmylms-ds-settings">
			<header className="ohmylms-streak-settings__header">
				<h2>{ __( 'Design system', 'ohmylms' ) }</h2>
				<p>
					{ __(
						'One set of colors, button roundness and spacing for every OhMyLMS admin screen. Learner pages keep their own branding.',
						'ohmylms'
					) }
				</p>
			</header>
			{ message && (
				<Notice status={ message.status } isDismissible={ false }>
					{ message.text }
					{ Object.keys( fieldErrors ).length > 0 && (
						<ul>
							{ Object.entries( fieldErrors ).map(
								( [ field, text ] ) => (
									<li key={ field }>
										{ field }: { text }
									</li>
								)
							) }
						</ul>
					) }
				</Notice>
			) }
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					run( 'save' );
				} }
			>
				<fieldset
					disabled={ !! busy }
					className="ohmylms-streak-settings__fields"
				>
					<div className="ohmylms-ds-layout">
						<div className="ohmylms-ds-controls">
							<section className="ohmylms-streak-settings__card">
								<h3>{ __( 'Preset', 'ohmylms' ) }</h3>
								<SelectControl
									label={ __( 'Look', 'ohmylms' ) }
									value={ form.preset }
									options={ presetOptions }
									onChange={ ( value ) =>
										change(
											applyPreset(
												data.presets,
												form,
												value
											)
										)
									}
								/>
							</section>
							<section className="ohmylms-streak-settings__card">
								<h3>{ __( 'Colors', 'ohmylms' ) }</h3>
								<div className="ohmylms-ds-colors">
									{ COLOR_FIELDS.map( ( key ) => (
										<ColorField
											key={ key }
											id={ 'ohmylms-ds-' + key }
											label={ labels[ key ] }
											value={ form.colors[ key ] }
											onChange={ ( value ) =>
												change(
													withColor(
														data.presets,
														form,
														key,
														value
													)
												)
											}
										/>
									) ) }
								</div>
								<p className="ohmylms-ds-contrast">
									{ __(
										'Text on primary buttons is chosen automatically:',
										'ohmylms'
									) }{ ' ' }
									<strong>
										{ textOn( primary ) === '#ffffff'
											? __( 'white', 'ohmylms' )
											: __( 'black', 'ohmylms' ) }
									</strong>
								</p>
								{ ! whitePassesAA( primary ) && (
									<Notice
										status="warning"
										isDismissible={ false }
									>
										{ __(
											'This primary color does not reach the WCAG AA contrast (4.5:1) with white text, so black text is used on buttons. Pick a darker color if you want white text.',
											'ohmylms'
										) }
									</Notice>
								) }
							</section>
							<section className="ohmylms-streak-settings__card">
								<h3>{ __( 'Button roundness', 'ohmylms' ) }</h3>
								<div
									className="ohmylms-ds-choices"
									role="group"
									aria-label={ __(
										'Button roundness',
										'ohmylms'
									) }
								>
									{ Object.keys( RADIUS_PRESETS ).map(
										( key ) => (
											<Button
												key={ key }
												variant={
													choice === key
														? 'primary'
														: 'secondary'
												}
												aria-pressed={ choice === key }
												onClick={ () =>
													change(
														withRadius(
															data.presets,
															form,
															RADIUS_PRESETS[
																key
															]
														)
													)
												}
											>
												{ radiusNames[ key ] }
											</Button>
										)
									) }
								</div>
								<RangeControl
									label={ __( 'Radius (px)', 'ohmylms' ) }
									min={ 0 }
									max={ 24 }
									value={ Math.min( form.button_radius, 24 ) }
									onChange={ ( value ) =>
										change(
											withRadius(
												data.presets,
												form,
												value
											)
										)
									}
								/>
								{ fieldErrors.button_radius && (
									<p role="alert">
										{ fieldErrors.button_radius }
									</p>
								) }
							</section>
							<section className="ohmylms-streak-settings__card">
								<h3>{ __( 'Spacing density', 'ohmylms' ) }</h3>
								<div
									className="ohmylms-ds-choices"
									role="group"
									aria-label={ __(
										'Spacing density',
										'ohmylms'
									) }
								>
									{ DENSITIES.map( ( key ) => (
										<Button
											key={ key }
											variant={
												form.density === key
													? 'primary'
													: 'secondary'
											}
											aria-pressed={
												form.density === key
											}
											onClick={ () =>
												change(
													withDensity(
														data.presets,
														form,
														key
													)
												)
											}
										>
											{ densityNames[ key ] }
										</Button>
									) ) }
								</div>
							</section>
						</div>
						<Preview settings={ form } />
					</div>
					<div className="ohmylms-streak-settings__footer">
						<Button
							variant="primary"
							type="submit"
							isBusy={ busy === 'save' }
							disabled={ ! dirty || !! busy }
						>
							{ __( 'Save design system', 'ohmylms' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => run( 'reset' ) }
							isBusy={ busy === 'reset' }
						>
							{ __(
								'Reset to Khan Academy defaults',
								'ohmylms'
							) }
						</Button>
					</div>
				</fieldset>
			</form>
			<HubIconSettings />
		</div>
	);
}
