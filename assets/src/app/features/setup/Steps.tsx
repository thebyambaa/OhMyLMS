import {
	RadioControl,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { listCurrencies } from './api';
import {
	describeFailure,
	isExperience,
	isNiche,
	parseTopics,
	validateExperience,
	validateNiche,
	validatePreferences,
} from './model';
import { StepFrame } from './StepFrame';
import type {
	CurrencyOption,
	CurrencyPosition,
	FailureView,
	FieldErrors,
	SetupState,
	StepInput,
} from './types';

/** Props shared by every step form. */
export interface StepProps {
	setup: SetupState;
	busy: boolean;
	error: FailureView | null;
	/** Save the step with this input. */
	onSubmit: ( input: StepInput ) => void;
	onBack?: () => void;
	onSkip?: () => void;
}

const empty: Record< string, never > = {};

export function WelcomeStep( { busy, error, onSubmit, onSkip }: StepProps ) {
	return (
		<StepFrame
			title={ __( 'Welcome to OhMyLMS', 'ohmylms' ) }
			description={ __(
				'A few short steps set up your site preferences and the pages students need. You can change everything later in Settings, and you can leave at any time.',
				'ohmylms'
			) }
			busy={ busy }
			error={ error }
			onSubmit={ () => onSubmit( empty ) }
			onSkip={ onSkip }
			submitLabel={ __( 'Start setup', 'ohmylms' ) }
		/>
	);
}

export function ExperienceStep( {
	setup,
	busy,
	error,
	onSubmit,
	onBack,
}: StepProps ) {
	const [ value, setValue ] = useState< string >(
		setup.answers.experience ?? ''
	);
	const [ errors, setErrors ] = useState< FieldErrors >( {} );
	const message = errors.experience ?? error?.fieldErrors.experience;

	return (
		<StepFrame
			title={ __(
				'How familiar are you with running an LMS?',
				'ohmylms'
			) }
			description={ __(
				'This only tailors the tips you see. It does not change any setting.',
				'ohmylms'
			) }
			busy={ busy }
			error={ error }
			onBack={ onBack }
			submitLabel={ __( 'Continue', 'ohmylms' ) }
			onSubmit={ () => {
				const invalid = validateExperience( value );
				setErrors( invalid );
				if (
					isExperience( value ) &&
					! Object.keys( invalid ).length
				) {
					onSubmit( { experience: value } );
				}
			} }
		>
			<RadioControl
				label={ __( 'Your experience', 'ohmylms' ) }
				selected={ value }
				onChange={ ( next ) => {
					setValue( next );
					setErrors( {} );
				} }
				options={ [
					{
						value: 'beginner',
						label: __( 'I am new to this', 'ohmylms' ),
					},
					{
						value: 'intermediate',
						label: __( 'I have some experience', 'ohmylms' ),
					},
					{
						value: 'experienced',
						label: __( 'I have run an LMS before', 'ohmylms' ),
					},
				] }
				help={ message }
			/>
			{ message && <p role="alert">{ message }</p> }
		</StepFrame>
	);
}

export function PreferencesStep( {
	setup,
	busy,
	error,
	onSubmit,
	onBack,
	onSkip,
}: StepProps ) {
	const [ currency, setCurrency ] = useState( setup.preferences.currency );
	const [ position, setPosition ] = useState(
		setup.preferences.currency_pos
	);
	const [ decimals, setDecimals ] = useState(
		String( setup.preferences.price_num_decimals )
	);
	const [ errors, setErrors ] = useState< FieldErrors >( {} );
	const [ options, setOptions ] = useState< CurrencyOption[] | null >( null );
	const [ listError, setListError ] = useState< FailureView | null >( null );

	useEffect( () => {
		const controller = new AbortController();
		listCurrencies( controller.signal ).then(
			( items ) => {
				if ( ! controller.signal.aborted ) {
					setOptions( items );
				}
			},
			( failure: unknown ) => {
				if ( ! controller.signal.aborted ) {
					setListError(
						describeFailure( normalizeApiError( failure ) )
					);
				}
			}
		);
		return () => controller.abort();
	}, [] );

	const all = { ...errors, ...error?.fieldErrors };
	const positionOptions: Array< { value: string; label: string } > = [
		{ value: 'left', label: __( 'Left', 'ohmylms' ) },
		{ value: 'right', label: __( 'Right', 'ohmylms' ) },
		{
			value: 'left_space',
			label: __( 'Left with space', 'ohmylms' ),
		},
		{
			value: 'right_space',
			label: __( 'Right with space', 'ohmylms' ),
		},
	];
	const choices: Array< { value: string; label: string } > = (
		options ?? []
	).map( ( item ) => ( {
		value: item.code,
		label: sprintf(
			/* translators: 1: currency name, 2: currency code, 3: symbol. */
			__( '%1$s (%2$s, %3$s)', 'ohmylms' ),
			item.name,
			item.code,
			item.symbol
		),
	} ) );
	if ( currency && ! choices.some( ( c ) => c.value === currency ) ) {
		choices.unshift( { value: currency, label: currency } );
	}

	return (
		<StepFrame
			title={ __( 'Site preferences', 'ohmylms' ) }
			description={ __(
				'How prices are shown to students. These are the Currency settings.',
				'ohmylms'
			) }
			busy={ busy }
			error={ error }
			onBack={ onBack }
			onSkip={ onSkip }
			submitLabel={ __( 'Save and continue', 'ohmylms' ) }
			onSubmit={ () => {
				const invalid = validatePreferences( {
					currency,
					position,
					decimals,
				} );
				setErrors( invalid );
				if ( ! Object.keys( invalid ).length ) {
					onSubmit( {
						currency,
						// Validated against the allowed positions above.
						currency_pos: position as CurrencyPosition,
						price_num_decimals: Number( decimals ),
					} );
				}
			} }
		>
			{ null === options && ! listError && (
				<p role="status">
					<Spinner /> { __( 'Loading currencies…', 'ohmylms' ) }
				</p>
			) }
			{ listError && <p role="alert">{ listError.message }</p> }
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Currency', 'ohmylms' ) }
				value={ currency }
				options={ choices }
				onChange={ setCurrency }
				help={ all.currency }
				aria-invalid={ all.currency ? true : undefined }
			/>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Currency position', 'ohmylms' ) }
				value={ position }
				options={ positionOptions }
				onChange={ ( next: string ) => setPosition( next ) }
				help={ all.currency_pos }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type="number"
				min={ 0 }
				max={ 8 }
				label={ __( 'Number of decimals', 'ohmylms' ) }
				value={ decimals }
				onChange={ setDecimals }
				help={ all.price_num_decimals }
				aria-invalid={ all.price_num_decimals ? true : undefined }
			/>
		</StepFrame>
	);
}

export function NicheStep( {
	setup,
	busy,
	error,
	onSubmit,
	onBack,
	onSkip,
}: StepProps ) {
	const [ niche, setNiche ] = useState< string >( setup.answers.niche ?? '' );
	const [ topics, setTopics ] = useState( setup.answers.topics.join( '\n' ) );
	const [ errors, setErrors ] = useState< FieldErrors >( {} );
	const all = { ...errors, ...error?.fieldErrors };

	return (
		<StepFrame
			title={ __( 'What will you teach?', 'ohmylms' ) }
			description={ __(
				'Pick the closest subject area. Starter topics become top-level curriculum items; names that already exist are reused and nothing is deleted.',
				'ohmylms'
			) }
			busy={ busy }
			error={ error }
			onBack={ onBack }
			onSkip={ onSkip }
			submitLabel={ __( 'Save and continue', 'ohmylms' ) }
			onSubmit={ () => {
				const names = parseTopics( topics );
				const invalid = validateNiche( niche, names );
				setErrors( invalid );
				if ( isNiche( niche ) && ! Object.keys( invalid ).length ) {
					onSubmit( { niche, topics: names } );
				}
			} }
		>
			<RadioControl
				label={ __( 'Subject area', 'ohmylms' ) }
				selected={ niche }
				onChange={ ( next ) => {
					setNiche( next );
					setErrors( ( current ) => ( { ...current, niche: '' } ) );
				} }
				options={ [
					{
						value: 'digital-marketing-growth',
						label: __( 'Digital marketing and growth', 'ohmylms' ),
					},
					{
						value: 'tech-skills',
						label: __( 'Tech skills', 'ohmylms' ),
					},
					{
						value: 'creator-economy',
						label: __( 'Creator economy', 'ohmylms' ),
					},
					{
						value: 'academic-test-prep',
						label: __( 'Academic and test prep', 'ohmylms' ),
					},
					{
						value: 'other',
						label: __( 'Something else', 'ohmylms' ),
					},
				] }
			/>
			{ all.niche && <p role="alert">{ all.niche }</p> }
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Starter topics (optional)', 'ohmylms' ) }
				help={
					all.topics ??
					__(
						'One per line, up to 10, at most 60 characters each.',
						'ohmylms'
					)
				}
				value={ topics }
				rows={ 4 }
				onChange={ setTopics }
			/>
		</StepFrame>
	);
}

export function PagesStep( {
	setup,
	busy,
	error,
	onSubmit,
	onBack,
	onSkip,
}: StepProps ) {
	const pending = setup.starterPages.filter( ( p ) => 'ready' !== p.status );
	return (
		<StepFrame
			title={ __( 'Starter pages', 'ohmylms' ) }
			description={ __(
				'Students need pages for the course list, checkout and their account. Existing pages are never changed: a page that is already set up is kept, a published page with the same address is reused, and only missing pages are created.',
				'ohmylms'
			) }
			busy={ busy }
			error={ error }
			onBack={ onBack }
			onSkip={ onSkip }
			submitLabel={
				pending.length
					? __( 'Set up the pages', 'ohmylms' )
					: __( 'Continue', 'ohmylms' )
			}
			onSubmit={ () => onSubmit( empty ) }
		>
			{ 0 === setup.starterPages.length ? (
				<p>
					{ __( 'There are no starter pages to set up.', 'ohmylms' ) }
				</p>
			) : (
				<ul aria-label={ __( 'Starter pages', 'ohmylms' ) }>
					{ setup.starterPages.map( ( page ) => (
						<li key={ page.key }>
							<strong>{ page.title }</strong>
							{ ' — ' }
							{ 'ready' === page.status &&
								__( 'already set up', 'ohmylms' ) }
							{ 'adoptable' === page.status &&
								sprintf(
									/* translators: %s: page address (slug). */
									__(
										'the existing page “%s” will be used',
										'ohmylms'
									),
									page.slug
								) }
							{ 'missing' === page.status &&
								__( 'will be created', 'ohmylms' ) }
						</li>
					) ) }
				</ul>
			) }
		</StepFrame>
	);
}

export function ImportStep( { busy, error, onBack, onSkip }: StepProps ) {
	return (
		<StepFrame
			title={ __( 'Import courses', 'ohmylms' ) }
			description={ __(
				'Moving courses from another LMS plugin and importing SCORM packages are not available in this version yet. Nothing will be imported now; you can continue.',
				'ohmylms'
			) }
			busy={ busy }
			error={ error }
			onBack={ onBack }
			submitLabel={ __( 'Continue without importing', 'ohmylms' ) }
			onSubmit={ () => onSkip?.() }
		/>
	);
}
