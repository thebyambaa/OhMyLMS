import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { createLearnerPreview } from './learnerPreview';

/**
 * Open lessons in the learner player through signed, short-lived preview links. The tab is opened
 * during the click, so popup blockers allow it, and then pointed at the link; when the browser
 * blocked it anyway the link is offered instead.
 */
export function useLearnerPreview() {
	const operation = useOperation();
	const [ blocked, setBlocked ] = useState( '' );
	function open( lessonId: number ) {
		setBlocked( '' );
		const tab = window.open( '', '_blank' );
		if ( tab ) {
			tab.opener = null;
		}
		void operation.run(
			async ( signal ) => {
				try {
					return await createLearnerPreview( lessonId, signal );
				} catch ( failure ) {
					tab?.close();
					throw failure;
				}
			},
			( preview ) => {
				if ( tab && ! tab.closed ) {
					tab.location.href = preview.url;
				} else {
					setBlocked( preview.url );
				}
			}
		);
	}
	return { open, busy: operation.busy, error: operation.error, blocked };
}

export function LearnerPreviewNotices( {
	error,
	blocked,
}: {
	error: string;
	blocked: string;
} ) {
	return (
		<>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ blocked && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'The browser blocked the preview tab.', 'ohmylms' ) }{ ' ' }
					<a href={ blocked } target="_blank" rel="noreferrer">
						{ __( 'Open the learner preview', 'ohmylms' ) }
					</a>
				</Notice>
			) }
		</>
	);
}

/**
 * "Preview as learner" for one lesson; the preview shows the saved lesson.
 *
 * @param props          Component properties.
 * @param props.lessonId Lesson ID.
 * @param props.dirty    The editor has unsaved changes.
 */
export function LearnerPreviewButton( {
	lessonId,
	dirty,
}: {
	lessonId: number;
	dirty: boolean;
} ) {
	const preview = useLearnerPreview();
	return (
		<>
			<Button
				variant="secondary"
				isBusy={ preview.busy }
				disabled={ preview.busy }
				onClick={ () => preview.open( lessonId ) }
				aria-describedby="ohmylms-learner-preview-help"
			>
				{ __( 'Preview as learner', 'ohmylms' ) }
			</Button>
			<p id="ohmylms-learner-preview-help">
				{ dirty
					? __(
							'Preview shows the last saved version, as an enrolled learner sees it. Nothing is recorded.',
							'ohmylms'
						)
					: __(
							'Opens the lesson as an enrolled learner sees it. Nothing is recorded.',
							'ohmylms'
						) }
			</p>
			<LearnerPreviewNotices
				error={ preview.error }
				blocked={ preview.blocked }
			/>
		</>
	);
}
