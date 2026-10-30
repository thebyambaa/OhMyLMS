import { __, sprintf } from '@wordpress/i18n';
import { FINISH, SCREENS, stepIndex, type Screen } from './model';
import type { SetupState, StepStatus } from './types';

/**
 * Translated names of the screens.
 * @param screen
 */
export function screenLabel( screen: Screen ): string {
	switch ( screen ) {
		case 'welcome':
			return __( 'Welcome', 'ohmylms' );
		case 'experience':
			return __( 'Experience', 'ohmylms' );
		case 'preferences':
			return __( 'Preferences', 'ohmylms' );
		case 'niche':
			return __( 'Subject area', 'ohmylms' );
		case 'pages':
			return __( 'Starter pages', 'ohmylms' );
		case 'import':
			return __( 'Import courses', 'ohmylms' );
		case FINISH:
			return __( 'Finish', 'ohmylms' );
	}
}

function statusText( status: StepStatus | undefined ): string {
	if ( 'done' === status ) {
		return __( 'done', 'ohmylms' );
	}
	if ( 'skipped' === status ) {
		return __( 'skipped', 'ohmylms' );
	}
	return '';
}

/**
 * Progress through the wizard. Every screen is a button (all steps can be revisited), the current one
 * has `aria-current="step"`, and a polite live region announces "Step N of M: name" when it changes.
 *
 * @param props          Component props.
 * @param props.setup    Wizard state, for the done/skipped markers.
 * @param props.current  Current screen.
 * @param props.onSelect Called with the screen to open.
 * @param props.disabled Disable navigation while a request is pending.
 */
export function Stepper( {
	setup,
	current,
	onSelect,
	disabled,
}: {
	setup: SetupState;
	current: Screen;
	onSelect: ( screen: Screen ) => void;
	disabled: boolean;
} ) {
	return (
		<>
			<p role="status" className="screen-reader-text">
				{ sprintf(
					/* translators: 1: step number, 2: number of steps, 3: step name. */
					__( 'Step %1$d of %2$d: %3$s', 'ohmylms' ),
					stepIndex( current ) + 1,
					SCREENS.length,
					screenLabel( current )
				) }
			</p>
			<nav aria-label={ __( 'Setup progress', 'ohmylms' ) }>
				<ol className="ohmylms-setup-steps">
					{ SCREENS.map( ( screen ) => {
						const info = setup.steps.find(
							( s ) => s.id === screen
						);
						const marker = statusText( info?.status );
						return (
							<li key={ screen }>
								<button
									type="button"
									className="button-link"
									aria-current={
										screen === current ? 'step' : undefined
									}
									disabled={ disabled }
									onClick={ () => onSelect( screen ) }
								>
									<span>{ screenLabel( screen ) }</span>
									{ marker && (
										<span className="screen-reader-text">
											{ ` (${ marker })` }
										</span>
									) }
								</button>
								{ marker && (
									<span aria-hidden="true">{ ' ✓' }</span>
								) }
							</li>
						);
					} ) }
				</ol>
			</nav>
		</>
	);
}
