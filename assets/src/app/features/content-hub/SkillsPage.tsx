import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { Table } from '../../../ui/Table';
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { Pagination } from '../../components/Pagination';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { changeArchive, listSkills, type SkillSummary } from './api';
import { SkillEditor } from './SkillEditor';
import { SkillLinks } from './SkillLinks';

export default function SkillsPage() {
	const { user } = useBootstrap();
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ version, setVersion ] = useState( 0 );
	const [ data, setData ] = useState< Collection< SkillSummary > >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ editor, setEditor ] = useState< {
		item: SkillSummary | null;
	} | null >( null );
	const [ confirmation, setConfirmation ] = useState< SkillSummary | null >(
		null
	);
	const [ links, setLinks ] = useState< SkillSummary | null >( null );
	const [ busy, setBusy ] = useState( false );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	const [ params, setParams ] = useSearchParams();
	const add = params.get( 'add' );
	useEffect( () => {
		if ( '1' !== add ) {
			return;
		}
		setEditor( { item: null } );
		setParams(
			( current ) => {
				const next = new URLSearchParams( current );
				next.delete( 'add' );
				return next;
			},
			{ replace: true }
		);
	}, [ add, setParams ] );
	useEffect( () => {
		const controller = new AbortController();
		setLoading( true );
		setError( null );
		listSkills( page, search, controller.signal ).then(
			( result ) => {
				if ( ! controller.signal.aborted ) {
					setData( result );
					setLoading( false );
				}
			},
			( failure ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				const problem = normalizeApiError( failure );
				if (
					page > 1 &&
					problem.code === 'ohmylms_skill_invalid_page'
				) {
					setPage( ( current ) => current - 1 );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
	}, [ page, search, version ] );
	async function run( work: ( signal: AbortSignal ) => Promise< void > ) {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( null );
		setMessage( null );
		try {
			await work( controller.signal );
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setError( normalizeApiError( failure ).message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				setBusy( false );
				operation.current = null;
			}
		}
	}
	return (
		<section aria-labelledby="skills-heading">
			<h2 id="skills-heading">
				{ __( 'Content Hub — Skills', 'ohmylms' ) }
			</h2>
			<p>
				{ __(
					'Shared skills, hierarchy and prerequisites. Skills are a core feature.',
					'ohmylms'
				) }
			</p>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( null ) }>
					{ message }
				</Notice>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
					<Button
						onClick={ () =>
							setVersion( ( current ) => current + 1 )
						}
					>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			<Button
				variant="primary"
				disabled={ busy }
				onClick={ () => {
					setMessage( null );
					setEditor( { item: null } );
				} }
			>
				{ __( 'Add skill', 'ohmylms' ) }
			</Button>
			<form
				role="search"
				aria-label={ __( 'Find skills', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setSearch( query.trim() );
					setPage( 1 );
					setVersion( ( current ) => current + 1 );
				} }
			>
				<TextControl
					label={ __( 'Search skills by name', 'ohmylms' ) }
					value={ query }
					maxLength={ 200 }
					onChange={ setQuery }
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<div aria-live="polite">
				{ loading && __( 'Loading skills…', 'ohmylms' ) }
				{ ! loading &&
					! error &&
					! data.items.length &&
					__( 'No skills found.', 'ohmylms' ) }
			</div>
			{ ! error && (
				<div aria-busy={ loading }>
					<Table caption={ __( 'Skills library', 'ohmylms' ) }>
						<thead>
							<tr>
								<th scope="col">{ __( 'Name', 'ohmylms' ) }</th>
								<th scope="col">{ __( 'Code', 'ohmylms' ) }</th>
								<th scope="col">
									{ __( 'Status', 'ohmylms' ) }
								</th>
								<th scope="col">
									{ __( 'Actions', 'ohmylms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ data.items.map( ( item ) => (
								<tr key={ item.id }>
									<th scope="row">{ item.name }</th>
									<td>{ item.code }</td>
									<td>
										{ item.archived
											? __( 'Archived', 'ohmylms' )
											: __( 'Active', 'ohmylms' ) }
									</td>
									<td>
										<Button
											variant="tertiary"
											disabled={ busy || loading }
											onClick={ () => {
												setMessage( null );
												setEditor( { item } );
											} }
											aria-label={ `${ __( 'Edit skill', 'ohmylms' ) }: ${ item.name }` }
										>
											{ __( 'Edit', 'ohmylms' ) }
										</Button>
										<Button
											variant="tertiary"
											disabled={ busy || loading }
											onClick={ () => setLinks( item ) }
										>
											{ __( 'Links', 'ohmylms' ) }
										</Button>
										{ user.capabilities.manage_options && (
											<Button
												variant="tertiary"
												disabled={ busy || loading }
												onClick={ () =>
													setConfirmation( item )
												}
											>
												{ item.archived
													? __( 'Restore', 'ohmylms' )
													: __(
															'Archive',
															'ohmylms'
														) }
											</Button>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</Table>
					<Pagination
						page={ page }
						pages={ data.totalPages }
						busy={ loading }
						onChange={ setPage }
					/>
				</div>
			) }
			{ editor && (
				<SkillEditor
					id={ editor.item?.id ?? null }
					onClose={ () => setEditor( null ) }
					onSaved={ () => {
						setEditor( null );
						setMessage( __( 'Skill saved.', 'ohmylms' ) );
						setVersion( ( current ) => current + 1 );
					} }
					onLinks={ () => {
						setLinks( editor.item );
						setEditor( null );
					} }
				/>
			) }
			{ links && (
				<SkillLinks
					skill={ links }
					onClose={ () => setLinks( null ) }
				/>
			) }
			{ confirmation && (
				<Modal
					title={
						confirmation.archived
							? __( 'Restore skill', 'ohmylms' )
							: __( 'Archive skill', 'ohmylms' )
					}
					onRequestClose={ () => {
						if ( ! busy ) {
							setConfirmation( null );
						}
					} }
					shouldCloseOnClickOutside={ false }
				>
					<p>{ confirmation.name }</p>
					<p>
						{ __(
							'Archiving preserves existing learning records and links.',
							'ohmylms'
						) }
					</p>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<Button
						variant="primary"
						disabled={ busy }
						isBusy={ busy }
						onClick={ () =>
							void run( async ( signal ) => {
								await changeArchive(
									confirmation.id,
									! confirmation.archived,
									signal
								);
								if ( ! signal.aborted ) {
									setConfirmation( null );
									setMessage(
										__( 'Skill status updated.', 'ohmylms' )
									);
									setVersion( ( current ) => current + 1 );
								}
							} )
						}
					>
						{ __( 'Confirm', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () => setConfirmation( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}
