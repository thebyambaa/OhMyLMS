import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api } from './api';
import { Check, Field } from './field';

const EDITABLE = [
	'primary',
	'secondary',
	'success',
	'danger',
	'warning',
	'link',
	'heading',
	'text',
	'muted',
] as const;

const PLACES = [ 'profile', 'empty', 'celebrate', 'quiz' ] as const;

interface Colors {
	[ key: string ]: string;
}

interface Design {
	preset: string;
	colors: Colors;
	button_radius: number;
	density: string;
}

interface Mascot {
	kind: string;
	attachment_id: number;
	attachment_url?: string;
	name: string;
	greeting: string;
	placements: Record< string, boolean >;
}

interface Preset {
	name: string;
	label: string;
	colors: Colors;
	button_radius: number;
	density: string;
}

interface Payload {
	owner: boolean;
	preset: Design;
	presets: Preset[];
	font: string;
	fonts: string[];
	mascot: Mascot;
	schools: Array< {
		id: number;
		name: string;
		accent: string;
		mascot: Mascot | null;
	} >;
}

function emptyMascot(): Mascot {
	return {
		kind: 'none',
		attachment_id: 0,
		name: '',
		greeting: '',
		placements: {
			profile: true,
			empty: true,
			celebrate: true,
			quiz: false,
		},
	};
}

function fontStack( font: string ) {
	if ( 'inherit' === font || '' === font ) {
		return 'inherit';
	}
	const system =
		'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
	if ( 'system' === font ) {
		return system;
	}
	return `"${ font }", ${ system }`;
}

function channel( value: string ) {
	const hex = value.replace( '#', '' );
	const full =
		hex.length === 3
			? hex
					.split( '' )
					.map( ( part ) => part + part )
					.join( '' )
			: hex;
	const num = Number.parseInt( full, 16 );
	if ( Number.isNaN( num ) || full.length !== 6 ) {
		return 0;
	}
	const red = Number.parseInt( full.slice( 0, 2 ), 16 );
	const green = Number.parseInt( full.slice( 2, 4 ), 16 );
	const blue = Number.parseInt( full.slice( 4, 6 ), 16 );
	const lin = [ red, green, blue ].map( ( part ) => {
		const scaled = part / 255;
		return scaled <= 0.03928
			? scaled / 12.92
			: ( ( scaled + 0.055 ) / 1.055 ) ** 2.4;
	} );
	const l0 = lin[ 0 ] ?? 0;
	const l1 = lin[ 1 ] ?? 0;
	const l2 = lin[ 2 ] ?? 0;
	return 0.2126 * l0 + 0.7152 * l1 + 0.0722 * l2;
}

function onColor( background: string ) {
	const white = 1.05 / ( channel( background ) + 0.05 );
	const black = ( channel( background ) + 0.05 ) / 0.05;
	return white >= black ? '#ffffff' : '#000000';
}

function MascotMark( { kind }: { kind: string } ) {
	const shared = {
		viewBox: '0 0 64 64',
		'aria-hidden': true as const,
		focusable: false as const,
	};
	if ( 'owl' === kind ) {
		return (
			<svg { ...shared }>
				<ellipse cx="32" cy="38" rx="20" ry="22" fill="currentColor" />
				<circle cx="24" cy="32" r="8" fill="var(--oml-surface)" />
				<circle cx="40" cy="32" r="8" fill="var(--oml-surface)" />
				<circle cx="24" cy="33" r="4" fill="var(--oml-heading)" />
				<circle cx="40" cy="33" r="4" fill="var(--oml-heading)" />
			</svg>
		);
	}
	if ( 'robot' === kind ) {
		return (
			<svg { ...shared }>
				<rect
					x="12"
					y="14"
					width="40"
					height="32"
					rx="8"
					fill="currentColor"
				/>
				<rect
					x="18"
					y="22"
					width="28"
					height="14"
					rx="5"
					fill="var(--oml-surface)"
				/>
				<circle cx="26" cy="29" r="3" fill="var(--oml-heading)" />
				<circle cx="38" cy="29" r="3" fill="var(--oml-heading)" />
			</svg>
		);
	}
	if ( 'horse' === kind ) {
		return (
			<svg { ...shared }>
				<path
					d="M20 58V34c0-14 8-24 22-26l4 8-4 4 6 14-6 4-8-6-4 26z"
					fill="currentColor"
				/>
			</svg>
		);
	}
	if ( 'star' === kind ) {
		return (
			<svg { ...shared }>
				<path
					d="M32 4l8 18 20 2-15 13 5 20-18-11-18 11 5-20L4 24l20-2z"
					fill="currentColor"
				/>
			</svg>
		);
	}
	return null;
}

function Preview( {
	design,
	font,
	mascot,
	accent,
}: {
	design: Design;
	font: string;
	mascot: Mascot;
	accent: string;
} ) {
	const primary = /^#[0-9a-fA-F]{6}$/.test( accent )
		? accent
		: design.colors.primary || '';
	const ink = onColor( primary );
	const show = 'none' !== mascot.kind && mascot.placements.profile;
	const pads: Record< string, string > = {
		compact: '6px 12px',
		comfortable: '10px 16px',
		spacious: '14px 22px',
	};
	return (
		<aside className="oml-dash-preview" aria-live="polite">
			<h3>{ __( 'Live preview', 'ohmylms' ) }</h3>
			<p className="oml-dash-note">
				{ __(
					'Sample using these colours, the button radius, the density and the learner font. It is not a real learner.',
					'ohmylms'
				) }
			</p>
			<div
				className="oml-dash-preview-band"
				style={ {
					background: primary,
					color: ink,
					fontFamily: fontStack( font ),
				} }
			>
				{ show && (
					<span style={ { color: ink } }>
						{ 'upload' === mascot.kind && mascot.attachment_url ? (
							<img
								src={ mascot.attachment_url }
								alt=""
								width={ 48 }
								height={ 48 }
							/>
						) : (
							<MascotMark kind={ mascot.kind } />
						) }
					</span>
				) }
				<div>
					<strong>
						{ mascot.name || __( 'Sample learner', 'ohmylms' ) }
					</strong>
					{ mascot.greeting && <div>{ mascot.greeting }</div> }
				</div>
			</div>
			<p
				style={ {
					color: design.colors.heading,
					fontFamily: fontStack( font ),
				} }
			>
				{ __( 'Algebra 1', 'ohmylms' ) }
			</p>
			<button
				type="button"
				style={ {
					background: primary,
					color: ink,
					border: 0,
					borderRadius:
						design.button_radius >= 999
							? '999px'
							: `${ design.button_radius }px`,
					padding: pads[ design.density ] || pads.comfortable,
					fontFamily: fontStack( font ),
				} }
			>
				{ __( 'Continue', 'ohmylms' ) }
			</button>
		</aside>
	);
}

/**
 * Design system and mascot editors. Saves go through the same options as Settings → Design system.
 *
 * @param props        Props.
 * @param props.school Selected school.
 * @param props.focus  branding or mascot.
 */
export function Branding( {
	school,
	focus,
}: {
	school: number;
	focus: string;
} ) {
	const [ design, setDesign ] = useState< Design | null >( null );
	const [ presets, setPresets ] = useState< Preset[] >( [] );
	const [ font, setFont ] = useState( 'inherit' );
	const [ fonts, setFonts ] = useState< string[] >( [] );
	const [ mascot, setMascot ] = useState< Mascot >( emptyMascot );
	const [ accent, setAccent ] = useState( '' );
	const [ schoolMascot, setSchoolMascot ] = useState< Mascot >( emptyMascot );
	const [ useSchoolMascot, setUseSchoolMascot ] = useState( false );
	const [ owner, setOwner ] = useState( false );
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		void api< Payload >( '/branding' ).then( ( body ) => {
			setOwner( body.owner );
			setDesign( body.preset );
			setPresets( body.presets );
			setFont( body.font );
			setFonts( body.fonts );
			setMascot( { ...emptyMascot(), ...body.mascot } );
			const mine = body.schools.find( ( item ) => item.id === school );
			setAccent( mine?.accent || '' );
			setUseSchoolMascot( Boolean( mine?.mascot ) );
			setSchoolMascot( { ...emptyMascot(), ...( mine?.mascot || {} ) } );
		} );
	}, [ school ] );

	useEffect( () => {
		if ( 'mascot' === focus ) {
			document.getElementById( 'oml-dash-mascot' )?.scrollIntoView();
		}
	}, [ focus, design ] );

	if ( ! design ) {
		return <p>{ __( 'Loading branding…', 'ohmylms' ) }</p>;
	}

	const previewMascot = school > 0 && useSchoolMascot ? schoolMascot : mascot;

	return (
		<div>
			{ owner && (
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						void api( '/branding', {
							method: 'POST',
							body: JSON.stringify( {
								design,
								font,
								mascot,
							} ),
						} )
							.then( () =>
								setMessage(
									__(
										'Design system and mascot saved.',
										'ohmylms'
									)
								)
							)
							.catch( ( failure: Error ) =>
								setMessage( failure.message )
							);
					} }
				>
					<h2>{ __( 'Branding', 'ohmylms' ) }</h2>
					<p className="oml-dash-note">
						{ __(
							'These are the Design system settings. Saving here updates that option. It does not create a second copy.',
							'ohmylms'
						) }
					</p>
					<Field label={ __( 'Preset', 'ohmylms' ) }>
						<select
							value={ design.preset }
							onChange={ ( event ) => {
								const next = presets.find(
									( item ) => item.name === event.target.value
								);
								if ( next ) {
									setDesign( {
										preset: next.name,
										colors: { ...next.colors },
										button_radius: next.button_radius,
										density: next.density,
									} );
								}
							} }
						>
							{ presets.map( ( item ) => (
								<option key={ item.name } value={ item.name }>
									{ item.label }
								</option>
							) ) }
							<option value="custom">
								{ __( 'Custom', 'ohmylms' ) }
							</option>
						</select>
					</Field>
					<div className="oml-dash-colors">
						{ EDITABLE.map( ( key ) => (
							<Field key={ key } label={ key }>
								<input
									type="color"
									value={
										/^#[0-9a-fA-F]{6}$/.test(
											design.colors[ key ] || ''
										)
											? design.colors[ key ]
											: '#000000'
									}
									onChange={ ( event ) =>
										setDesign( {
											...design,
											preset: 'custom',
											colors: {
												...design.colors,
												[ key ]: event.target.value,
											},
										} )
									}
								/>
							</Field>
						) ) }
					</div>
					<Field label={ __( 'Button radius', 'ohmylms' ) }>
						<select
							value={ String( design.button_radius ) }
							onChange={ ( event ) =>
								setDesign( {
									...design,
									preset: 'custom',
									button_radius: Number( event.target.value ),
								} )
							}
						>
							<option value="0">
								{ __( 'Square', 'ohmylms' ) }
							</option>
							<option value="4">
								{ __( 'Slight', 'ohmylms' ) }
							</option>
							<option value="8">
								{ __( 'Rounded', 'ohmylms' ) }
							</option>
							<option value="999">
								{ __( 'Pill', 'ohmylms' ) }
							</option>
						</select>
					</Field>
					<Field label={ __( 'Density', 'ohmylms' ) }>
						<select
							value={ design.density }
							onChange={ ( event ) =>
								setDesign( {
									...design,
									preset: 'custom',
									density: event.target.value,
								} )
							}
						>
							<option value="compact">
								{ __( 'Compact', 'ohmylms' ) }
							</option>
							<option value="comfortable">
								{ __( 'Comfortable', 'ohmylms' ) }
							</option>
							<option value="spacious">
								{ __( 'Spacious', 'ohmylms' ) }
							</option>
						</select>
					</Field>
					<Field label={ __( 'Learner font', 'ohmylms' ) }>
						<select
							value={ font }
							onChange={ ( event ) =>
								setFont( event.target.value )
							}
						>
							{ fonts.map( ( item ) => (
								<option key={ item } value={ item }>
									{ item }
								</option>
							) ) }
						</select>
					</Field>
					<h2 id="oml-dash-mascot">{ __( 'Mascot', 'ohmylms' ) }</h2>
					<div className="oml-dash-actions">
						{ [ 'none', 'owl', 'robot', 'horse', 'star' ].map(
							( kind ) => (
								<button
									key={ kind }
									type="button"
									className={
										mascot.kind === kind
											? 'oml-dash-btn is-primary'
											: 'oml-dash-btn'
									}
									onClick={ () =>
										setMascot( { ...mascot, kind } )
									}
								>
									{ kind }
								</button>
							)
						) }
					</div>
					<Field label={ __( 'Name', 'ohmylms' ) }>
						<input
							value={ mascot.name }
							onChange={ ( event ) =>
								setMascot( {
									...mascot,
									name: event.target.value,
								} )
							}
						/>
					</Field>
					<Field label={ __( 'Greeting', 'ohmylms' ) }>
						<input
							value={ mascot.greeting }
							onChange={ ( event ) =>
								setMascot( {
									...mascot,
									greeting: event.target.value,
								} )
							}
						/>
					</Field>
					{ PLACES.map( ( place ) => (
						<Check
							key={ place }
							label={ place }
							checked={ Boolean( mascot.placements[ place ] ) }
							onChange={ ( checked ) =>
								setMascot( {
									...mascot,
									placements: {
										...mascot.placements,
										[ place ]: checked,
									},
								} )
							}
						/>
					) ) }
					<button type="submit" className="oml-dash-btn is-primary">
						{ __( 'Save branding and mascot', 'ohmylms' ) }
					</button>
				</form>
			) }
			{ school > 0 && (
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						const schoolBody: {
							accent: string;
							mascot?: Mascot;
							reset_mascot?: boolean;
						} = { accent };
						if ( useSchoolMascot ) {
							schoolBody.mascot = schoolMascot;
						} else {
							schoolBody.reset_mascot = true;
						}
						void api( '/branding', {
							method: 'POST',
							body: JSON.stringify( {
								school_id: school,
								school: schoolBody,
							} ),
						} )
							.then( () =>
								setMessage(
									__( 'School branding saved.', 'ohmylms' )
								)
							)
							.catch( ( failure: Error ) =>
								setMessage( failure.message )
							);
					} }
				>
					<h2>{ __( 'This school', 'ohmylms' ) }</h2>
					<Field label={ __( 'Accent', 'ohmylms' ) }>
						<input
							value={ accent }
							onChange={ ( event ) =>
								setAccent( event.target.value )
							}
							placeholder={ design.colors.primary || '' }
						/>
					</Field>
					<Check
						label={ __(
							'Use a mascot for this school',
							'ohmylms'
						) }
						checked={ useSchoolMascot }
						onChange={ setUseSchoolMascot }
					/>
					{ useSchoolMascot && (
						<>
							<Field label={ __( 'School mascot', 'ohmylms' ) }>
								<select
									value={ schoolMascot.kind }
									onChange={ ( event ) =>
										setSchoolMascot( {
											...schoolMascot,
											kind: event.target.value,
										} )
									}
								>
									{ [
										'none',
										'owl',
										'robot',
										'horse',
										'star',
									].map( ( kind ) => (
										<option key={ kind } value={ kind }>
											{ kind }
										</option>
									) ) }
								</select>
							</Field>
							<Field label={ __( 'Name', 'ohmylms' ) }>
								<input
									value={ schoolMascot.name }
									onChange={ ( event ) =>
										setSchoolMascot( {
											...schoolMascot,
											name: event.target.value,
										} )
									}
								/>
							</Field>
							<Field label={ __( 'Greeting', 'ohmylms' ) }>
								<input
									value={ schoolMascot.greeting }
									onChange={ ( event ) =>
										setSchoolMascot( {
											...schoolMascot,
											greeting: event.target.value,
										} )
									}
								/>
							</Field>
						</>
					) }
					<button type="submit" className="oml-dash-btn">
						{ __( 'Save school branding', 'ohmylms' ) }
					</button>
				</form>
			) }
			<Preview
				design={ design }
				font={ font }
				mascot={ previewMascot }
				accent={ accent }
			/>
			{ message && <p role="status">{ message }</p> }
		</div>
	);
}
