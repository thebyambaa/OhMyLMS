import { Table } from '../../../ui/Table';
import {
	Button,
	Notice,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Pagination } from '../../components/Pagination';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	isStale,
	listSharedSkills,
	readSharedSkill,
	saveSharedSkill,
	SHARED_PER_PAGE,
	sharedSkillProblem,
	type SharedSkillInput,
	type SharedSkillSummary,
} from './compare';

const EMPTY: SharedSkillInput = {
	name: '',
	description: '',
	topic: '',
	subject: '',
};

/** Shared skills: search, the objectives mapped to each, and editing against the read revision. */
export function CompareSharedSkills() {
	const [ query, setQuery ] = useState( { search: '', subject: '' } );
	const [ draft, setDraft ] = useState( query );
	const [ page, setPage ] = useState( 1 );
	const [ editing, setEditing ] = useState<
		SharedSkillSummary | 'new' | null
	>( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const list = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				listSharedSkills( query.search, query.subject, page, signal ),
			[ query, page ]
		)
	);
	const pages = Math.ceil( ( list.data?.total ?? 0 ) / SHARED_PER_PAGE );

	return (
		<section aria-labelledby="oml-compare-shared">
			<h3 id="oml-compare-shared">
				{ __( 'Shared skills', 'ohmylms' ) }
			</h3>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			<Button
				variant="primary"
				onClick={ () => {
					setMessage( null );
					setEditing( 'new' );
				} }
			>
				{ __( 'New shared skill', 'ohmylms' ) }
			</Button>
			<form
				role="search"
				aria-label={ __( 'Find shared skills', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setPage( 1 );
					setQuery( {
						search: draft.search.trim(),
						subject: draft.subject.trim(),
					} );
				} }
			>
				<TextControl
					label={ __( 'Search shared skills', 'ohmylms' ) }
					value={ draft.search }
					maxLength={ 100 }
					onChange={ ( search ) => setDraft( { ...draft, search } ) }
				/>
				<TextControl
					label={ __( 'Subject', 'ohmylms' ) }
					value={ draft.subject }
					maxLength={ 100 }
					onChange={ ( subject ) =>
						setDraft( { ...draft, subject } )
					}
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ list.loading && (
				<p role="status">
					{ __( 'Loading shared skills…', 'ohmylms' ) }
				</p>
			) }
			{ list.error && (
				<Notice status="error" isDismissible={ false }>
					{ list.error }{ ' ' }
					<Button variant="link" onClick={ list.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ list.data && ! list.data.items.length && (
				<p>{ __( 'No shared skills found.', 'ohmylms' ) }</p>
			) }
			{ list.data && list.data.items.length > 0 && (
				<>
					<Table caption={ __( 'Shared skills', 'ohmylms' ) }>
						<thead>
							<tr>
								<th scope="col">{ __( 'Name', 'ohmylms' ) }</th>
								<th scope="col">
									{ __( 'Subject', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Topic', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Mapped objectives', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Actions', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ list.data.items.map( ( skill ) => (
								<tr key={ skill.id }>
									<th scope="row">{ skill.name }</th>
									<td>{ skill.subject || '—' }</td>
									<td>{ skill.topic || '—' }</td>
									<td>
										{ skill.mapped_from.length
											? skill.mapped_from
													.map(
														( mapping ) =>
															`${ mapping.name } (${ mapping.relation })`
													)
													.join( ', ' )
											: __( 'None', 'ohmylms' ) }
									</td>
									<td>
										<Button
											variant="link"
											aria-label={ sprintf(
												/* translators: %s: skill name. */
												__( 'Edit %s', 'ohmylms' ),
												skill.name
											) }
											onClick={ () => {
												setMessage( null );
												setEditing( skill );
											} }
										>
											{ __( 'Edit', 'ohmylms' ) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
					<Pagination
						page={ page }
						pages={ pages }
						busy={ list.loading }
						onChange={ setPage }
					/>
				</>
			) }
			{ editing && (
				<SharedSkillForm
					key={ 'new' === editing ? 'new' : editing.id }
					skill={ 'new' === editing ? null : editing }
					onClose={ () => setEditing( null ) }
					onSaved={ ( saved ) => {
						setEditing( null );
						setMessage(
							sprintf(
								/* translators: %s: skill name. */
								__( 'Saved %s.', 'ohmylms' ),
								saved.name
							)
						);
						list.retry();
					} }
				/>
			) }
		</section>
	);
}

function SharedSkillForm( {
	skill,
	onClose,
	onSaved,
}: {
	skill: SharedSkillSummary | null;
	onClose: () => void;
	onSaved: ( skill: SharedSkillSummary ) => void;
} ) {
	const [ form, setForm ] = useState< SharedSkillInput >(
		skill
			? {
					name: skill.name,
					description: skill.description,
					topic: skill.topic,
					subject: skill.subject,
				}
			: EMPTY
	);
	const [ revision, setRevision ] = useState( skill?.revision ?? null );
	const [ problem, setProblem ] = useState< string | null >( null );
	const [ stale, setStale ] = useState( false );
	const [ notice, setNotice ] = useState< string | null >( null );
	const operation = useOperation();

	function save() {
		const found = sharedSkillProblem( form );
		setProblem( found );
		if ( found ) {
			return;
		}
		void operation.run( async ( signal ) => {
			try {
				return await saveSharedSkill(
					skill?.id ?? 0,
					form,
					revision,
					signal
				);
			} catch ( failure ) {
				setStale( isStale( failure ) );
				throw failure;
			}
		}, onSaved );
	}

	function reloadKeepingEdits() {
		if ( ! skill ) {
			return;
		}
		void operation.run(
			( signal ) => readSharedSkill( skill.id, signal ),
			( fresh ) => {
				setRevision( fresh.revision );
				setStale( false );
				setNotice(
					__(
						'Reloaded the latest version. Your unsaved edits were kept; save again to replace it.',
						'ohmylms'
					)
				);
			}
		);
	}

	return (
		<form
			noValidate
			aria-label={
				skill
					? __( 'Edit shared skill', 'ohmylms' )
					: __( 'New shared skill', 'ohmylms' )
			}
			onSubmit={ ( event ) => {
				event.preventDefault();
				save();
			} }
		>
			<h4>
				{ skill
					? __( 'Edit shared skill', 'ohmylms' )
					: __( 'New shared skill', 'ohmylms' ) }
			</h4>
			{ notice && (
				<Notice status="info" onRemove={ () => setNotice( null ) }>
					{ notice }
				</Notice>
			) }
			{ stale && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This skill or its mappings changed after you opened it.',
						'ohmylms'
					) }{ ' ' }
					<Button variant="secondary" onClick={ reloadKeepingEdits }>
						{ __( 'Reload and keep my edits', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ operation.error && ! stale && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ problem && (
				<p role="alert" className="oml-compare-problem">
					{ problem }
				</p>
			) }
			<TextControl
				label={ __( 'Name', 'ohmylms' ) }
				value={ form.name }
				maxLength={ 190 }
				onChange={ ( name ) => setForm( { ...form, name } ) }
			/>
			<TextareaControl
				label={ __( 'Scope', 'ohmylms' ) }
				value={ form.description }
				onChange={ ( description ) =>
					setForm( { ...form, description } )
				}
			/>
			<TextControl
				label={ __( 'Topic', 'ohmylms' ) }
				value={ form.topic }
				maxLength={ 100 }
				onChange={ ( topic ) => setForm( { ...form, topic } ) }
			/>
			<TextControl
				label={ __( 'Subject', 'ohmylms' ) }
				value={ form.subject }
				maxLength={ 100 }
				onChange={ ( subject ) => setForm( { ...form, subject } ) }
			/>
			{ skill && skill.mapped_from.length > 0 && (
				<p>
					{ sprintf(
						/* translators: %d: mapped objectives. */
						__(
							'Saving changes this skill for all %d mapped objectives.',
							'ohmylms'
						),
						skill.mapped_from.length
					) }
				</p>
			) }
			<Button
				type="submit"
				variant="primary"
				disabled={ operation.busy || stale }
				isBusy={ operation.busy }
			>
				{ __( 'Save shared skill', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				variant="tertiary"
				disabled={ operation.busy }
				onClick={ onClose }
			>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>
		</form>
	);
}
