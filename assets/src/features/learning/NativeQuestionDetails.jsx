import { createElement, useEffect, useState } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import {
	Button,
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	listBanks,
	listSkills,
	loadBankQuestion,
	loadVersion,
	loadVersions,
	pinQuestion,
	saveQuestionAttributes,
	SkillMapEditor,
} from '../question-bank/public.mjs';
import { QuestionLivePreview } from '../question-editor/QuestionLivePreview';
import { NativeSaveGuard, markNativeQuizDirty } from './NativeSaveGuard';

/** @typedef {Awaited<ReturnType<typeof loadBankQuestion>>} BankQuestion */

/**
 * Bank fields of a question.
 *
 * @typedef {Object} BankFields
 * @property {number}                           [bank_id]    Bank ID, 0 for the personal bank.
 * @property {''|'easy'|'standard'|'challenge'} [difficulty] Difficulty.
 * @property {string}                           [source]     Source.
 * @property {number|string}                    [family_id]  Question family.
 * @property {boolean}                          [secure]     Exam-only question.
 */

/**
 * Bank metadata, skills, immutable history and placement pins in the native sidebar.
 * @param {Object}                                            root0            Properties.
 * @param {number}                                            root0.id         Bank question ID.
 * @param {number}                                            root0.quizId     Quiz the question is placed in, 0 outside quizzes.
 * @param {boolean}                                           root0.busy       Whether the question draft is saving or unsaved.
 * @param {(fresh: BankQuestion, restored?: boolean) => void} root0.onRefresh  Receives the question after a write.
 * @param {number}                                            [root0.revision] Authoring revision; a change reloads the details.
 */
export function NativeQuestionDetails( {
	id,
	quizId,
	busy,
	onRefresh,
	revision,
} ) {
	const [ detail, setDetail ] = useState(
		/** @type {BankQuestion|null} */ ( null )
	);
	const [ versions, setVersions ] = useState(
		/** @type {{ id: number, version_no?: number }[]} */ ( [] )
	);
	const [ banks, setBanks ] = useState(
		/** @type {{ id: number, name: string }[]} */ ( [] )
	);
	const [ skills, setSkills ] = useState( [] );
	const [ bank, setBank ] = useState( /** @type {BankFields} */ ( {} ) );
	const [ map, setMap ] = useState( {} );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ preview, setPreview ] = useState(
		/** @type {BankQuestion|null} */ ( null )
	);
	const [ version, setVersion ] = useState( '' );
	useEffect( () => {
		if ( busy || dirty ) {
			return undefined;
		}
		let active = true;
		Promise.all( [
			loadBankQuestion( id ),
			loadVersions( id ),
			listBanks(),
			listSkills( '' ),
		] )
			.then( ( [ loaded, history, catalogue, taxonomy ] ) => {
				if ( ! active ) {
					return;
				}
				setDetail( loaded );
				setVersions( Array.isArray( history ) ? history : [] );
				setBank(
					Object.fromEntries(
						[
							'bank_id',
							'difficulty',
							'source',
							'family_id',
							'secure',
						].map( ( key ) => [ key, loaded[ key ] ] )
					)
				);
				setMap( loaded.skill_map || {} );
				setBanks( catalogue.banks || [] );
				setSkills( taxonomy.skills || [] );
			} )
			.catch( ( cause ) => active && setError( cause.message ) );
		return () => {
			active = false;
		};
	}, [ id, revision, busy, dirty ] );
	/** @param {() => Promise<unknown>} work Write to run. */
	async function act( work ) {
		setSaving( true );
		setError( '' );
		try {
			await work();
		} catch ( cause ) {
			setError( /** @type {{ message: string }} */ ( cause ).message );
		} finally {
			setSaving( false );
		}
	}
	/** @param {number} versionId Version to pin, 0 for the latest. */
	async function pin( versionId ) {
		await pinQuestion( quizId, id, versionId );
		markNativeQuizDirty();
	}
	return (
		<InspectorControls>
			<NativeSaveGuard dirty={ dirty } busy={ saving } />
			<PanelBody
				title={ __( 'Question bank and versions', 'ohmylms' ) }
				initialOpen={ false }
			>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ detail && (
					<>
						<p>
							{ __(
								'Question changes save to the shared bank draft. Approved versions and past attempts remain unchanged.',
								'ohmylms'
							) }
						</p>
						<p>
							{ detail.status } · { detail.usage?.quizzes || 0 }{ ' ' }
							{ __( 'quiz references', 'ohmylms' ) }
						</p>
						<fieldset
							disabled={ busy || saving || ! detail.can_edit }
						>
							<SelectControl
								label={ __( 'Bank', 'ohmylms' ) }
								value={ String( bank.bank_id || 0 ) }
								options={ [
									{
										value: '0',
										label: __( 'Personal bank', 'ohmylms' ),
									},
									...banks.map( ( item ) => ( {
										value: String( item.id ),
										label: item.name,
									} ) ),
								] }
								onChange={ ( value ) => {
									setBank( {
										...bank,
										bank_id: Number( value ),
									} );
									setDirty( true );
								} }
							/>
							<SelectControl
								label={ __( 'Difficulty', 'ohmylms' ) }
								value={ bank.difficulty || 'standard' }
								options={ [
									{
										value: 'easy',
										label: __( 'Easy', 'ohmylms' ),
									},
									{
										value: 'standard',
										label: __( 'Standard', 'ohmylms' ),
									},
									{
										value: 'challenge',
										label: __( 'Challenge', 'ohmylms' ),
									},
								] }
								onChange={ ( value ) => {
									setBank( { ...bank, difficulty: value } );
									setDirty( true );
								} }
							/>
							<TextControl
								label={ __( 'Source', 'ohmylms' ) }
								value={ bank.source || '' }
								onChange={ ( value ) => {
									setBank( { ...bank, source: value } );
									setDirty( true );
								} }
							/>
							<TextControl
								label={ __( 'Question family', 'ohmylms' ) }
								value={ bank.family_id || '' }
								onChange={ ( value ) => {
									setBank( { ...bank, family_id: value } );
									setDirty( true );
								} }
							/>
							<ToggleControl
								label={ __(
									'Secure exam question',
									'ohmylms'
								) }
								checked={ Boolean( bank.secure ) }
								onChange={ ( value ) => {
									setBank( { ...bank, secure: value } );
									setDirty( true );
								} }
							/>
							<SkillMapEditor
								parts={
									Array.isArray( detail.settings?.parts )
										? detail.settings.parts.map(
												/** @param {{ id: string }} part Question part. */
												( part ) => part.id
											)
										: [ 'p1' ]
								}
								skills={ skills }
								value={ map }
								onChange={ ( value ) => {
									setMap( value );
									setDirty( true );
								} }
							/>
							<Button
								variant="secondary"
								disabled={ ! dirty }
								onClick={ () =>
									act( async () => {
										const current =
											await loadBankQuestion( id );
										await saveQuestionAttributes( id, {
											bank,
											skills: map,
											base_modified: current.modified,
										} );
										const fresh =
											await loadBankQuestion( id );
										setDetail( fresh );
										onRefresh( fresh );
										setDirty( false );
									} )
								}
							>
								{ __( 'Save bank settings', 'ohmylms' ) }
							</Button>
						</fieldset>
						<SelectControl
							label={ __( 'Version history', 'ohmylms' ) }
							value={ version }
							options={ [
								{
									value: '',
									label: __(
										'Choose a saved version',
										'ohmylms'
									),
								},
								...versions.map( ( item ) => ( {
									value: String( item.id ),
									label: String( item.version_no || item.id ),
								} ) ),
							] }
							onChange={ ( value ) => {
								setVersion( value );
								setPreview( null );
								if ( value ) {
									act( async () =>
										setPreview(
											( ( snapshot ) => ( {
												...snapshot,
												name: snapshot.title,
												description: snapshot.body,
											} ) )(
												await loadVersion( id, value )
											)
										)
									);
								}
							} }
						/>
						{ preview && (
							<QuestionLivePreview
								question={ {
									...preview,
									questions:
										preview.options ||
										preview.questions ||
										[],
								} }
							/>
						) }
						{ preview && detail.can_edit && (
							<Button
								variant="secondary"
								disabled={ busy || saving || dirty }
								onClick={ () =>
									act( async () => {
										const current =
											await loadBankQuestion( id );
										await saveQuestionAttributes( id, {
											name: preview.name,
											description:
												preview.description || '',
											settings: preview.settings,
											questions:
												/** @type {Record<string, unknown>[]} */ (
													preview.options ||
														preview.questions ||
														[]
												).map(
													( {
														id: optionId,
														...option
													} ) => option
												),
											base_modified: current.modified,
										} );
										const fresh =
											await loadBankQuestion( id );
										setDetail( fresh );
										onRefresh( fresh, true );
									} )
								}
							>
								{ __(
									'Restore this version as a draft',
									'ohmylms'
								) }
							</Button>
						) }
						{ quizId > 0 && (
							<>
								<Button
									variant="secondary"
									disabled={
										busy || saving || dirty || ! version
									}
									onClick={ () =>
										act( () => pin( Number( version ) ) )
									}
								>
									{ __( 'Pin this version', 'ohmylms' ) }
								</Button>
								<Button
									variant="tertiary"
									disabled={ busy || saving || dirty }
									onClick={ () => act( () => pin( 0 ) ) }
								>
									{ __( 'Use latest version', 'ohmylms' ) }
								</Button>
							</>
						) }
					</>
				) }
			</PanelBody>
		</InspectorControls>
	);
}
