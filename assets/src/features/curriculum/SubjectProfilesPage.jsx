/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { AdminCard, AdminPage } from '../../extensions/AdminPage';
import { readComparison, writeComparison } from './comparison.mjs';
import { clearPlacementOptions } from './usePlacementOptions';
import {
	domainId,
	formFromJson,
	formFromProfile,
	profileFromForm,
	profileJson,
} from './subjectProfiles.mjs';

/**
 * Subject profiles: each subject's domains, ordered levels and the context AI analysis is given.
 * Shared skills select a profile by their subject; subjects without one use the default profile.
 * Route: `#/content-hub/curriculum/subjects`.
 */
export function SubjectProfilesPage() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const [ selected, setSelected ] = useState( '' );
	const [ form, setForm ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ transfer, setTransfer ] = useState( null );
	useEffect( () => {
		let current = true;
		readComparison( 'subject-profiles' )
			.then( ( payload ) => current && setData( payload ) )
			.catch( ( cause ) => current && setError( cause.message ) );
		return () => {
			current = false;
		};
	}, [] );
	function open( profile ) {
		setSelected( profile?.key || '' );
		setForm( formFromProfile( profile ) );
		setNotice( '' );
		setError( '' );
	}
	async function write( route, body, message ) {
		setBusy( true );
		setError( '' );
		setNotice( '' );
		try {
			const payload = await writeComparison( route, {
				...body,
				expected_revision: data.revision,
			} );
			clearPlacementOptions();
			setData( payload );
			setNotice( message );
			return payload;
		} catch ( cause ) {
			setError( cause.message );
			return null;
		} finally {
			setBusy( false );
		}
	}
	async function save() {
		const profile = profileFromForm( form );
		const payload = await write(
			'subject-profiles/save',
			profile,
			__( 'Profile saved.', 'ohmylms' )
		);
		if ( payload ) {
			const saved =
				payload.profiles.find( ( row ) => row.key === form.key ) ||
				payload.profiles.find( ( row ) => row.label === profile.label );
			if ( saved ) {
				open( saved );
				setNotice( __( 'Profile saved.', 'ohmylms' ) );
			}
		}
	}
	async function remove( profile ) {
		const payload = await write(
			'subject-profiles/remove',
			{ key: profile.key },
			profile.builtin
				? __( 'Built-in values restored.', 'ohmylms' )
				: __( 'Profile removed.', 'ohmylms' )
		);
		if ( payload ) {
			const restored = payload.profiles.find(
				( row ) => row.key === profile.key
			);
			if ( restored ) {
				open( restored );
			} else {
				setSelected( '' );
				setForm( null );
			}
		}
	}
	const current = data?.profiles.find( ( row ) => row.key === selected );
	return (
		<AdminPage
			className="ohmylms-subject-profiles-page"
			title={ __( 'Subject profiles', 'ohmylms' ) }
			description={ __(
				'Create a subject here to make it available in the Library and in course Settings → Organize → Subject. Domains, levels and AI guidance describe how its skills are organised. Generic is the fallback skill profile.',
				'ohmylms'
			) }
			actions={
				<Button variant="secondary" href="#/content-hub/curriculum">
					{ __( 'Back to curriculum', 'ohmylms' ) }
				</Button>
			}
		>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }
			{ ! data && ! error && <Spinner /> }
			{ data && (
				<div className="ohmylms-subject-profiles">
					<div className="ohmylms-subject-profiles-side">
						<AdminCard title={ __( 'Profiles', 'ohmylms' ) }>
							<ul className="ohmylms-subject-profile-list">
								{ data.profiles.map( ( profile ) => (
									<li key={ profile.key }>
										<Button
											className="ohmylms-subject-profile-item"
											aria-current={
												selected === profile.key
													? 'true'
													: undefined
											}
											onClick={ () => open( profile ) }
										>
											<strong>{ profile.label }</strong>
											<span>
												{ [
													sprintf(
														/* translators: %d: number of domains. */
														_n(
															'%d domain',
															'%d domains',
															profile.domains
																.length,
															'ohmylms'
														),
														profile.domains.length
													),
													sprintf(
														/* translators: %d: number of levels. */
														_n(
															'%d level',
															'%d levels',
															profile.levels
																.length,
															'ohmylms'
														),
														profile.levels.length
													),
												].join( ' · ' ) }
											</span>
										</Button>
										<span className="ohmylms-subject-profile-badges">
											{ data.default === profile.key && (
												<span className="ohmylms-course-badge">
													{ __(
														'Default',
														'ohmylms'
													) }
												</span>
											) }
											{ profile.builtin && (
												<span className="ohmylms-course-badge is-muted">
													{ profile.customized
														? __(
																'Built-in, customised',
																'ohmylms'
															)
														: __(
																'Built-in',
																'ohmylms'
															) }
												</span>
											) }
										</span>
									</li>
								) ) }
							</ul>
							<Button
								variant="secondary"
								onClick={ () => open( null ) }
							>
								{ __( 'Add subject', 'ohmylms' ) }
							</Button>
						</AdminCard>
						<AdminCard title={ __( 'Default profile', 'ohmylms' ) }>
							<SelectControl
								label={ __(
									'Profile for subjects without their own',
									'ohmylms'
								) }
								value={ data.default }
								disabled={ busy }
								options={ data.profiles.map( ( profile ) => ( {
									value: profile.key,
									label: profile.label,
								} ) ) }
								onChange={ ( key ) =>
									write(
										'subject-profiles/default',
										{ key },
										__(
											'Default profile saved.',
											'ohmylms'
										)
									)
								}
								__nextHasNoMarginBottom
							/>
						</AdminCard>
						<AdminCard title={ __( 'Subjects in use', 'ohmylms' ) }>
							{ data.subjects.length ? (
								<table className="widefat striped">
									<thead>
										<tr>
											<th scope="col">
												{ __( 'Subject', 'ohmylms' ) }
											</th>
											<th scope="col">
												{ __(
													'Shared skills',
													'ohmylms'
												) }
											</th>
											<th scope="col">
												{ __( 'Profile', 'ohmylms' ) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ data.subjects.map( ( row ) => (
											<tr key={ row.subject }>
												<td>{ row.subject }</td>
												<td>{ row.skills }</td>
												<td>
													{ data.profiles.find(
														( profile ) =>
															profile.key ===
															row.profile
													)?.label || row.profile }
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							) : (
								<p>
									{ __(
										'No shared skills have a subject yet.',
										'ohmylms'
									) }
								</p>
							) }
						</AdminCard>
					</div>
					<div className="ohmylms-subject-profiles-main">
						{ ! form && (
							<AdminCard>
								<p>
									{ __(
										'Select a profile to edit it, or add a profile for a new subject.',
										'ohmylms'
									) }
								</p>
							</AdminCard>
						) }
						{ form && (
							<ProfileForm
								form={ form }
								setForm={ setForm }
								current={ current }
								limits={ data.limits }
								busy={ busy }
								onSave={ save }
								onRemove={ remove }
								onTransfer={ setTransfer }
							/>
						) }
					</div>
				</div>
			) }
			{ transfer && (
				<Modal
					title={
						'export' === transfer.mode
							? __( 'Export profile', 'ohmylms' )
							: __( 'Import profile', 'ohmylms' )
					}
					onRequestClose={ () => setTransfer( null ) }
				>
					<TextareaControl
						label={ __( 'Profile JSON', 'ohmylms' ) }
						help={
							'export' === transfer.mode
								? __(
										'Copy this to reuse the profile on another site or keep it as a file.',
										'ohmylms'
									)
								: ( form?.key &&
										sprintf(
											/* translators: %s: profile name. */
											__(
												'Paste a profile. It replaces the domains, levels and AI context of %s and keeps its subject name. Nothing is saved until you save the profile.',
												'ohmylms'
											),
											form.label
										) ) ||
									__(
										'Paste a profile. It fills the new profile, including its subject name. Nothing is saved until you save the profile.',
										'ohmylms'
									)
						}
						rows={ 14 }
						value={ transfer.text }
						readOnly={ 'export' === transfer.mode }
						onChange={ ( text ) =>
							setTransfer( { ...transfer, text, invalid: false } )
						}
					/>
					{ transfer.invalid && (
						<Notice status="error" isDismissible={ false }>
							{ __(
								'This is not a profile: it needs a label and a list of levels.',
								'ohmylms'
							) }
						</Notice>
					) }
					{ 'import' === transfer.mode && (
						<Button
							variant="primary"
							onClick={ () => {
								const imported = formFromJson(
									transfer.text,
									form || {}
								);
								if ( ! imported ) {
									setTransfer( {
										...transfer,
										invalid: true,
									} );
									return;
								}
								setForm( imported );
								setTransfer( null );
							} }
						>
							{ __( 'Fill the form', 'ohmylms' ) }
						</Button>
					) }
				</Modal>
			) }
		</AdminPage>
	);
}

/**
 * Editor for one profile.
 *
 * @param {Object}                     props            Component properties.
 * @param {Object}                     props.form       Editor values.
 * @param {(form: Object) => void}     props.setForm    Change the editor values.
 * @param {?Object}                    props.current    The saved profile, or null for a new one.
 * @param {Object}                     props.limits     Most domains, levels and AI text length.
 * @param {boolean}                    props.busy       A save is running.
 * @param {() => void}                 props.onSave     Save the profile.
 * @param {(profile: Object) => void}  props.onRemove   Remove a saved profile or its changes.
 * @param {(transfer: Object) => void} props.onTransfer Open the import or export dialog.
 */
function ProfileForm( {
	form,
	setForm,
	current,
	limits,
	busy,
	onSave,
	onRemove,
	onTransfer,
} ) {
	const [ confirming, setConfirming ] = useState( false );
	const set = ( patch ) => setForm( { ...form, ...patch } );
	const setDomain = ( index, patch ) =>
		set( {
			domains: form.domains.map( ( domain, row ) =>
				row === index ? { ...domain, ...patch } : domain
			),
		} );
	const levelCount = form.levels
		.split( /\r?\n/ )
		.filter( ( level ) => level.trim() ).length;
	return (
		<AdminCard
			title={
				current
					? sprintf(
							/* translators: %s: profile name. */
							__( 'Edit %s', 'ohmylms' ),
							current.label
						)
					: __( 'New profile', 'ohmylms' )
			}
			className="ohmylms-subject-profile-form"
		>
			<TextControl
				label={ __( 'Subject name', 'ohmylms' ) }
				help={
					current
						? sprintf(
								/* translators: %s: profile key. */
								__(
									'Key: %s. Shared skills whose subject has this key use this profile.',
									'ohmylms'
								),
								current.key
							)
						: __(
								'Use the subject name your shared skills carry, for example Mathematics or English language.',
								'ohmylms'
							)
				}
				value={ form.label }
				maxLength={ 100 }
				onChange={ ( label ) => set( { label } ) }
			/>
			<TextControl
				label={ __( 'Language code', 'ohmylms' ) }
				help={ __(
					'Optional, for example en or mn: the language its skills are written in.',
					'ohmylms'
				) }
				value={ form.language }
				maxLength={ 12 }
				onChange={ ( language ) => set( { language } ) }
			/>
			<fieldset className="ohmylms-subject-profile-domains">
				<legend>
					{ sprintf(
						/* translators: 1: number of domains, 2: most domains. */
						__( 'Domains (%1$d of at most %2$d)', 'ohmylms' ),
						form.domains.length,
						limits.domains
					) }
				</legend>
				<p className="description">
					{ __(
						'The areas a subject is divided into, such as Algebra or Reading. Ids are stored on skills: keep an id once skills use it.',
						'ohmylms'
					) }
				</p>
				{ form.domains.map( ( domain, index ) => (
					<div
						className="ohmylms-subject-profile-domain"
						key={ index }
					>
						<TextControl
							label={ __( 'Name', 'ohmylms' ) }
							value={ domain.label }
							maxLength={ 100 }
							onChange={ ( label ) =>
								setDomain( index, { label } )
							}
						/>
						<TextControl
							label={ __( 'Id', 'ohmylms' ) }
							value={ domain.id }
							placeholder={ domainId( domain.label ) }
							maxLength={ 40 }
							onChange={ ( id ) => setDomain( index, { id } ) }
						/>
						<Button
							isDestructive
							variant="tertiary"
							label={ sprintf(
								/* translators: %s: domain name. */
								__( 'Remove domain %s', 'ohmylms' ),
								domain.label || domain.id
							) }
							onClick={ () =>
								set( {
									domains: form.domains.filter(
										( row, position ) => position !== index
									),
								} )
							}
						>
							{ __( 'Remove', 'ohmylms' ) }
						</Button>
					</div>
				) ) }
				<Button
					variant="secondary"
					disabled={ form.domains.length >= limits.domains }
					onClick={ () =>
						set( {
							domains: [ ...form.domains, { id: '', label: '' } ],
						} )
					}
				>
					{ __( 'Add domain', 'ohmylms' ) }
				</Button>
			</fieldset>
			<TextareaControl
				label={ sprintf(
					/* translators: 1: number of levels, 2: most levels. */
					__(
						'Levels, lowest first (%1$d of at most %2$d)',
						'ohmylms'
					),
					levelCount,
					limits.levels
				) }
				help={ __(
					'One level per line, for example Grade 1 … Grade 12, or A1 … C2. Skills store a level by its position, so add new levels at the end or keep positions when renaming.',
					'ohmylms'
				) }
				rows={ 8 }
				value={ form.levels }
				onChange={ ( levels ) => set( { levels } ) }
			/>
			<fieldset className="ohmylms-subject-profile-ai">
				<legend>{ __( 'AI context', 'ohmylms' ) }</legend>
				<TextControl
					label={ __( 'Analyst role', 'ohmylms' ) }
					help={ __(
						'Who the AI acts as, for example "English language curriculum analyst".',
						'ohmylms'
					) }
					value={ form.role }
					maxLength={ limits.text }
					onChange={ ( role ) => set( { role } ) }
				/>
				<TextareaControl
					label={ __( 'Subject context', 'ohmylms' ) }
					value={ form.context }
					maxLength={ limits.text }
					onChange={ ( context ) => set( { context } ) }
				/>
				<TextareaControl
					label={ __( 'Level hints', 'ohmylms' ) }
					help={ __(
						'How syllabuses and exams relate to the levels, for example "IGCSE is grades 9–10".',
						'ohmylms'
					) }
					value={ form.level_hints }
					maxLength={ limits.text }
					onChange={ ( levelHints ) =>
						set( { level_hints: levelHints } )
					}
				/>
			</fieldset>
			<div className="ohmylms-subject-profile-actions">
				<Button
					variant="primary"
					disabled={ busy || ! form.label.trim() || ! levelCount }
					isBusy={ busy }
					onClick={ onSave }
				>
					{ __( 'Save profile', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () =>
						onTransfer( {
							mode: 'export',
							text: profileJson( form ),
						} )
					}
				>
					{ __( 'Export', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => onTransfer( { mode: 'import', text: '' } ) }
				>
					{ __( 'Import', 'ohmylms' ) }
				</Button>
				{ current?.customized && ! confirming && (
					<Button
						isDestructive
						variant="tertiary"
						disabled={ busy }
						onClick={ () => setConfirming( true ) }
					>
						{ current.builtin
							? __( 'Restore built-in values', 'ohmylms' )
							: __( 'Delete profile', 'ohmylms' ) }
					</Button>
				) }
				{ current?.customized && confirming && (
					<span className="ohmylms-subject-profile-confirm">
						{ current.builtin
							? __(
									'Discard your changes to this built-in profile?',
									'ohmylms'
								)
							: __(
									'Delete this profile? Skills keep their stored domains and levels.',
									'ohmylms'
								) }{ ' ' }
						<Button
							isDestructive
							variant="secondary"
							disabled={ busy }
							onClick={ () => {
								setConfirming( false );
								onRemove( current );
							} }
						>
							{ __( 'Confirm', 'ohmylms' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setConfirming( false ) }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</span>
				) }
			</div>
		</AdminCard>
	);
}
