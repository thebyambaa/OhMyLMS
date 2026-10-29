import { createElement } from '@wordpress/element';
import { restUrl } from '../restUrl.mjs';
import * as Controls from '../../shared/controls';

/**
 * Course levels are a read-only view of curriculum placement and saved skill bands.
 * @param {() => Object} readRuntime Course feature runtime.
 * @return {() => Object} Course level summary component.
 */
export function createCourseLevel( readRuntime ) {
	return function CourseLevel() {
		const { b: I18n, f: Router, g: Hooks, l: apiFetch } = readRuntime();
		const { id } = Router.g();
		const [ summary, setSummary ] = Hooks.useState( null );
		const [ error, setError ] = Hooks.useState( '' );
		const [ attempt, setAttempt ] = Hooks.useState( 0 );
		Hooks.useEffect( () => {
			let active = true;
			setSummary( null );
			setError( '' );
			if ( ! Number( id ) ) {
				setSummary( {
					curriculum: [],
					skills: [],
					unplaced_skills: 0,
				} );
				return undefined;
			}
			const path = `/ohmylms/v1/courses/${ Number( id ) }/organization`;
			const root = window.ohmylms_params?.api_url;
			apiFetch()( root ? { url: restUrl( root, path ) } : { path } )
				.then( ( data ) => active && setSummary( data.level_summary ) )
				.catch(
					( cause ) =>
						active &&
						setError(
							cause.message ||
								I18n.__(
									'Could not load course levels.',
									'ohmylms'
								)
						)
				);
			return () => {
				active = false;
			};
		}, [ id, attempt ] );
		return (
			<div>
				<Controls.HeadingWP level={ 4 }>
					{ I18n.__( 'Level', 'ohmylms' ) }
				</Controls.HeadingWP>
				<p>
					{ I18n.__(
						'Determined by the saved curriculum placement and skill levels.',
						'ohmylms'
					) }
				</p>
				{ error && (
					<p role="alert">
						{ error }{ ' ' }
						<button
							type="button"
							onClick={ () => setAttempt( attempt + 1 ) }
						>
							{ I18n.__( 'Try again', 'ohmylms' ) }
						</button>
					</p>
				) }
				{ ! error && summary && (
					<div>
						{ summary.curriculum.length > 0 && (
							<p>
								<strong>
									{ I18n.__( 'Curriculum:', 'ohmylms' ) }
								</strong>{ ' ' }
								{ summary.curriculum.join( ', ' ) }
							</p>
						) }
						{ summary.skills.map( ( range ) => (
							<p key={ range.subject }>
								<strong>{ range.subject }:</strong>{ ' ' }
								{ range.label }
							</p>
						) ) }
						{ ! summary.curriculum.length &&
							! summary.skills.length && (
								<p>
									{ I18n.__(
										'Not yet determined. Assign a curriculum grade or level, or add skills with level bands.',
										'ohmylms'
									) }
								</p>
							) }
						{ summary.unplaced_skills > 0 && (
							<p>
								{ I18n.__(
									'Some skills do not have valid level bands yet.',
									'ohmylms'
								) }
							</p>
						) }
					</div>
				) }
				{ ! error && ! summary && (
					<p>{ I18n.__( 'Loading levels…', 'ohmylms' ) }</p>
				) }
				<a href={ `#/course-edit/${ Number( id ) }/settings/organize` }>
					{ I18n.__( 'Manage curriculum placement', 'ohmylms' ) }
				</a>
			</div>
		);
	};
}
