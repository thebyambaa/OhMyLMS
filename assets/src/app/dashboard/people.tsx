import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api, boot } from './api';
import { Field } from './field';
import type { Person } from './types';

/**
 * Add students, teachers and parents, and resend or revoke invitations.
 *
 * @param props        Props.
 * @param props.school Selected school.
 * @param props.view   student, teacher, parent or invitations.
 */
export function People( { school, view }: { school: number; view: string } ) {
	const [ role, setRole ] = useState( 'student' );
	const [ rows, setRows ] = useState< Person[] >( [] );
	const [ pending, setPending ] = useState<
		Array< { id: number; email: string; role: string } >
	>( [] );
	const [ email, setEmail ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const canManage = boot().canManage;

	useEffect( () => {
		if ( view === 'student' || view === 'teacher' || view === 'parent' ) {
			setRole( view );
		}
	}, [ view ] );

	useEffect( () => {
		const requested = role === 'parent' ? 'guardian' : role;
		void api< {
			people: Person[];
			pending: Array< { id: number; email: string; role: string } >;
		} >(
			`/people?role=${ requested }&school=${ school }&search=${ encodeURIComponent( search ) }`
		).then( ( body ) => {
			setRows( body.people );
			setPending( body.pending );
		} );
	}, [ role, school, search, message ] );

	return (
		<section>
			{ view !== 'invitations' && (
				<Field label={ __( 'Search', 'ohmylms' ) }>
					<input
						value={ search }
						onChange={ ( event ) =>
							setSearch( event.target.value )
						}
					/>
				</Field>
			) }
			{ canManage && school > 0 && (
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						void api( '/invitations', {
							method: 'POST',
							body: JSON.stringify( { school, role, email } ),
						} )
							.then( () => {
								setMessage(
									__(
										'Invitation emailed. It stays pending until it is accepted, and you can resend it.',
										'ohmylms'
									)
								);
								setEmail( '' );
							} )
							.catch( ( failure: Error ) =>
								setMessage( failure.message )
							);
					} }
				>
					<h2>{ __( 'Add', 'ohmylms' ) }</h2>
					<Field label={ __( 'Email', 'ohmylms' ) }>
						<input
							type="email"
							required
							value={ email }
							onChange={ ( event ) =>
								setEmail( event.target.value )
							}
						/>
					</Field>
					<button type="submit" className="oml-dash-btn is-primary">
						{ __( 'Send invitation', 'ohmylms' ) }
					</button>
				</form>
			) }
			{ canManage && ! school && (
				<p className="oml-dash-note">
					{ __(
						'Choose a school before sending invitations.',
						'ohmylms'
					) }
				</p>
			) }
			{ message && <p role="status">{ message }</p> }
			<h2>{ __( 'Pending invitations', 'ohmylms' ) }</h2>
			{ pending.length ? (
				<ul>
					{ pending.map( ( item ) => (
						<li key={ item.id }>
							{ item.email } ({ item.role }){ ' ' }
							<button
								type="button"
								className="oml-dash-btn"
								onClick={ () =>
									void api(
										`/invitations/${ item.id }/resend?school=${ school }`,
										{ method: 'POST' }
									).then( () =>
										setMessage(
											__(
												'Invitation resent.',
												'ohmylms'
											)
										)
									)
								}
							>
								{ __( 'Resend', 'ohmylms' ) }
							</button>
							<button
								type="button"
								className="oml-dash-btn"
								onClick={ () =>
									void api(
										`/invitations/${ item.id }/revoke?school=${ school }`,
										{ method: 'POST' }
									).then( () =>
										setMessage(
											__(
												'Invitation revoked.',
												'ohmylms'
											)
										)
									)
								}
							>
								{ __( 'Revoke', 'ohmylms' ) }
							</button>
						</li>
					) ) }
				</ul>
			) : (
				<p className="oml-dash-note">
					{ __( 'No pending invitations.', 'ohmylms' ) }
				</p>
			) }
			{ view !== 'invitations' && (
				<table className="oml-dash-table">
					<tbody>
						{ rows.map( ( row ) => (
							<tr key={ row.id }>
								<td>{ row.name }</td>
								<td>{ row.email }</td>
								<td>{ row.school }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</section>
	);
}
