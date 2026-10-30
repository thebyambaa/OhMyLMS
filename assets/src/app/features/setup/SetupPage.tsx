import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useSetup } from './hooks';
import {
	FINISH,
	canComplete,
	isCompleted,
	nextStep,
	pendingSteps,
	previousStep,
	progressOf,
	resolveStep,
	stepInfo,
	type Screen,
} from './model';
import { Stepper, screenLabel } from './Stepper';
import { StepFrame } from './StepFrame';
import {
	ExperienceStep,
	ImportStep,
	NicheStep,
	PagesStep,
	PreferencesStep,
	WelcomeStep,
	type StepProps,
} from './Steps';
import type { SetupState, StepId, StepInput } from './types';

const FORMS: Record< StepId, ( props: StepProps ) => JSX.Element > = {
	welcome: WelcomeStep,
	experience: ExperienceStep,
	preferences: PreferencesStep,
	niche: NicheStep,
	pages: PagesStep,
	import: ImportStep,
};

/**
 * Setup wizard at `#/setup`. The screen is the `step` query parameter (`#/setup?step=preferences`),
 * so a step can be linked and survives a reload. Every step can be revisited and repeated. After
 * completion the page shows a done state; `?step=...` re-opens a step.
 */
export default function SetupPage() {
	const [ params, setParams ] = useSearchParams();
	const navigate = useNavigate();
	const wizard = useSetup();
	const { setup } = wizard;

	if ( wizard.loading && ! setup ) {
		return (
			<p role="status">
				<Spinner /> { __( 'Loading setup…', 'ohmylms' ) }
			</p>
		);
	}
	if ( ! setup ) {
		return (
			<Notice status="error" isDismissible={ false }>
				<p>
					{ wizard.loadError?.message ??
						__( 'The setup could not be loaded.', 'ohmylms' ) }
				</p>
				{ ! wizard.loadError?.permission && (
					<Button variant="secondary" onClick={ wizard.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				) }
			</Notice>
		);
	}

	const requested = params.get( 'step' );
	const go = ( screen: Screen | null ) => {
		wizard.clearError();
		if ( screen ) {
			setParams( { step: screen } );
		}
	};

	if ( isCompleted( setup ) && null === requested ) {
		return (
			<CompletedView
				setup={ setup }
				busy={ wizard.busy }
				error={ wizard.actionError?.message ?? null }
				onRestart={ async () => {
					if ( await wizard.reset() ) {
						setParams( { step: 'welcome' } );
					}
				} }
			/>
		);
	}

	const current = resolveStep( requested, setup );
	const submit = async ( step: StepId, input: StepInput ) => {
		if ( await wizard.save( step, input ) ) {
			go( nextStep( step ) );
		}
	};
	const skip = async ( step: StepId ) => {
		if ( await wizard.skip( step ) ) {
			go( nextStep( step ) );
		}
	};
	const exit = async () => {
		const step = FINISH === current ? null : current;
		if ( await wizard.dismiss( step ) ) {
			navigate( '/' );
		}
	};
	const { resolved, total } = progressOf( setup );

	return (
		<>
			<h2>{ __( 'Set up your LMS', 'ohmylms' ) }</h2>
			<p>
				{ `${ resolved } / ${ total } ` }
				{ __( 'steps finished', 'ohmylms' ) }
				{ 'dismissed' === setup.status &&
					` — ${ __( 'setup was paused; continue where you left off.', 'ohmylms' ) }` }
			</p>
			<Stepper
				setup={ setup }
				current={ current }
				disabled={ wizard.busy }
				onSelect={ go }
			/>
			{ FINISH === current ? (
				<FinishScreen
					setup={ setup }
					busy={ wizard.busy }
					error={ wizard.actionError }
					onBack={ () => go( previousStep( FINISH ) ) }
					onOpen={ go }
					onComplete={ async () => {
						if ( await wizard.complete() ) {
							setParams( {} );
						}
					} }
				/>
			) : (
				<StepSlot
					step={ current }
					setup={ setup }
					busy={ wizard.busy }
					error={ wizard.actionError }
					onSubmit={ ( input ) => submit( current, input ) }
					onSkip={ () => skip( current ) }
					onBack={ () => go( previousStep( current ) ) }
				/>
			) }
			<p>
				<Button
					variant="link"
					onClick={ exit }
					disabled={ wizard.busy }
					accessibleWhenDisabled
				>
					{ __( 'Exit setup', 'ohmylms' ) }
				</Button>
			</p>
		</>
	);
}

function StepSlot( {
	step,
	setup,
	busy,
	error,
	onSubmit,
	onSkip,
	onBack,
}: {
	step: StepId;
	setup: SetupState;
	busy: boolean;
	error: StepProps[ 'error' ];
	onSubmit: StepProps[ 'onSubmit' ];
	onSkip: () => void;
	onBack: () => void;
} ) {
	const Form = FORMS[ step ];
	const info = stepInfo( setup, step );
	return (
		<Form
			// A fresh form (and focus) for each step; values are re-read from the server state.
			key={ `${ step }-${ info?.status ?? 'pending' }` }
			setup={ setup }
			busy={ busy }
			error={ error }
			onSubmit={ onSubmit }
			onSkip={ info?.skippable ? onSkip : undefined }
			onBack={ 'welcome' === step ? undefined : onBack }
		/>
	);
}

function FinishScreen( {
	setup,
	busy,
	error,
	onBack,
	onOpen,
	onComplete,
}: {
	setup: SetupState;
	busy: boolean;
	error: StepProps[ 'error' ];
	onBack: () => void;
	onOpen: ( screen: Screen ) => void;
	onComplete: () => void;
} ) {
	const pending = pendingSteps( setup );
	return (
		<StepFrame
			title={ __( 'Finish setup', 'ohmylms' ) }
			description={
				canComplete( setup )
					? __(
							'Everything is done or skipped. Finish to mark the setup complete.',
							'ohmylms'
						)
					: __(
							'Some steps are still open. Finish or skip them, then complete the setup.',
							'ohmylms'
						)
			}
			busy={ busy }
			error={ error }
			onBack={ onBack }
			onSubmit={ () => canComplete( setup ) && onComplete() }
			submitLabel={ __( 'Finish setup', 'ohmylms' ) }
		>
			{ pending.length > 0 && (
				<ul aria-label={ __( 'Open steps', 'ohmylms' ) }>
					{ pending.map( ( id ) => (
						<li key={ id }>
							<Button
								variant="link"
								onClick={ () => onOpen( id ) }
							>
								{ screenLabel( id ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
		</StepFrame>
	);
}

function CompletedView( {
	setup,
	busy,
	error,
	onRestart,
}: {
	setup: SetupState;
	busy: boolean;
	error: string | null;
	onRestart: () => void;
} ) {
	return (
		<section aria-labelledby="ohmylms-setup-done">
			<h2 id="ohmylms-setup-done">
				{ __( 'Setup complete', 'ohmylms' ) }
			</h2>
			<Notice status="success" isDismissible={ false }>
				{ __( 'Your LMS is set up.', 'ohmylms' ) }
			</Notice>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<p>
				{ __( 'Currency:', 'ohmylms' ) } { setup.preferences.currency }
			</p>
			<p>
				<Link to="/settings">{ __( 'Open settings', 'ohmylms' ) }</Link>
				{ ' · ' }
				<Link to="/">{ __( 'Go to the dashboard', 'ohmylms' ) }</Link>
			</p>
			<p>
				<Button
					variant="secondary"
					onClick={ onRestart }
					disabled={ busy }
					accessibleWhenDisabled
				>
					{ __( 'Run setup again', 'ohmylms' ) }
				</Button>
			</p>
		</section>
	);
}
