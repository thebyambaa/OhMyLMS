import { Button, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import type { FailureView } from './types';

/** What the frame needs from the settings group hook. */
export interface FrameState {
	loading: boolean;
	saving: boolean;
	dirty: boolean;
	loaded: boolean;
	loadError: FailureView | null;
	saveError: FailureView | null;
	lastSaved: string[] | null;
	save: () => Promise< boolean >;
	reset: () => void;
	reload: () => void;
}

/**
 * Shared chrome of a settings tab: loading and error states, the form with its Save and Discard
 * buttons, and the result of the last save.
 *
 * @param props             Component props.
 * @param props.title       Tab heading.
 * @param props.description Short intro.
 * @param props.state       Load/save state of the group.
 * @param props.children    The fields, rendered once values are loaded.
 * @param props.extra       Extra controls shown beside Save.
 * @param props.savedNote   Extra sentence appended to the success message.
 */
export function FormFrame( {
	title,
	description,
	state,
	children,
	extra,
	savedNote,
}: {
	title: string;
	description: string;
	state: FrameState;
	children: ReactNode;
	extra?: ReactNode;
	savedNote?: string;
} ) {
	if ( state.loading ) {
		return (
			<p role="status">
				<Spinner />{ ' ' }
				{ sprintf(
					/* translators: %s: settings group name. */
					__( 'Loading %s settings�', 'ohmylms' ),
					title
				) }
			</p>
		);
	}
	if ( state.loadError || ! state.loaded ) {
		return (
			<Notice status="error" isDismissible={ false }>
				<p>
					{ state.loadError?.message ??
						__( 'Settings could not be loaded.', 'ohmylms' ) }
				</p>
				{ ! state.loadError?.permission && (
					<Button variant="secondary" onClick={ state.reload }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				) }
			</Notice>
		);
	}

	const saved = null !== state.lastSaved && ! state.dirty && ! state.saving;
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
			{ saved && (
				<Notice status="success" isDismissible={ false }>
					{ state.lastSaved?.length
						? `${ __( 'Settings saved.', 'ohmylms' ) }${
								savedNote ? ` ${ savedNote }` : ''
							}`
						: __( 'Nothing changed.', 'ohmylms' ) }
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
				{ extra }
			</p>
		</form>
	);
}
