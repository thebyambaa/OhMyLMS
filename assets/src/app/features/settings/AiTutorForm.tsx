import { Button } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import {
	AI_LIMITS,
	aiDirty,
	aiDraft,
	getAi,
	listAiModels,
	saveAi,
	testAi,
	validateAi,
	type AiDraft,
	type AiSnapshot,
} from './aiTutor';
import {
	NumberField,
	SecretField,
	SelectField,
	TextField,
	ToggleField,
} from './fields';
import { FormFrame } from './FormFrame';
import { describeFailure } from './model';
import { useResource } from './useResource';

/**
 * AI tutor tab: the shared provider connection and the tutor and exam converter policies (`/ai`).
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function AiTutorForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< AiSnapshot, AiDraft >(
		{
			load: getAi,
			toDraft: aiDraft,
			isDirty: aiDirty,
			validate: validateAi,
			save: async ( _stored, draft, signal ) => ( {
				snapshot: await saveAi( draft, signal ),
				changed: [ 'ai' ],
			} ),
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );

	const [ busy, setBusy ] = useState< 'models' | 'test' | null >( null );
	const [ models, setModels ] = useState< string[] >( [] );
	const [ outcome, setOutcome ] = useState< {
		ok: boolean;
		message: string;
	} | null >( null );
	const mounted = useRef( true );
	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	async function run( action: 'models' | 'test' ) {
		setBusy( action );
		setOutcome( null );
		try {
			if ( 'models' === action ) {
				const result = await listAiModels();
				if ( mounted.current ) {
					setModels( result.models );
					setOutcome( {
						ok: true,
						message: sprintf(
							/* translators: %d: number of models. */
							__( '%d models available.', 'ohmylms' ),
							result.models.length
						),
					} );
				}
			} else {
				const result = await testAi();
				if ( mounted.current ) {
					setOutcome( result );
				}
			}
		} catch ( failure ) {
			if ( mounted.current ) {
				setOutcome( {
					ok: false,
					message: describeFailure( normalizeApiError( failure ) )
						.message,
				} );
			}
		} finally {
			if ( mounted.current ) {
				setBusy( null );
			}
		}
	}

	const stored = state.snapshot;
	const draft = state.draft;
	const set = ( change: Partial< AiDraft >, field: string ) =>
		state.update( ( current ) => ( { ...current, ...change } ), field );
	const keyFixed =
		stored &&
		( 'constant' === stored.keyFrom || 'environment' === stored.keyFrom );
	const connectionSaved =
		stored && draft
			? stored.hasKey &&
				! state.dirty &&
				stored.settings.provider === draft.provider
			: false;
	const modelOptions =
		draft && models.length
			? [
					...( models.includes( draft.model ) || '' === draft.model
						? []
						: [ draft.model ] ),
					...models,
				]
			: [];

	return (
		<FormFrame
			title={ __( 'AI tutor', 'ohmylms' ) }
			description={ __(
				'Connect an AI provider for practice hints, explanations and the exam converter. AI never grades. The API key is never shown again; leave it empty to keep it.',
				'ohmylms'
			) }
			state={ state }
			extra={
				<>
					{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => void run( 'models' ) }
						isBusy={ 'models' === busy }
						disabled={ null !== busy || ! connectionSaved }
						accessibleWhenDisabled
					>
						{ __( 'Load models', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => void run( 'test' ) }
						isBusy={ 'test' === busy }
						disabled={ null !== busy || ! connectionSaved }
						accessibleWhenDisabled
					>
						{ __( 'Test connection', 'ohmylms' ) }
					</Button>
					{ outcome && (
						<span role={ outcome.ok ? 'status' : 'alert' }>
							{ ' ' }
							{ outcome.message }
						</span>
					) }
				</>
			}
		>
			{ stored && draft && (
				<>
					<SelectField
						label={ __( 'Provider', 'ohmylms' ) }
						value={ draft.provider }
						options={ Object.entries( stored.providers ).map(
							( [ value, label ] ) => ( { value, label } )
						) }
						error={ state.errors.provider }
						onChange={ ( provider ) => {
							setModels( [] );
							set( { provider }, 'provider' );
						} }
					/>
					<SecretField
						label={ __( 'API key', 'ohmylms' ) }
						help={
							keyFixed
								? __(
										'Set by OHMYLMS_AI_API_KEY in wp-config.php or the environment; change it there.',
										'ohmylms'
									)
								: undefined
						}
						value={ draft.key }
						hasValue={ stored.hasKey }
						hint={ stored.keyHint || undefined }
						cleared={ draft.clearKey }
						disabled={ Boolean( keyFixed ) }
						error={ state.errors.key }
						onChange={ ( key ) => set( { key }, 'key' ) }
						onClear={ ( clearKey ) => set( { clearKey }, 'key' ) }
					/>
					{ modelOptions.length > 0 ? (
						<SelectField
							label={ __( 'Model', 'ohmylms' ) }
							value={ draft.model }
							options={ [
								{
									value: '',
									label: __( 'Choose a model', 'ohmylms' ),
								},
								...modelOptions.map( ( model ) => ( {
									value: model,
									label: model,
								} ) ),
							] }
							error={ state.errors.model }
							onChange={ ( model ) => set( { model }, 'model' ) }
						/>
					) : (
						<TextField
							label={ __( 'Model', 'ohmylms' ) }
							help={ __(
								'The provider’s model identifier. Save the provider and key, then use Load models to choose from a list.',
								'ohmylms'
							) }
							value={ draft.model }
							maxLength={ 100 }
							error={ state.errors.model }
							onChange={ ( model ) => set( { model }, 'model' ) }
						/>
					) }
					<ToggleField
						label={ __( 'Practice tutor', 'ohmylms' ) }
						help={ __(
							'Hints and explanations while learners practise.',
							'ohmylms'
						) }
						checked={ draft.enabled }
						onChange={ ( enabled ) =>
							set( { enabled }, 'enabled' )
						}
					/>
					<ToggleField
						label={ __( 'Exam converter', 'ohmylms' ) }
						checked={ draft.exam_converter_enabled }
						onChange={ ( on ) =>
							set(
								{ exam_converter_enabled: on },
								'exam_converter_enabled'
							)
						}
					/>
					<ToggleField
						label={ __( 'Hints', 'ohmylms' ) }
						checked={ draft.hints }
						onChange={ ( hints ) => set( { hints }, 'hints' ) }
					/>
					<ToggleField
						label={ __( 'Explanations', 'ohmylms' ) }
						checked={ draft.explanations }
						onChange={ ( explanations ) =>
							set( { explanations }, 'explanations' )
						}
					/>
					<ToggleField
						label={ __( 'Allow guests', 'ohmylms' ) }
						checked={ draft.guests }
						onChange={ ( guests ) => set( { guests }, 'guests' ) }
					/>
					<SelectField
						label={ __( 'Practice style', 'ohmylms' ) }
						value={ draft.practiceStyle }
						options={ [
							{
								value: 'standard',
								label: __( 'Standard', 'ohmylms' ),
							},
							{
								value: 'lesson',
								label: __( 'Lesson', 'ohmylms' ),
							},
						] }
						onChange={ ( style ) =>
							set(
								{
									practiceStyle:
										'lesson' === style
											? 'lesson'
											: 'standard',
								},
								'practice_style'
							)
						}
					/>
					<NumberField
						label={ __(
							'Requests per learner per day',
							'ohmylms'
						) }
						value={ draft.daily_limit }
						min={ AI_LIMITS.daily_limit.min }
						max={ AI_LIMITS.daily_limit.max }
						step={ 1 }
						error={ state.errors.daily_limit }
						onChange={ ( value ) =>
							set( { daily_limit: value }, 'daily_limit' )
						}
					/>
					<NumberField
						label={ __( 'Maximum reply tokens', 'ohmylms' ) }
						value={ draft.max_tokens }
						min={ AI_LIMITS.max_tokens.min }
						max={ AI_LIMITS.max_tokens.max }
						step={ 1 }
						error={ state.errors.max_tokens }
						onChange={ ( value ) =>
							set( { max_tokens: value }, 'max_tokens' )
						}
					/>
					<NumberField
						label={ __( 'Timeout (seconds)', 'ohmylms' ) }
						value={ draft.timeout }
						min={ AI_LIMITS.timeout.min }
						max={ AI_LIMITS.timeout.max }
						step={ 1 }
						error={ state.errors.timeout }
						onChange={ ( value ) =>
							set( { timeout: value }, 'timeout' )
						}
					/>
					<p>
						{ sprintf(
							/* translators: 1: requests, 2: failures, 3: cached replies. */
							__(
								'Usage: %1$d requests, %2$d failures, %3$d cached.',
								'ohmylms'
							),
							stored.stats.requests,
							stored.stats.failures,
							stored.stats.cached
						) }
						{ stored.stats.lastError &&
							` ${ sprintf(
								/* translators: %s: error message. */
								__( 'Last error: %s', 'ohmylms' ),
								stored.stats.lastError
							) }` }
					</p>
				</>
			) }
		</FormFrame>
	);
}
