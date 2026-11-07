import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api } from './api';
import { Alert } from '../../ui/Alert';
import { Button } from '../../ui/Button';
import { Field } from '../../ui/Field';
import { Input } from '../../ui/Input';
import { Select } from '../../ui/Select';
import { Textarea } from '../../ui/Textarea';
import { ConfirmRemove } from './confirm-remove';
import { Disclosure } from './field';
import { deleteSkill, renameSkill } from './studio-actions';

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
	const [ failed, setFailed ] = useState( false );
	const [ renameValue, setRenameValue ] = useState( '' );
	const [ removing, setRemoving ] = useState( false );
	const [ removeError, setRemoveError ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ csv, setCsv ] = useState( 'subject,grade,skill,mn\n' );
	const [ focus, setFocus ] = useState( 0 );
	const [ adding, setAdding ] = useState( false );
	const [ coverOpen, setCoverOpen ] = useState( false );
	const [ importOpen, setImportOpen ] = useState( false );

	function done( text: string ) {
		setFailed( false );
		setMessage( text );
		setBusy( '' );
	}

	function fail( failure: Error ) {
		setFailed( true );
		setMessage( failure.message );
		setBusy( '' );
	}

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
	const selectedSkill = skills.find( ( skill ) => skill.id === focus );

	function confirmDelete() {
		if ( ! selectedSkill ) {
			return;
		}
		setBusy( 'delete' );
		setRemoveError( '' );
		deleteSkill( selectedSkill.id )
			.then( () => {
				setRemoving( false );
				setFocus( 0 );
				done( __( 'Skill deleted.', 'ohmylms' ) );
			} )
			.catch( ( failure: Error ) => {
				// Keep the dialog open and show why the server refused.
				setRemoveError( failure.message );
				setBusy( '' );
			} );
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
					<Button
						variant="primary"
						aria-expanded={ adding }
						onClick={ () => setAdding( ! adding ) }
					>
						{ __( 'Add skill', 'ohmylms' ) }
					</Button>
				) }
			</header>
			<ul className="oml-tree">
				{ skills.map( ( skill ) => (
					<li
						key={ skill.id }
						style={ { marginLeft: indent( skill.depth ) } }
					>
						<Button
							variant="tertiary"
							size="sm"
							aria-pressed={ focus === skill.id }
							onClick={ () => {
								setFocus( skill.id );
								setRenameValue( skill.name );
							} }
						>
							{ skill.name }
						</Button>
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
						setBusy( 'add' );
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
								done( __( 'Saved.', 'ohmylms' ) );
							} )
							.catch( ( failure: Error ) => fail( failure ) );
					} }
				>
					<Input
						label={ __( 'Name (English)', 'ohmylms' ) }
						required
						value={ name }
						onChange={ ( event ) => setName( event.target.value ) }
					/>
					<Input
						label={ __( 'Name (Mongolian)', 'ohmylms' ) }
						lang="mn"
						value={ mn }
						onChange={ ( event ) => setMn( event.target.value ) }
					/>
					<Select
						label={ __( 'Parent', 'ohmylms' ) }
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
					</Select>
					<Button
						type="submit"
						variant="primary"
						loading={ busy === 'add' }
					>
						{ __( 'Save skill', 'ohmylms' ) }
					</Button>
				</form>
			) }
			{ focus > 0 && canEdit && selectedSkill && (
				<form
					className="oml-dash-card oml-inline-form"
					onSubmit={ ( event ) => {
						event.preventDefault();
						setBusy( 'rename' );
						renameSkill(
							selectedSkill.id,
							renameValue,
							selectedSkill.parent
						)
							.then( () =>
								done( __( 'Skill renamed.', 'ohmylms' ) )
							)
							.catch( ( failure: Error ) => fail( failure ) );
					} }
				>
					<Input
						label={ __( 'Skill name', 'ohmylms' ) }
						required
						value={ renameValue }
						onChange={ ( event ) =>
							setRenameValue( event.target.value )
						}
					/>
					<Button
						type="submit"
						loading={ busy === 'rename' }
						disabled={ renameValue === selectedSkill.name }
					>
						{ __( 'Rename skill', 'ohmylms' ) }
					</Button>
					<Button
						variant="tertiary"
						onClick={ () => {
							setRemoveError( '' );
							setRemoving( true );
						} }
					>
						{ __( 'Delete skill', 'ohmylms' ) }
					</Button>
				</form>
			) }
			{ removing && selectedSkill && (
				<ConfirmRemove
					title={ __( 'Delete this skill?', 'ohmylms' ) }
					confirm={ __( 'Delete skill', 'ohmylms' ) }
					busy={ busy === 'delete' }
					error={ removeError }
					onConfirm={ confirmDelete }
					onCancel={ () => setRemoving( false ) }
				>
					<p>
						{ sprintf(
							/* translators: %s: skill name. */
							__(
								'“%s” will be deleted permanently. Only administrators can delete skills, and this cannot be undone.',
								'ohmylms'
							),
							selectedSkill.name
						) }
					</p>
				</ConfirmRemove>
			) }
			{ focus > 0 && canEdit && (
				<form
					onSubmit={ ( event ) => {
						event.preventDefault();
						setBusy( 'pre' );
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
								done( __( 'Prerequisites saved.', 'ohmylms' ) )
							)
							.catch( ( failure: Error ) => fail( failure ) );
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
					<Button type="submit" loading={ busy === 'pre' }>
						{ __( 'Save prerequisites', 'ohmylms' ) }
					</Button>
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
							setBusy( 'import' );
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
									done(
										sprintf(
											/* translators: %d: rows imported. */
											__(
												'Imported %d rows.',
												'ohmylms'
											),
											body.imported
										)
									)
								)
								.catch( ( failure: Error ) => fail( failure ) );
						} }
					>
						<h2>{ __( 'Import CSV', 'ohmylms' ) }</h2>
						<Field
							id="oml-skills-file"
							label={ __( 'CSV file', 'ohmylms' ) }
						>
							<input
								id="oml-skills-file"
								name="file"
								type="file"
								accept=".csv,text/csv"
							/>
						</Field>
						<Textarea
							label={ __( 'Or paste rows', 'ohmylms' ) }
							value={ csv }
							rows={ 4 }
							onChange={ ( event ) =>
								setCsv( event.target.value )
							}
						/>
						<Button type="submit" loading={ busy === 'import' }>
							{ __( 'Import', 'ohmylms' ) }
						</Button>
					</form>
				</Disclosure>
			) }
			{ message && (
				<Alert tone={ failed ? 'danger' : 'success' }>
					{ message }
				</Alert>
			) }
		</section>
	);
}
