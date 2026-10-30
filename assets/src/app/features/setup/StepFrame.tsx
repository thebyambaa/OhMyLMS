import { Button, Notice } from '@wordpress/components';
import { useEffect, useRef } from '@wordpress/element';
import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import type { FailureView } from './types';

/**
 * Shared chrome of a step: heading (focused when the step opens, so screen readers announce it), the
 * error of the last action, the form and Back / Skip / Continue controls. Submit with Enter works
 * because the controls live in a real form.
 *
 * @param props             Component props.
 * @param props.title       Step heading.
 * @param props.description Short intro.
 * @param props.busy        An action is pending.
 * @param props.error       Failure of the last action.
 * @param props.onSubmit    Continue / save handler.
 * @param props.submitLabel Label of the primary button.
 * @param props.onBack      Back handler; omit on the first step.
 * @param props.onSkip      Skip handler; omit when the step cannot be skipped.
 * @param props.children    The fields.
 */
export function StepFrame( {
	title,
	description,
	busy,
	error,
	onSubmit,
	submitLabel,
	onBack,
	onSkip,
	children,
}: {
	title: string;
	description: string;
	busy: boolean;
	error: FailureView | null;
	onSubmit: () => void;
	submitLabel: string;
	onBack?: () => void;
	onSkip?: () => void;
	children?: ReactNode;
} ) {
	const heading = useRef< HTMLHeadingElement >( null );
	useEffect( () => {
		heading.current?.focus();
	}, [ title ] );

	return (
		<form
			aria-labelledby="ohmylms-setup-heading"
			noValidate
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( ! busy ) {
					onSubmit();
				}
			} }
		>
			<h3 id="ohmylms-setup-heading" ref={ heading } tabIndex={ -1 }>
				{ title }
			</h3>
			<p>{ description }</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error.message }
				</Notice>
			) }
			{ children }
			<p>
				{ onBack && (
					<Button
						variant="tertiary"
						onClick={ onBack }
						disabled={ busy }
						accessibleWhenDisabled
					>
						{ __( 'Back', 'ohmylms' ) }
					</Button>
				) }
				{ onSkip && (
					<Button
						variant="secondary"
						onClick={ onSkip }
						disabled={ busy }
						accessibleWhenDisabled
					>
						{ __( 'Skip this step', 'ohmylms' ) }
					</Button>
				) }{ ' ' }
				<Button
					variant="primary"
					type="submit"
					isBusy={ busy }
					disabled={ busy }
					accessibleWhenDisabled
				>
					{ busy ? __( 'Saving…', 'ohmylms' ) : submitLabel }
				</Button>
			</p>
		</form>
	);
}
