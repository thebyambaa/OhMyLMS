import { createElement, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, FormFileUpload, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const PATH = '/ohmylms/v1/hub-icons';

/**
 * Choose or upload the icon shown for each kind of activity in the learner hub.
 *
 * @return {Element} Settings section.
 */
export function HubIconSettings() {
	const [ kinds, setKinds ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );

	useEffect( () => {
		let active = true;
		apiFetch( { path: PATH } )
			.then( ( data ) => active && setKinds( data.kinds ) )
			.catch(
				( failure ) =>
					active &&
					setError(
						failure.message ||
							__( 'Could not load the icons.', 'ohmylms' )
					)
			);
		return () => {
			active = false;
		};
	}, [] );

	/**
	 * Save the full set of choices with one kind changed.
	 *
	 * @param {string} key Kind to change.
	 * @param {number} id  Attachment ID, or 0 for the built-in icon.
	 */
	async function change( key, id ) {
		const icons = {};
		kinds.forEach( ( kind ) => {
			icons[ kind.key ] = kind.key === key ? id : kind.id;
		} );
		setBusy( key );
		setError( '' );
		setNotice( '' );
		try {
			const data = await apiFetch( {
				path: PATH,
				method: 'POST',
				data: { icons },
			} );
			setKinds( data.kinds );
			setNotice( __( 'Icon saved.', 'ohmylms' ) );
		} catch ( failure ) {
			setError(
				failure.message || __( 'Could not save the icon.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}

	/**
	 * Upload an image to the media library and use it for a kind.
	 *
	 * @param {Object} kind Kind being changed.
	 * @param {File}   file Chosen image file.
	 */
	async function upload( kind, file ) {
		if ( ! file ) {
			return;
		}
		const body = new window.FormData();
		body.append( 'file', file, file.name );
		setBusy( kind.key );
		setError( '' );
		setNotice( '' );
		try {
			const media = await apiFetch( {
				path: '/wp/v2/media',
				method: 'POST',
				body,
			} );
			await change( kind.key, media.id );
		} catch ( failure ) {
			setBusy( false );
			setError(
				failure.message ||
					__( 'Could not upload the image.', 'ohmylms' )
			);
		}
	}

	/**
	 * One list of slots, each with a preview, upload and reset control.
	 *
	 * @param {Array} rows Slots to show.
	 * @return {Element} The list.
	 */
	function renderRows( rows ) {
		return (
			<ul className="ohmylms-hub-icons-list">
				{ rows.map( ( kind ) => (
					<li key={ kind.key } className="ohmylms-hub-icons-row">
						<span className="ohmylms-hub-icons-preview">
							{ kind.url ? (
								<img src={ kind.url } alt="" />
							) : (
								<span>{ __( 'Default', 'ohmylms' ) }</span>
							) }
						</span>
						<strong>{ kind.label }</strong>
						<FormFileUpload
							accept="image/png,image/jpeg,image/gif,image/webp"
							onChange={ ( event ) =>
								upload( kind, event.target.files[ 0 ] )
							}
							render={ ( { openFileDialog } ) => (
								<Button
									variant="secondary"
									isBusy={ busy === kind.key }
									disabled={ !! busy }
									onClick={ openFileDialog }
								>
									{ kind.url
										? __( 'Upload a new image', 'ohmylms' )
										: __( 'Upload an image', 'ohmylms' ) }
								</Button>
							) }
						/>
						{ kind.url && (
							<Button
								variant="tertiary"
								isDestructive
								disabled={ !! busy }
								onClick={ () => change( kind.key, 0 ) }
							>
								{ __( 'Use default', 'ohmylms' ) }
							</Button>
						) }
					</li>
				) ) }
			</ul>
		);
	}

	return (
		<section className="ohmylms-hub-icons">
			<h3>{ __( 'Hub logo', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'The mark at the top of the left menu. One logo covers every user type; add a role logo only to show a different one to learners, teachers, parents or school administrators. Square PNG, JPEG, GIF or WebP images work best.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }
			{ ! kinds && ! error && <Spinner /> }
			{ kinds &&
				renderRows(
					kinds.filter( ( kind ) => 'logo' === kind.group )
				) }
			{ kinds && (
				<>
					<h3>{ __( 'Learner hub icons', 'ohmylms' ) }</h3>
					<p>
						{ __(
							'Upload an image for each kind of activity. Square PNG, JPEG, GIF or WebP images work best. Without one, the built-in icon is used.',
							'ohmylms'
						) }
					</p>
					{ renderRows(
						kinds.filter( ( kind ) => 'logo' !== kind.group )
					) }
				</>
			) }
		</section>
	);
}
