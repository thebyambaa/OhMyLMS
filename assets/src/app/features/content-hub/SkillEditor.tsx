import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { normalizeApiError } from '../../lib/api';
import { SkillPicker } from './SkillPicker';
import {
	draftOf,
	LOCALES,
	readSkill,
	rebase,
	saveSkill,
	suggestTranslation,
	updateSkill,
	type Locale,
	type SkillDetail,
	type SkillDraft,
	type TranslationText,
} from './api';

const EMPTY: SkillDraft = {
	name: '',
	code: '',
	description: '',
	parent: 0,
	prerequisites: [],
	objective: '',
	public_practice: false,
	translations: {
		en: { name: '', objective: '', description: '' },
		mn: { name: '', objective: '', description: '' },
	},
};
const LANGUAGE: Record< Locale, () => string > = {
	en: () => __( 'English', 'ohmylms' ),
	mn: () => __( 'Mongolian', 'ohmylms' ),
};

/**
 * Add a skill, or edit one against the revision it was read at.
 *
 * @param props         Editor configuration.
 * @param props.id      Skill to edit, or null to add one.
 * @param props.onClose Close without saving.
 * @param props.onSaved Called after a successful save.
 * @param props.onLinks Open the lesson and course links manager.
 */
export function SkillEditor( {
	id,
	onClose,
	onSaved,
	onLinks,
}: {
	id: number | null;
	onClose: () => void;
	onSaved: () => void;
	onLinks: () => void;
} ) {
	const [ detail, setDetail ] = useState< SkillDetail | null >( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ draft, setDraft ] = useState< SkillDraft >( EMPTY );
	const [ baseline, setBaseline ] = useState< SkillDraft >( EMPTY );
	const [ stale, setStale ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const [ leaving, setLeaving ] = useState( false );
	const [ version, setVersion ] = useState( 0 );
	const operation = useOperation();
	useEffect( () => {
		if ( id === null ) {
			return;
		}
		const controller = new AbortController();
		setLoadError( '' );
		readSkill( id, controller.signal ).then(
			( fresh ) => {
				if ( ! controller.signal.aborted ) {
					setDetail( fresh );
					setDraft( draftOf( fresh ) );
					setBaseline( draftOf( fresh ) );
				}
			},
			( failure ) => {
				if ( ! controller.signal.aborted ) {
					setLoadError( normalizeApiError( failure ).message );
				}
			}
		);
		return () => controller.abort();
	}, [ id, version ] );
	const loading = id !== null && ! detail;
	const busy = operation.busy || loading;
	const dirty = JSON.stringify( draft ) !== JSON.stringify( baseline );
	function change( patch: Partial< SkillDraft > ) {
		setDraft( ( current ) => ( { ...current, ...patch } ) );
	}
	function translate( locale: Locale, patch: Partial< TranslationText > ) {
		setDraft( ( current ) => ( {
			...current,
			translations: {
				...current.translations,
				[ locale ]: { ...current.translations[ locale ], ...patch },
			},
		} ) );
	}
	function save() {
		setNotice( '' );
		void operation.run(
			async ( signal ) => {
				if ( id === null ) {
					await saveSkill( draft, null, signal );
					return null;
				}
				try {
					return await updateSkill(
						id,
						draft,
						baseline,
						detail?.revision ?? '',
						signal
					);
				} catch ( failure ) {
					if (
						normalizeApiError( failure ).code ===
						'ohmylms_skill_stale'
					) {
						setStale( true );
					}
					throw failure;
				}
			},
			() => onSaved()
		);
	}
	function reload() {
		if ( id === null ) {
			return;
		}
		void operation.run(
			( signal ) => readSkill( id, signal ),
			( fresh ) => {
				const next = draftOf( fresh );
				setDraft( rebase( draft, baseline, next ) );
				setBaseline( next );
				setDetail( fresh );
				setStale( false );
				setNotice(
					__(
						'Loaded the latest skill. Your unsaved edits were kept; review them and save again.',
						'ohmylms'
					)
				);
			}
		);
	}
	function suggest( locale: Locale ) {
		if ( id === null || ! detail ) {
			return;
		}
		const other = locale === 'en' ? 'mn' : 'en';
		const translated = draft.translations[ other ];
		const source: TranslationText = translated.name.trim()
			? translated
			: {
					name: draft.name,
					objective:
						draft.objective || detail.skill.effective_objective,
					description: draft.description,
				};
		setNotice( '' );
		void operation.run(
			( signal ) => suggestTranslation( id, locale, source, signal ),
			( result ) => {
				translate( locale, result );
				setNotice(
					__(
						'Review the suggested translation; it is saved only when you save the skill.',
						'ohmylms'
					)
				);
			}
		);
	}
	function close() {
		if ( operation.busy ) {
			return;
		}
		if ( dirty ) {
			setLeaving( true );
		} else {
			onClose();
		}
	}
	return (
		<Modal
			title={
				id === null
					? __( 'Add skill', 'ohmylms' )
					: __( 'Edit skill', 'ohmylms' )
			}
			onRequestClose={ close }
			shouldCloseOnClickOutside={ false }
		>
			{ loadError && (
				<Notice status="error" isDismissible={ false }>
					{ loadError }{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => setVersion( ( value ) => value + 1 ) }
					>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ loading && ! loadError && (
				<p role="status">{ __( 'Loading skill…', 'ohmylms' ) }</p>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
					{ stale && (
						<>
							{ ' ' }
							<Button
								variant="secondary"
								disabled={ operation.busy }
								onClick={ reload }
							>
								{ __(
									'Reload latest and keep my edits',
									'ohmylms'
								) }
							</Button>
						</>
					) }
				</Notice>
			) }
			{ notice && (
				<Notice status="info" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }
			{ leaving && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'Discard unsaved skill edits?', 'ohmylms' ) }{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => setLeaving( false ) }
					>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						isDestructive
						onClick={ onClose }
					>
						{ __( 'Discard edits', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ ( id === null || detail ) && (
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						if ( ! busy && ! stale ) {
							save();
						}
					} }
				>
					<fieldset disabled={ busy }>
						<legend>{ __( 'Details', 'ohmylms' ) }</legend>
						<TextControl
							label={ __( 'Name', 'ohmylms' ) }
							value={ draft.name }
							required
							maxLength={ 200 }
							onChange={ ( name ) => change( { name } ) }
						/>
						<TextControl
							label={ __( 'Code', 'ohmylms' ) }
							value={ draft.code }
							maxLength={ 40 }
							onChange={ ( code ) => change( { code } ) }
						/>
						<TextareaControl
							label={ __( 'Description', 'ohmylms' ) }
							value={ draft.description }
							maxLength={ 10000 }
							onChange={ ( description ) =>
								change( { description } )
							}
						/>
						<SkillPicker
							label={ __( 'Parent', 'ohmylms' ) }
							selected={ draft.parent ? [ draft.parent ] : [] }
							multiple={ false }
							exclude={ id }
							disabled={ busy }
							onChange={ ( ids ) =>
								change( { parent: ids[ 0 ] ?? 0 } )
							}
						/>
						<SkillPicker
							label={ __( 'Prerequisites', 'ohmylms' ) }
							selected={ draft.prerequisites }
							multiple
							exclude={ id }
							disabled={ busy }
							onChange={ ( prerequisites ) =>
								change( { prerequisites } )
							}
						/>
					</fieldset>
					<fieldset disabled={ busy }>
						<legend>
							{ __( 'Learning objective', 'ohmylms' ) }
						</legend>
						<TextareaControl
							label={ __( 'Full learning objective', 'ohmylms' ) }
							value={ draft.objective }
							maxLength={ 2000 }
							help={ __(
								'Leave empty to show learners the description, or the name when there is no description.',
								'ohmylms'
							) }
							onChange={ ( objective ) =>
								change( { objective } )
							}
						/>
					</fieldset>
					{ id !== null && detail && (
						<>
							{ LOCALES.map( ( locale ) => (
								<fieldset key={ locale } disabled={ busy }>
									<legend>
										{ sprintf(
											/* translators: %s: language name. */
											__( '%s translation', 'ohmylms' ),
											LANGUAGE[ locale ]()
										) }
									</legend>
									<TextControl
										label={ sprintf(
											/* translators: %s: language name. */
											__( 'Name (%s)', 'ohmylms' ),
											LANGUAGE[ locale ]()
										) }
										value={
											draft.translations[ locale ].name
										}
										maxLength={ 190 }
										onChange={ ( name ) =>
											translate( locale, { name } )
										}
									/>
									<TextareaControl
										label={ sprintf(
											/* translators: %s: language name. */
											__( 'Objective (%s)', 'ohmylms' ),
											LANGUAGE[ locale ]()
										) }
										value={
											draft.translations[ locale ]
												.objective
										}
										maxLength={ 2000 }
										onChange={ ( objective ) =>
											translate( locale, { objective } )
										}
									/>
									<TextareaControl
										label={ sprintf(
											/* translators: %s: language name. */
											__( 'Notes (%s)', 'ohmylms' ),
											LANGUAGE[ locale ]()
										) }
										value={
											draft.translations[ locale ]
												.description
										}
										maxLength={ 2000 }
										onChange={ ( description ) =>
											translate( locale, { description } )
										}
									/>
									<Button
										variant="secondary"
										disabled={ busy || ! draft.name.trim() }
										onClick={ () => suggest( locale ) }
									>
										{ sprintf(
											/* translators: %s: language name. */
											__(
												'Suggest %s translation',
												'ohmylms'
											),
											LANGUAGE[ locale ]()
										) }
									</Button>
								</fieldset>
							) ) }
							<fieldset disabled={ busy }>
								<legend>{ __( 'Practice', 'ohmylms' ) }</legend>
								<CheckboxControl
									label={ __(
										'Allow public catalogue practice from approved personal and site-bank questions',
										'ohmylms'
									) }
									checked={ draft.public_practice }
									disabled={ ! detail.canPublishPractice }
									help={
										detail.canPublishPractice
											? undefined
											: __(
													'Only an administrator can change public practice.',
													'ohmylms'
												)
									}
									onChange={ ( value ) =>
										change( { public_practice: value } )
									}
								/>
							</fieldset>
							<section
								aria-label={ __( 'Resources', 'ohmylms' ) }
							>
								<h3>{ __( 'Resources', 'ohmylms' ) }</h3>
								<ul>
									<li>
										{ sprintf(
											/* translators: %d: number of lessons. */
											__(
												'Linked lessons: %d',
												'ohmylms'
											),
											detail.resources.lessons
										) }
									</li>
									<li>
										{ sprintf(
											/* translators: %d: number of courses. */
											__(
												'Linked courses: %d',
												'ohmylms'
											),
											detail.resources.courses
										) }
									</li>
									<li>
										{ sprintf(
											/* translators: %d: number of questions. */
											__(
												'Mapped questions: %d',
												'ohmylms'
											),
											detail.resources.questions
										) }
									</li>
								</ul>
								<Button
									variant="secondary"
									disabled={ busy || dirty }
									onClick={ onLinks }
								>
									{ __(
										'Manage lesson and course links',
										'ohmylms'
									) }
								</Button>
								{ dirty && (
									<p>
										{ __(
											'Save or discard your edits before managing links.',
											'ohmylms'
										) }
									</p>
								) }
							</section>
						</>
					) }
					<Button
						variant="primary"
						type="submit"
						disabled={ busy || stale || ! draft.name.trim() }
						isBusy={ operation.busy }
					>
						{ __( 'Save skill', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						onClick={ close }
						disabled={ operation.busy }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</form>
			) }
		</Modal>
	);
}
