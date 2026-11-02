import { Button, Modal, Notice } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSearchParams } from 'react-router-dom';
import { ListTable } from '../../../ui/ListTable';
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { useListState } from '../../components/listQuery';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { normalizeApiError } from '../../lib/api';
import type { Collection } from '../../lib/api/collection';
import { changeArchive, listSkills, type SkillSummary } from './api';
import { SkillEditor } from './SkillEditor';
import { SkillLinks } from './SkillLinks';

const NO_FILTERS = { search: '' };

export default function SkillsPage() {
	const { user } = useBootstrap();
	const list = useListState( NO_FILTERS, { search: 's' } );
	const { page, setPage } = list;
	const search = list.filters.search;
	const [ query, setQuery ] = useState( search );
	useEffect( () => setQuery( search ), [ search ] );
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
					setPage( page - 1, { replace: true } );
					return;
				}
				setError( problem.message );
				setLoading( false );
			}
		);
		return () => controller.abort();
		// eslint-disable-next-line react-hooks/exhaustive-deps
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
		<section>
			<PageTitleActions>
				<PageTitleAction
					disabled={ busy }
					onClick={ () => {
						setMessage( null );
						setEditor( { item: null } );
					} }
				>
					{ __( 'Add skill', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
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
			<div aria-live="polite">
				{ loading && __( 'Loading skills…', 'ohmylms' ) }
			</div>
			{ ! error && (
				<div aria-busy={ loading }>
					<ListTable< SkillSummary >
						caption={ __( 'Skills library', 'ohmylms' ) }
						heading={ __( 'Skills list', 'ohmylms' ) }
						formLabel={ __( 'Find skills', 'ohmylms' ) }
						busy={ busy || loading }
						rows={ data.items }
						rowKey={ ( item ) => item.id }
						emptyMessage={
							loading ? '' : __( 'No skills found.', 'ohmylms' )
						}
						search={ {
							label: __( 'Search skills by name', 'ohmylms' ),
							submitLabel: __( 'Search', 'ohmylms' ),
							value: query,
							maxLength: 200,
							onChange: setQuery,
							onSubmit: () => {
								list.setFilters( { search: query.trim() } );
								setVersion( ( current ) => current + 1 );
							},
						} }
						pagination={ {
							page,
							totalPages: data.totalPages,
							onChange: setPage,
							href: list.pageHref,
						} }
						columns={ [
							{
								id: 'name',
								label: __( 'Name', 'ohmylms' ),
								primary: true,
								render: ( item ) => (
									<strong className="row-title">
										{ item.name }
									</strong>
								),
							},
							{
								id: 'code',
								label: __( 'Code', 'ohmylms' ),
								render: ( item ) => item.code,
							},
							{
								id: 'status',
								label: __( 'Status', 'ohmylms' ),
								render: ( item ) =>
									item.archived
										? __( 'Archived', 'ohmylms' )
										: __( 'Active', 'ohmylms' ),
							},
						] }
						rowActions={ ( item ) => [
							{
								key: 'edit',
								label: __( 'Edit', 'ohmylms' ),
								disabled: busy || loading,
								ariaLabel: `${ __( 'Edit skill', 'ohmylms' ) }: ${ item.name }`,
								onClick: () => {
									setMessage( null );
									setEditor( { item } );
								},
							},
							{
								key: 'links',
								label: __( 'Links', 'ohmylms' ),
								disabled: busy || loading,
								onClick: () => setLinks( item ),
							},
							user.capabilities.manage_options && {
								key: item.archived ? 'untrash' : 'archive',
								label: item.archived
									? __( 'Restore', 'ohmylms' )
									: __( 'Archive', 'ohmylms' ),
								destructive: ! item.archived,
								disabled: busy || loading,
								onClick: () => setConfirmation( item ),
							},
						] }
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
