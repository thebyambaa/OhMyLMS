import { createElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';
import { duplicateQuestion, pinQuestion } from './api.mjs';

/**
 * Version context for the selected question in the quiz editor: version number,
 * reuse across quizzes, pinning, and "duplicate to edit" for shared read-only questions.
 * @param root0
 * @param root0.question
 * @param root0.quizId
 * @param root0.onDuplicated
 * @param root0.onPinned
 */
export function QuestionVersionBar( {
	question,
	quizId,
	onDuplicated,
	onPinned,
} ) {
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	if ( ! question || question.temp ) {
		return null;
	}
	async function act( work ) {
		setBusy( true );
		setError( '' );
		try {
			await work();
		} catch ( cause ) {
			setError( cause.message || __( 'The action failed.', 'ohmylms' ) );
		} finally {
			setBusy( false );
		}
	}
	const pinned = Number( question.pinned_version_id ) > 0;
	return (
		<div
			className="ohmylms-version-bar"
			style={ {
				display: 'flex',
				flexWrap: 'wrap',
				gap: 8,
				alignItems: 'center',
				padding: '8px 0',
			} }
		>
			{ question.version > 0 && (
				<span className="ohmylms-badge">
					{ sprintf(
						/* translators: %d: published version number. */ __(
							'Version %d',
							'ohmylms'
						),
						question.version
					) }
				</span>
			) }
			{ question.usage_count > 1 && (
				<span className="ohmylms-badge">
					{ sprintf(
						/* translators: %d: number of quizzes. */ __(
							'Used in %d quizzes',
							'ohmylms'
						),
						question.usage_count
					) }
				</span>
			) }
			{ pinned && (
				<span className="ohmylms-badge">
					{ __( 'Pinned version', 'ohmylms' ) }
				</span>
			) }
			{ question.readonly && (
				<span className="ohmylms-badge ohmylms-badge-warning">
					{ __( 'Shared question — read-only here', 'ohmylms' ) }
				</span>
			) }
			{ question.readonly && (
				<Button
					variant="secondary"
					size="small"
					isBusy={ busy }
					onClick={ () =>
						act( async () =>
							onDuplicated(
								await duplicateQuestion( question.id, quizId ),
								question.id
							)
						)
					}
				>
					{ __( 'Duplicate to edit', 'ohmylms' ) }
				</Button>
			) }
			{ ! question.readonly && question.version > 0 && (
				<Button
					variant="tertiary"
					size="small"
					isBusy={ busy }
					onClick={ () =>
						act( async () => {
							const result = await pinQuestion(
								quizId,
								question.id,
								pinned ? 0 : -1
							);
							onPinned( result.version_id || 0 );
						} )
					}
				>
					{ pinned
						? __( 'Use latest version', 'ohmylms' )
						: __( 'Pin this version', 'ohmylms' ) }
				</Button>
			) }
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
		</div>
	);
}
