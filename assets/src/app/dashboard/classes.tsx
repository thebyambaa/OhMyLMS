import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api, boot } from './api';
import { Field } from './field';

/**
 * Three-step class wizard. The school’s current year is used when none is chosen.
 *
 * @param props        Props.
 * @param props.school Selected school.
 */
export function Classes( { school }: { school: number } ) {
	const [ step, setStep ] = useState( 1 );
	const [ name, setName ] = useState( '' );
	const [ grade, setGrade ] = useState( '8' );
	const [ teacher, setTeacher ] = useState( '' );
	const [ emails, setEmails ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ rows, setRows ] = useState<
		Array< { id: number; name: string; cells: string[] } >
	>( [] );
	const canManage = boot().canManage;
	useEffect( () => {
		void api< {
			classes: Array< { id: number; name: string; cells: string[] } >;
		} >( `/classes?school=${ school }` ).then( ( body ) =>
			setRows( body.classes )
		);
	}, [ school, message ] );
	return (
		<section>
			{ canManage && school > 0 && (
				<>
					<h2>{ __( 'Create class', 'ohmylms' ) }</h2>
					<div className="oml-dash-steps" aria-hidden="true">
						<span className={ step >= 1 ? 'is-on' : '' } />
						<span className={ step >= 2 ? 'is-on' : '' } />
						<span className={ step >= 3 ? 'is-on' : '' } />
					</div>
					{ step === 1 && (
						<>
							<Field label={ __( 'Class name', 'ohmylms' ) }>
								<input
									value={ name }
									onChange={ ( event ) =>
										setName( event.target.value )
									}
								/>
							</Field>
							<Field label={ __( 'Grade', 'ohmylms' ) }>
								<input
									value={ grade }
									onChange={ ( event ) =>
										setGrade( event.target.value )
									}
								/>
							</Field>
						</>
					) }
					{ step === 2 && (
						<Field label={ __( 'Teacher email', 'ohmylms' ) }>
							<input
								type="email"
								value={ teacher }
								onChange={ ( event ) =>
									setTeacher( event.target.value )
								}
							/>
						</Field>
					) }
					{ step === 3 && (
						<Field
							label={ __(
								'Student emails, separated by commas',
								'ohmylms'
							) }
						>
							<textarea
								value={ emails }
								onChange={ ( event ) =>
									setEmails( event.target.value )
								}
								rows={ 3 }
							/>
						</Field>
					) }
					<div className="oml-dash-actions">
						<button
							type="button"
							className="oml-dash-btn"
							disabled={ step === 1 }
							onClick={ () => setStep( step - 1 ) }
						>
							{ __( 'Back', 'ohmylms' ) }
						</button>
						<button
							type="button"
							className="oml-dash-btn is-primary"
							onClick={ () => {
								if ( step < 3 ) {
									setStep( step + 1 );
									return;
								}
								void api( '/classes', {
									method: 'POST',
									body: JSON.stringify( {
										school,
										name,
										grade,
										teacher_email: teacher,
										student_emails: emails,
									} ),
								} )
									.then( () => {
										setMessage(
											__(
												'Class created. Invitations were emailed for 14 days and can be resent from People.',
												'ohmylms'
											)
										);
										setStep( 1 );
									} )
									.catch( ( failure: Error ) =>
										setMessage( failure.message )
									);
							} }
						>
							{ step === 3
								? __( 'Create class', 'ohmylms' )
								: __( 'Next', 'ohmylms' ) }
						</button>
					</div>
				</>
			) }
			{ canManage && ! school && (
				<p className="oml-dash-note">
					{ __(
						'Choose a school. The wizard uses that school’s current year, or creates one.',
						'ohmylms'
					) }
				</p>
			) }
			{ message && <p role="status">{ message }</p> }
			<h2>{ __( 'Classes', 'ohmylms' ) }</h2>
			{ rows.length ? (
				<ul>
					{ rows.map( ( row ) => (
						<li key={ row.id }>
							{ row.name } — { row.cells.join( ', ' ) }
						</li>
					) ) }
				</ul>
			) : (
				<p className="oml-dash-note">
					{ __( 'No classes in this view yet.', 'ohmylms' ) }
				</p>
			) }
			{ canManage && school > 0 && (
				<Import school={ school } onDone={ setMessage } />
			) }
		</section>
	);
}

function Import( {
	school,
	onDone,
}: {
	school: number;
	onDone: ( text: string ) => void;
} ) {
	const [ csv, setCsv ] = useState( '' );
	return (
		<form
			onSubmit={ ( event ) => {
				event.preventDefault();
				const form = new FormData();
				form.set( 'school', String( school ) );
				form.set( 'csv', csv );
				const file = (
					event.currentTarget.elements.namedItem(
						'file'
					) as HTMLInputElement
				 ).files?.[ 0 ];
				if ( file ) {
					form.set( 'file', file );
				}
				void api< { sent: number; skipped: string[] } >( '/import', {
					method: 'POST',
					body: form,
				} )
					.then( ( body ) =>
						onDone(
							sprintf(
								/* translators: %d: invitations sent. */
								__(
									'%d invitations sent from the CSV.',
									'ohmylms'
								),
								body.sent
							)
						)
					)
					.catch( ( failure: Error ) => onDone( failure.message ) );
			} }
		>
			<h2>{ __( 'Import students', 'ohmylms' ) }</h2>
			<Field label={ __( 'CSV file', 'ohmylms' ) }>
				<input name="file" type="file" accept=".csv,text/csv" />
			</Field>
			<Field label={ __( 'Or paste rows', 'ohmylms' ) }>
				<textarea
					value={ csv }
					onChange={ ( event ) => setCsv( event.target.value ) }
					rows={ 4 }
					placeholder="email,class"
				/>
			</Field>
			<button type="submit" className="oml-dash-btn">
				{ __( 'Import', 'ohmylms' ) }
			</button>
		</form>
	);
}
