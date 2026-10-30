import { Button } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError } from '../../lib/api';
import { clearCache } from './api';
import { asFlag, ToggleField } from './fields';
import { FormFrame } from './FormFrame';
import type { SettingsGroupState } from './hooks';
import { describeFailure } from './model';

/**
 * Advanced tab: video player and clearing the OhMyLMS cache.
 *
 * @param props       Component props.
 * @param props.state State of the `advanced` group.
 */
export function AdvancedForm( {
	state,
}: {
	state: SettingsGroupState< 'advanced' >;
} ) {
	const [ clearing, setClearing ] = useState( false );
	const [ outcome, setOutcome ] = useState< string | null >( null );
	const [ problem, setProblem ] = useState< string | null >( null );
	const mounted = useRef( true );
	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	async function clear() {
		setClearing( true );
		setOutcome( null );
		setProblem( null );
		try {
			const deleted = await clearCache();
			if ( mounted.current ) {
				setOutcome(
					sprintf(
						/* translators: %d: number of cache entries. */
						__( 'Cache cleared (%d entries removed).', 'ohmylms' ),
						deleted
					)
				);
			}
		} catch ( failure ) {
			if ( mounted.current ) {
				setProblem(
					describeFailure( normalizeApiError( failure ) ).message
				);
			}
		} finally {
			if ( mounted.current ) {
				setClearing( false );
			}
		}
	}

	const draft = state.draft;
	return (
		<FormFrame
			title={ __( 'Advanced', 'ohmylms' ) }
			description={ __(
				'Lesson player behaviour and maintenance.',
				'ohmylms'
			) }
			state={ state }
			extra={
				<>
					{ ' ' }
					<Button
						variant="secondary"
						onClick={ () => void clear() }
						isBusy={ clearing }
						disabled={ clearing }
						accessibleWhenDisabled
					>
						{ __( 'Clear OhMyLMS cache', 'ohmylms' ) }
					</Button>
					{ outcome && <span role="status"> { outcome }</span> }
					{ problem && <span role="alert"> { problem }</span> }
				</>
			}
		>
			{ draft && (
				<ToggleField
					label={ __( 'Use the custom video player', 'ohmylms' ) }
					help={ __(
						'Enhanced controls for YouTube, Vimeo and self-hosted videos. When off, the standard platform embed is used.',
						'ohmylms'
					) }
					checked={ asFlag( draft.use_custom_video_player ) }
					error={ state.errors.use_custom_video_player }
					onChange={ ( value ) =>
						state.setField( 'use_custom_video_player', value )
					}
				/>
			) }
		</FormFrame>
	);
}
