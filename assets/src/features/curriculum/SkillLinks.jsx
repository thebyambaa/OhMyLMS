/** @jsx createElement */
import {
	createElement,
	Fragment,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { readComparison, writeComparison } from './comparison.mjs';
import { percent, skillSummary, weightPercent } from './aiAnalysis.mjs';

const REMOVE_TYPES = {
	prerequisites: 'prerequisite',
	encompasses: 'encompasses',
};

/**
 * The links of a shared skill: what it needs first, what it includes, and what includes it.
 * Shown only for shared skills; other skills render nothing.
 *
 * @param {Object} props         Component props.
 * @param {number} props.skillId Skill term ID.
 */
export function SkillLinks( { skillId } ) {
	const [ links, setLinks ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		let current = true;
		readComparison( `skill-links/${ skillId }` )
			.then( ( data ) => current && setLinks( data ) )
			.catch( () => current && setLinks( null ) );
		return () => {
			current = false;
		};
	}, [ skillId ] );
	if ( ! links ) {
		return null;
	}
	const total =
		links.prerequisites.length +
		links.encompasses.length +
		links.encompassed_by.length;
	async function remove( list, other ) {
		setBusy( true );
		setError( '' );
		try {
			setLinks(
				await writeComparison( 'skill-links/remove', {
					skill_id: skillId,
					other_id: other.id,
					type: REMOVE_TYPES[ list ],
				} )
			);
		} catch ( cause ) {
			setError( cause.message );
		} finally {
			setBusy( false );
		}
	}
	const row = ( list, other ) => (
		<li key={ `${ list }-${ other.id }` }>
			<strong>{ other.name }</strong>
			<span className="ohmylms-ai-meta">{ skillSummary( other ) }</span>
			{ list === 'encompasses' && (
				<span>
					{ sprintf(
						/* translators: %d: percentage of the included skill that is practised. */
						__( 'covers %d%%', 'ohmylms' ),
						weightPercent( other.weight )
					) }
				</span>
			) }
			{ other.source === 'ai' && (
				<span
					className="ohmylms-link-ai"
					title={ other.note || undefined }
				>
					{ other.confidence === null
						? __( 'AI', 'ohmylms' )
						: sprintf(
								/* translators: %d: AI confidence percentage. */
								__( 'AI %d%%', 'ohmylms' ),
								percent( other.confidence )
							) }
				</span>
			) }
			{ list !== 'encompassed_by' && (
				<Button
					variant="link"
					isDestructive
					disabled={ busy }
					onClick={ () => remove( list, other ) }
				>
					{ __( 'Remove', 'ohmylms' ) }
				</Button>
			) }
		</li>
	);
	const section = ( list, title ) =>
		links[ list ].length > 0 && (
			<Fragment>
				<h4>{ title }</h4>
				<ul>
					{ links[ list ].map( ( other ) => row( list, other ) ) }
				</ul>
			</Fragment>
		);
	return (
		<section className="ohmylms-skill-links">
			<h3>{ __( 'Skill links', 'ohmylms' ) }</h3>
			{ total === 0 && (
				<p className="ohmylms-ext-muted">
					{ __(
						'No links yet. Use “Suggest prerequisites and links” in the AI analysis, or add them through the API.',
						'ohmylms'
					) }
				</p>
			) }
			{ section( 'prerequisites', __( 'Needs first', 'ohmylms' ) ) }
			{ section( 'encompasses', __( 'Includes', 'ohmylms' ) ) }
			{ section( 'encompassed_by', __( 'Included in', 'ohmylms' ) ) }
			{ error && <p role="alert">{ error }</p> }
		</section>
	);
}
