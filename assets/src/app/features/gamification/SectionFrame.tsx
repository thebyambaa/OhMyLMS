import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import type { FailureView } from './types';

/** What the frame needs from the section hook. */
export interface FrameState {
	loading: boolean;
	loaded: boolean;
	saving: boolean;
	dirty: boolean;
	justSaved: boolean;
	loadError: FailureView | null;
	saveError: FailureView | null;
	save: () => Promise< boolean >;
	reset: () => void;
	reload: () => void;
}

// Loading and error states shared by every tab.
export function LoadState( {
	title,
	loading,
	loadError,
	reload,
}: {
	title: string;
	loading: boolean;
	loadError: FailureView | null;
	reload: () => void;
} ) {
	if ( loading ) {
		return (
			<p role="status">
				<Spinner />{ ' ' }
				{ sprintf(
					/* translators: %s: tab name. */
					__( 'Loading %s…', 'ohmylms' ),
					title
				) }
			</p>
		);
	}
	return (
		<Notice status="error" isDismissible={ false }>
			<p>
				{ loadError?.message ??
					__( 'This tab could not be loaded.', 'ohmylms' ) }
			</p>
			{ ! loadError?.permission && (
				<Button variant="secondary" onClick={ reload }>
					{ __( 'Try again', 'ohmylms' ) }
				</Button>
			) }
		</Notice>
	);
}

/**
 * Chrome of a settings tab: loading and error states, the form with Save and Discard buttons
 * and the result of the last save.
 *
 * @param props             Component props.
 * @param props.title       Tab heading.
 * @param props.description Short intro.
 * @param props.state       Load and save state.
 * @param props.children    The fields, shown once values are loaded.
 */
export function SectionFrame( {
	title,
	description,
	state,
	children,
}: {
	title: string;
	description: string;
	state: FrameState;
	children: ReactNode;
} ) {
	if ( state.loading || state.loadError || ! state.loaded ) {
		return (
			<LoadState
				title={ title }
				loading={ state.loading }
				loadError={ state.loadError }
				reload={ state.reload }
			/>
		);
	}
	return (
		<form
			aria-label={ title }
			noValidate
			onSubmit={ ( event ) => {
				event.preventDefault();
				void state.save();
			} }
		>
			<h3>{ title }</h3>
			<p>{ description }</p>
			{ state.saveError && (
				<Notice status="error" isDismissible={ false }>
					{ state.saveError.message }
				</Notice>
			) }
			{ state.justSaved && ! state.dirty && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Settings saved.', 'ohmylms' ) }
				</Notice>
			) }
			{ children }
			<p>
				<Button
					variant="primary"
					type="submit"
					disabled={ ! state.dirty || state.saving }
					isBusy={ state.saving }
					accessibleWhenDisabled
				>
					{ state.saving
						? __( 'Saving…', 'ohmylms' )
						: __( 'Save changes', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					onClick={ state.reset }
					disabled={ ! state.dirty || state.saving }
					accessibleWhenDisabled
				>
					{ __( 'Discard changes', 'ohmylms' ) }
				</Button>
			</p>
		</form>
	);
}
