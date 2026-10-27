import {
	createElement,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { ViewAsModal } from './ViewAsModal';

const api = ( path, options = {} ) =>
	window.wp.apiFetch( { path: `/ohmylms/v1/school/${ path }`, ...options } );

const ROLES = [
	{
		id: 'student',
		title: __( 'Student', 'ohmylms' ),
		detail: __( 'Learning home, courses and progress', 'ohmylms' ),
	},
	{
		id: 'teacher',
		title: __( 'Teacher', 'ohmylms' ),
		detail: __( 'Classes, assignments and gradebook', 'ohmylms' ),
	},
	{
		id: 'parent',
		title: __( 'Parent', 'ohmylms' ),
		detail: __( 'Family view of linked children', 'ohmylms' ),
	},
	{
		id: 'school',
		title: __( 'School admin', 'ohmylms' ),
		detail: __( 'School overview, people and classes', 'ohmylms' ),
	},
];

const sampleLabel = ( sample ) => {
	if ( sample.complete ) {
		return __( 'Sample people are ready.', 'ohmylms' );
	}
	if ( sample.exists ) {
		return __(
			'Sample people are partly created; create again to finish.',
			'ohmylms'
		);
	}
	return __( 'No sample people yet.', 'ohmylms' );
};

const grid = {
	display: 'grid',
	gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
	gap: 16,
	margin: '8px 0 24px',
};
const card = {
	border: '1px solid #dcdcde',
	borderRadius: 8,
	padding: 16,
	background: '#fff',
};

/**
 * Home tab of the Account Hub: one-click role previews, sample people, and the Student Home editor.
 */
export function PreviewHome() {
	const [ defaults, setDefaults ] = useState( {} );
	const [ sample, setSample ] = useState( null );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ picker, setPicker ] = useState( '' );
	const [ removal, setRemoval ] = useState( null );
	const [ showEditor, setShowEditor ] = useState( false );
	const config = window.ohmylmsViewAs || {};

	const load = useCallback( async () => {
		try {
			const entries = await Promise.all(
				ROLES.map( async ( { id } ) => [
					id,
					await api( `view-as/default?role=${ id }` ),
				] )
			);
			setDefaults( Object.fromEntries( entries ) );
			setSample( await api( 'sample-preview' ) );
		} catch ( failure ) {
			setError( failure.message );
		}
	}, [] );
	useEffect( () => {
		load();
	}, [ load ] );

	const preview = async ( role, person ) => {
		setBusy( role );
		setError( '' );
		try {
			const result = await api( 'view-as/start', {
				method: 'POST',
				data: {
					user_id: Number( person.id ),
					role,
					class_id: 0,
					course_id: 0,
				},
			} );
			window.location.assign( result.url );
		} catch ( failure ) {
			setError( failure.message );
			setBusy( '' );
		}
	};
	const run = async ( key, path, data ) => {
		setBusy( key );
		setError( '' );
		try {
			const result = await api( path, { method: 'POST', data } );
			await load();
			return result;
		} catch ( failure ) {
			setError( failure.message );
			return null;
		} finally {
			setBusy( '' );
		}
	};
	const askRemoval = async () => {
		const plan = await run( 'remove', 'sample-preview/remove', {
			confirm: false,
		} );
		if ( plan ) {
			setRemoval( plan );
		}
	};
	const confirmRemoval = async () => {
		const result = await run( 'remove', 'sample-preview/remove', {
			confirm: true,
		} );
		if ( result ) {
			setRemoval( result.removed ? null : result );
		}
	};

	return (
		<div className="ohmylms-preview-home">
			<h2>{ __( 'Preview the learner experience', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'One click opens the hub as that person. Actions are saved as them; use Return to admin in the bar at the bottom of the page to switch back.',
					'ohmylms'
				) }
			</p>
			{ error && <p role="alert">{ error }</p> }
			<div style={ grid }>
				{ ROLES.map( ( role ) => {
					const entry = defaults[ role.id ];
					const person = entry?.person;
					return (
						<div key={ role.id } style={ card }>
							<h3 style={ { marginTop: 0 } }>{ role.title }</h3>
							<p>{ role.detail }</p>
							<p>
								{ entry === undefined &&
									__( 'Loading…', 'ohmylms' ) }
								{ entry !== undefined && person && (
									<strong>{ person.name }</strong>
								) }
								{ entry !== undefined && ! person && (
									<em>
										{ __(
											'No one has this role yet. Create the sample preview people below.',
											'ohmylms'
										) }
									</em>
								) }
							</p>
							<Button
								variant="primary"
								disabled={ ! person || Boolean( busy ) }
								onClick={ () => preview( role.id, person ) }
							>
								{ busy === role.id
									? __( 'Opening…', 'ohmylms' )
									: `${ __( 'Preview as', 'ohmylms' ) } ${ role.title }` }
							</Button>{ ' ' }
							<Button
								variant="link"
								disabled={ Boolean( busy ) }
								onClick={ () => setPicker( role.id ) }
							>
								{ __( 'Change person', 'ohmylms' ) }
							</Button>
						</div>
					);
				} ) }
			</div>
			{ picker && (
				<ViewAsModal
					role={ picker }
					onClose={ () => {
						setPicker( '' );
						load();
					} }
				/>
			) }

			<h2>{ __( 'Sample preview people', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Creates one marked sample school with a class, a teacher, a parent, a student and a school admin so every preview works on an empty site. Nobody can sign in as them; only View as can open them. Removal deletes only records marked as sample.',
					'ohmylms'
				) }
			</p>
			{ sample && <p>{ sampleLabel( sample ) }</p> }
			<p>
				<Button
					variant="secondary"
					disabled={ Boolean( busy ) || sample?.complete }
					onClick={ () => run( 'create', 'sample-preview/create' ) }
				>
					{ busy === 'create'
						? __( 'Creating…', 'ohmylms' )
						: __( 'Create sample preview people', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="secondary"
					isDestructive
					disabled={ Boolean( busy ) || ! sample?.exists }
					onClick={ askRemoval }
				>
					{ __( 'Remove sample preview people', 'ohmylms' ) }
				</Button>
			</p>
			{ removal && (
				<div
					style={ card }
					role="group"
					aria-label={ __( 'Confirm removal', 'ohmylms' ) }
				>
					{ removal.blockers.length ? (
						<>
							<p>
								<strong>
									{ __(
										'Not removed: the sample data looks like it was used.',
										'ohmylms'
									) }
								</strong>
							</p>
							<ul>
								{ removal.blockers.map( ( line ) => (
									<li key={ line }>{ line }</li>
								) ) }
							</ul>
						</>
					) : (
						<>
							<p>
								<strong>
									{ __(
										'This will permanently remove:',
										'ohmylms'
									) }
								</strong>
							</p>
							<ul>
								{ removal.remove.map( ( line ) => (
									<li key={ line }>{ line }</li>
								) ) }
							</ul>
						</>
					) }
					{ removal.skip.length > 0 && (
						<>
							<p>{ __( 'Left untouched:', 'ohmylms' ) }</p>
							<ul>
								{ removal.skip.map( ( line ) => (
									<li key={ line }>{ line }</li>
								) ) }
							</ul>
						</>
					) }
					{ removal.allowed && (
						<Button
							variant="primary"
							isDestructive
							disabled={ Boolean( busy ) }
							onClick={ confirmRemoval }
						>
							{ __( 'Remove these records', 'ohmylms' ) }
						</Button>
					) }{ ' ' }
					<Button
						variant="tertiary"
						onClick={ () => setRemoval( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</div>
			) }

			<h2>{ __( 'Customize the learner home', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Navigation, home cards, branding and wording for the page real learners see.',
					'ohmylms'
				) }
				{ config.hubUrl && (
					<>
						{ ' ' }
						<a
							href={ config.hubUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Open the public page', 'ohmylms' ) }
						</a>
					</>
				) }
			</p>
			{ config.editorUrl && ! showEditor && (
				<Button
					variant="secondary"
					onClick={ () => setShowEditor( true ) }
				>
					{ __( 'Open the editor', 'ohmylms' ) }
				</Button>
			) }
			{ config.editorUrl && showEditor && (
				<iframe
					title={ __( 'Student Home editor', 'ohmylms' ) }
					src={ config.editorUrl }
					style={ {
						width: '100%',
						height: 900,
						border: '1px solid #dcdcde',
						borderRadius: 8,
					} }
				/>
			) }
		</div>
	);
}
