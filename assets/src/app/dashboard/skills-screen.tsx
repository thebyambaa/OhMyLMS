import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api } from './api';
import { Disclosure, Field } from './field';

interface Skill {
	id: number;
	name: string;
	mn: string;
	parent: number;
	depth: string;
	uses: number;
	prerequisites: number[];
}

/**
 * Subject, grade and skill tree, translations, prerequisites and coverage.
 */
export function SkillsScreen() {
	const [ skills, setSkills ] = useState< Skill[] >( [] );
	const [ coverage, setCoverage ] = useState< Skill[] >( [] );
	const [ canEdit, setCanEdit ] = useState( false );
	const [ name, setName ] = useState( '' );
	const [ mn, setMn ] = useState( '' );
	const [ parent, setParent ] = useState( 0 );
	const [ message, setMessage ] = useState( '' );
	const [ csv, setCsv ] = useState( 'subject,grade,skill,mn\n' );
	const [ focus, setFocus ] = useState( 0 );
	const [ adding, setAdding ] = useState( false );
	const [ coverOpen, setCoverOpen ] = useState( false );
	const [ importOpen, setImportOpen ] = useState( false );

	function load() {
		void api< { skills: Skill[]; coverage: Skill[]; canEdit: boolean } >(
			'/studio/skills'
		).then( ( body ) => {
			setSkills( body.skills );
			setCoverage( body.coverage );
			setCanEdit( body.canEdit );
		} );
	}
	useEffect( () => {
		load();
	}, [ message ] );

	function indent( depth: string ) {
		if ( depth === 'skill' ) {
			return '2rem';
		}
		if ( depth === 'grade' ) {
			return '1rem';
		}
		return '0';
	}
	const subjects = skills.filter( ( skill ) => skill.depth === 'subject' );
	const grades = skills.filter( ( skill ) => skill.depth === 'grade' );

	return (
		<section>
			<header className="oml-page-bar">
				<h1 className="oml-dash-title">
					{ __( 'Skills', 'ohmylms' ) }
				</h1>
				{ canEdit && (
					<button
						type="button"
						className="oml-dash-btn is-primary"
						onClick={ () => setAdding( ! adding ) }
					>
						{ __( 'Add skill', 'ohmylms' ) }
					</button>
				) }
			</header>
			<ul className="oml-tree">
				{ skills.map( ( skill ) => (
					<li
						key={ skill.id }
						style={ { marginLeft: indent( skill.depth ) } }
					>
						<button
							type="button"
							className="link"
							onClick={ () => setFocus( skill.id ) }
						>
							{ skill.name }
						</button>
						{ skill.mn && (
							<span className="oml-dash-note"> { skill.mn }</span>
						) }
					</li>
				) ) }
			</ul>
			{ adding && canEdit && (
				<form
					className="oml-dash-card"
					onSubmit={ ( event ) => {
						event.preventDefault();
						void api( '/studio/skills', {
							method: 'POST',
							body: JSON.stringify( {
								name,
								mn,
								parent,
								subject:
									subjects.find(
										( item ) => item.id === parent
									)?.name || name,
							} ),
						} )
							.then( () => {
								setName( '' );
								setMn( '' );
								setAdding( false );
								setMessage( __( 'Saved.', 'ohmylms' ) );
							} )
							.catch( ( failure: Error ) =>
								setMessage( failure.message )
							);
					} }
				>
					<Field label={ __( 'Name (English)', 'ohmylms' ) }>
						<input
							value={ name }
							onChange={ ( event ) =>
								setName( event.target.value )
							}
						/>
					</Field>
					<Field label={ __( 'Name (Mongolian)', 'ohmylms' ) }>
						<input
							value={ mn }
							onChange={ ( event ) =>
								setMn( event.target.value )
							}
						/>
					</Field>
					<Field label={ __( 'Parent', 'ohmylms' ) }>
						<select
							value={ parent }
							onChange={ ( event ) =>
								setParent( Number( event.target.value ) )
							}
						>
							<option value={ 0 }>
								{ __( 'Subject', 'ohmylms' ) }
							</option>
							{ subjects.map( ( skill ) => (
								<option key={ skill.id } value={ skill.id }>
									{ skill.name }
								</option>
							) ) }
							{ grades.map( ( skill ) => (
								<option key={ skill.id } value={ skill.id }>
									{ skill.name }
								</option>
							) ) }
						</select>
					</Field>
					<button type="submit" className="oml-dash-btn is-primary">
						{ __( 'Add', 'ohmylms' ) }
					</button>
				</form>
			) }
			{ focus > 0 && canEdit && (
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						const selected = [
							...event.currentTarget.querySelectorAll< HTMLInputElement >(
								'input[type="checkbox"]:checked'
							),
						].map( ( input ) => Number( input.value ) );
						void api( `/studio/skills/${ focus }/prerequisites`, {
							method: 'POST',
							body: JSON.stringify( { ids: selected } ),
						} )
							.then( () =>
								setMessage(
									__( 'Prerequisites saved.', 'ohmylms' )
								)
							)
							.catch( ( failure: Error ) =>
								setMessage( failure.message )
							);
					} }
				>
					<h2>{ __( 'Prerequisites', 'ohmylms' ) }</h2>
					{ skills
						.filter(
							( skill ) =>
								skill.depth === 'skill' && skill.id !== focus
						)
						.map( ( skill ) => (
							<label
								key={ skill.id }
								className="oml-dash-field"
								htmlFor={ `oml-pre-${ skill.id }` }
							>
								<input
									id={ `oml-pre-${ skill.id }` }
									type="checkbox"
									value={ skill.id }
									defaultChecked={ skills
										.find( ( item ) => item.id === focus )
										?.prerequisites.includes( skill.id ) }
								/>{ ' ' }
								{ skill.name }
							</label>
						) ) }
					<button type="submit" className="oml-dash-btn">
						{ __( 'Save prerequisites', 'ohmylms' ) }
					</button>
				</form>
			) }
			<Disclosure
				title={ __( 'Coverage', 'ohmylms' ) }
				open={ coverOpen }
				onToggle={ () => setCoverOpen( ! coverOpen ) }
			>
				<section className="oml-dash-card">
					{ coverage.length ? (
						<ul className="oml-tree">
							{ coverage.map( ( skill ) => (
								<li key={ skill.id }>{ skill.name }</li>
							) ) }
						</ul>
					) : (
						<p className="oml-dash-note">
							{ __(
								'Every skill has an exercise, or there are no skills yet.',
								'ohmylms'
							) }
						</p>
					) }
				</section>
			</Disclosure>
			{ canEdit && (
				<Disclosure
					title={ __( 'Import CSV', 'ohmylms' ) }
					open={ importOpen }
					onToggle={ () => setImportOpen( ! importOpen ) }
				>
					<form
						className="oml-drop"
						onSubmit={ ( event ) => {
							event.preventDefault();
							const form = new FormData();
							form.set( 'csv', csv );
							const file = (
								event.currentTarget.elements.namedItem(
									'file'
								) as HTMLInputElement
							 ).files?.[ 0 ];
							if ( file ) {
								form.set( 'file', file );
							}
							void api< { imported: number } >(
								'/studio/skills/import',
								{ method: 'POST', body: form }
							)
								.then( ( body ) =>
									setMessage( String( body.imported ) )
								)
								.catch( ( failure: Error ) =>
									setMessage( failure.message )
								);
						} }
					>
						<h2>{ __( 'Import CSV', 'ohmylms' ) }</h2>
						<Field label={ __( 'CSV file', 'ohmylms' ) }>
							<input
								name="file"
								type="file"
								accept=".csv,text/csv"
							/>
						</Field>
						<Field label={ __( 'Or paste rows', 'ohmylms' ) }>
							<textarea
								value={ csv }
								rows={ 4 }
								onChange={ ( event ) =>
									setCsv( event.target.value )
								}
							/>
						</Field>
						<button type="submit" className="oml-dash-btn">
							{ __( 'Import', 'ohmylms' ) }
						</button>
					</form>
				</Disclosure>
			) }
			{ message && <p role="status">{ message }</p> }
		</section>
	);
}
