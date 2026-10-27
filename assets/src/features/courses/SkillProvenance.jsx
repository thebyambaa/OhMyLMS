/** @jsx createElement */
import { createElement, useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Name of a skill source.
 *
 * @param {string} source Source id.
 * @return {string} Name.
 */
export function sourceLabel( source ) {
	return (
		{
			manual: __( 'Created by a person', 'ohmylms' ),
			syllabus: __( 'Syllabus', 'ohmylms' ),
			import: __( 'Import', 'ohmylms' ),
			ai: __( 'AI analysis', 'ohmylms' ),
			'course-content': __( 'AI from course content', 'ohmylms' ),
		}[ source ] ||
		source ||
		__( 'Created by a person', 'ohmylms' )
	);
}

/**
 * Text of a provenance badge.
 *
 * @param {string} kind Badge kind from provenanceBadge().
 * @return {string} Text.
 */
export function badgeLabel( kind ) {
	return {
		archived: __( 'Archived', 'ohmylms' ),
		rejected: __( 'Rejected', 'ohmylms' ),
		'ai-pending': __( 'AI · pending review', 'ohmylms' ),
		pending: __( 'Pending review', 'ohmylms' ),
	}[ kind ];
}

/**
 * Where a skill came from and whether it is approved, with review and archive controls for administrators.
 * Changes apply to the skill library at once; they are not part of the outline draft.
 *
 * @param {Object}                 props           Component properties.
 * @param {number}                 props.termId    Skill term ID.
 * @param {Object}                 props.meta      source, review, status, model, confidence.
 * @param {boolean}                props.canReview The current user may approve, reject and archive skills.
 * @param {(meta: Object) => void} props.onChange  Apply the changed review state or status.
 */
export function SkillProvenance( { termId, meta = {}, canReview, onChange } ) {
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	async function send( action, data, patch ) {
		setBusy( true );
		setError( '' );
		try {
			await apiFetch( {
				path: `/ohmylms/v1/skills/${ termId }/${ action }`,
				method: 'POST',
				data,
			} );
			onChange( patch );
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			setBusy( false );
		}
	}
	const review = meta.review || 'approved';
	const archived = 'archived' === meta.status;
	return (
		<div className="ohmylms-skill-provenance">
			<h3>{ __( 'Origin and review', 'ohmylms' ) }</h3>
			<p>
				{ [
					sourceLabel( meta.source ),
					meta.model,
					null !== meta.confidence && undefined !== meta.confidence
						? sprintf(
								/* translators: %d: confidence percentage. */
								__( '%d%% confident', 'ohmylms' ),
								Math.round( meta.confidence * 100 )
							)
						: '',
				]
					.filter( Boolean )
					.join( ' · ' ) }
			</p>
			<p>
				{
					{
						approved: __(
							'Approved for the skill library.',
							'ohmylms'
						),
						pending: __(
							'Waiting for library approval. The course can use it in its draft, but cannot be published until it is approved.',
							'ohmylms'
						),
						rejected: __(
							'Rejected. Replace it before publishing the course.',
							'ohmylms'
						),
					}[ review ]
				}
				{ archived &&
					' ' +
						__(
							'Archived: kept for the courses and records that use it, but not offered for new use.',
							'ohmylms'
						) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ canReview && (
				<div className="ohmylms-skill-provenance-actions">
					{ 'approved' !== review && (
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () =>
								send(
									'review',
									{ review: 'approved' },
									{ review: 'approved' }
								)
							}
						>
							{ __( 'Approve skill', 'ohmylms' ) }
						</Button>
					) }
					{ 'rejected' !== review && (
						<Button
							variant="tertiary"
							disabled={ busy }
							onClick={ () =>
								send(
									'review',
									{ review: 'rejected' },
									{ review: 'rejected' }
								)
							}
						>
							{ __( 'Reject skill', 'ohmylms' ) }
						</Button>
					) }
					<Button
						variant="tertiary"
						disabled={ busy }
						onClick={ () =>
							archived
								? send( 'restore', {}, { status: 'active' } )
								: send( 'archive', {}, { status: 'archived' } )
						}
					>
						{ archived
							? __( 'Restore skill', 'ohmylms' )
							: __( 'Archive skill', 'ohmylms' ) }
					</Button>
				</div>
			) }
		</div>
	);
}
