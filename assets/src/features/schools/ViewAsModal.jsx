import { createElement, useEffect, useState, useId } from '@wordpress/element';
import { Modal, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
export const canViewAs = () => Boolean( window.ohmylmsViewAs?.enabled );
export function ViewAsModal( {
	role,
	user,
	classId = 0,
	courseId = 0,
	onClose,
} ) {
	const _controlId = useId();
	const [ rows, setRows ] = useState( [] );
	const [ chosen, setChosen ] = useState( user?.id || '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ loading, setLoading ] = useState( ! user );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		if ( user ) {
			return;
		}
		let active = true;
		setLoading( true );
		setChosen( '' );
		setError( '' );
		window.wp
			.apiFetch( {
				path: `/ohmylms/v1/school/view-as/options?${ new URLSearchParams(
					{
						role,
						class_id: classId,
						course_id: courseId,
						search,
						page,
					}
				) }`,
			} )
			.then(
				( data ) => {
					if ( active ) {
						setRows( data );
					}
				},
				( failure ) => {
					if ( active ) {
						setError( failure.message );
					}
				}
			)
			.finally( () => {
				if ( active ) {
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [ role, user, classId, courseId, search, page ] );
	const label = {
		student: __( 'student', 'ohmylms' ),
		teacher: __( 'teacher', 'ohmylms' ),
		parent: __( 'parent', 'ohmylms' ),
		instructor: __( 'instructor', 'ohmylms' ),
		school: __( 'school administrator', 'ohmylms' ),
	}[ role ];
	return (
		<Modal
			title={ `${ __( 'View as', 'ohmylms' ) } ${ label }` }
			onRequestClose={ () => ! busy && onClose() }
		>
			<p>
				{ __(
					'Actions will be saved as this person. Use Return to admin to switch back.',
					'ohmylms'
				) }
			</p>
			{ user ? (
				<p>
					<strong>{ user.name }</strong>
				</p>
			) : (
				<>
					<label htmlFor={ _controlId + '-field-1' }>
						{ __( 'Search people', 'ohmylms' ) }
						<input
							value={ search }
							onChange={ ( event ) => {
								setSearch( event.target.value );
								setPage( 1 );
							} }
							id={ _controlId + '-field-1' }
						/>
					</label>
					<p>
						<label htmlFor={ _controlId + '-field-2' }>
							{ __( 'Choose person', 'ohmylms' ) }
							<select
								value={ chosen }
								disabled={ loading || busy }
								onChange={ ( event ) =>
									setChosen( event.target.value )
								}
								id={ _controlId + '-field-2' }
							>
								<option value="">
									{ loading
										? __( 'Loading…', 'ohmylms' )
										: __( 'Choose…', 'ohmylms' ) }
								</option>
								{ rows.map( ( person ) => (
									<option
										key={ person.id }
										value={ person.id }
									>
										{ person.name } ({ person.email })
									</option>
								) ) }
							</select>
						</label>
					</p>
					{ ! loading && ! rows.length && (
						<p>
							{ __(
								'No eligible people found for this view.',
								'ohmylms'
							) }
						</p>
					) }
					<p>
						<Button
							disabled={ page === 1 || loading || busy }
							onClick={ () => setPage( page - 1 ) }
						>
							{ __( 'Previous', 'ohmylms' ) }
						</Button>{ ' ' }
						{ page }{ ' ' }
						<Button
							disabled={ ! rows.length || loading || busy }
							onClick={ () => setPage( page + 1 ) }
						>
							{ __( 'Next', 'ohmylms' ) }
						</Button>
					</p>
				</>
			) }
			{ error && <p role="alert">{ error }</p> }
			<Button
				variant="primary"
				disabled={ ! chosen || loading || busy }
				onClick={ async () => {
					setBusy( true );
					setError( '' );
					try {
						const result = await window.wp.apiFetch( {
							path: '/ohmylms/v1/school/view-as/start',
							method: 'POST',
							data: {
								user_id: Number( chosen ),
								role,
								class_id: classId,
								course_id: courseId,
							},
						} );
						window.location.assign( result.url );
					} catch ( failure ) {
						setError( failure.message );
						setBusy( false );
					}
				} }
			>
				{ busy
					? __( 'Switching…', 'ohmylms' )
					: __( 'View as this person', 'ohmylms' ) }
			</Button>
		</Modal>
	);
}
