import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useListQuery } from '../../components/listQuery';
import { statusViews } from '../../components/listViews';
import { useOperation } from '../../hooks/useOperation';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { createSchool } from './api';
import { HubListTable } from './ListControls';
import { MAX_NAME, validateSchool } from './model';
import { listSchoolsByStatus, type SchoolFilter } from './operationsApi';
import { SampleSchoolActions } from './SampleSchoolActions';
import type { FormErrors, SchoolInput } from './types';
import { usePagedList } from './usePagedList';

const STATUS_FILTER = { status: 'all' };

/**
 * Schools tab: searchable list with status views, a create dialog that opens the new school's editor,
 * and the sample school action.
 */
export function SchoolsTab() {
	const query = useListQuery( STATUS_FILTER );
	const status = query.values.status as SchoolFilter;
	const load = useCallback(
		( page: number, search: string, signal: AbortSignal ) =>
			listSchoolsByStatus( page, search, status, signal ),
		[ status ]
	);
	const list = usePagedList( load, true );
	const deleted = ( useLocation().state as { deleted?: string } | null )
		?.deleted;
	const navigate = useNavigate();
	const operation = useOperation();
	const [ open, setOpen ] = useState( false );
	const [ form, setForm ] = useState< SchoolInput >( {
		name: '',
		timezone: 'UTC',
	} );
	const [ errors, setErrors ] = useState< FormErrors< SchoolInput > >( {} );

	function create() {
		const found = validateSchool( form );
		setErrors( found );
		if ( Object.keys( found ).length ) {
			return;
		}
		void operation.run(
			( signal ) => createSchool( form, signal ),
			( created ) => navigate( `/account-hub/schools/${ created.id }` )
		);
	}

	return (
		<>
			<PageTitleActions>
				<PageTitleAction
					onClick={ () => {
						setForm( { name: '', timezone: 'UTC' } );
						setErrors( {} );
						operation.clearError();
						setOpen( true );
					} }
				>
					{ __( 'Add school', 'ohmylms' ) }
				</PageTitleAction>
			</PageTitleActions>
			<SampleSchoolActions onChange={ list.reload } />
			{ deleted && (
				<Notice status="success" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: school name. */
						__(
							'“%s” was deleted. Its accounts were kept.',
							'ohmylms'
						),
						deleted
					) }
				</Notice>
			) }
			<HubListTable
				caption={ __( 'Schools', 'ohmylms' ) }
				searchLabel={ __( 'Search schools', 'ohmylms' ) }
				list={ list }
				loadingText={ __( 'Loading schools…', 'ohmylms' ) }
				emptyText={ __( 'No schools found.', 'ohmylms' ) }
				views={ statusViews(
					[
						{ value: 'all', label: __( 'All', 'ohmylms' ) },
						{ value: 'active', label: __( 'Active', 'ohmylms' ) },
						{
							value: 'archived',
							label: __( 'Archived', 'ohmylms' ),
						},
					],
					status,
					list.data.total,
					( value ) => query.set( { status: value } ),
					( value ) => query.href( { status: value } )
				) }
				table={ {
					rowKey: ( school ) => school.id,
					columns: [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( school ) => (
								<strong>
									<Link
										className="row-title"
										to={ `/account-hub/schools/${ school.id }` }
									>
										{ school.name }
									</Link>
									{ 'archived' === school.status && (
										<span className="post-state">
											{ ' — ' }
											{ __( 'Archived', 'ohmylms' ) }
										</span>
									) }
									{ school.sample && (
										<span className="post-state">
											{ ' — ' }
											{ __( 'Sample', 'ohmylms' ) }
										</span>
									) }
								</strong>
							),
						},
						{
							id: 'timezone',
							label: __( 'Time zone', 'ohmylms' ),
							render: ( school ) => school.timezone,
						},
						{
							id: 'classes',
							label: __( 'Classes', 'ohmylms' ),
							className: 'num',
							render: ( school ) => school.class_count,
						},
						{
							id: 'students',
							label: __( 'Students', 'ohmylms' ),
							className: 'num',
							render: ( school ) => school.student_count,
						},
						{
							id: 'teachers',
							label: __( 'Teachers', 'ohmylms' ),
							className: 'num',
							render: ( school ) => school.teacher_count,
						},
					],
					rowActions: ( school ) => [
						{
							key: 'edit',
							label: __( 'Edit', 'ohmylms' ),
							render: (
								<Link
									to={ `/account-hub/schools/${ school.id }` }
								>
									{ __( 'Edit', 'ohmylms' ) }
								</Link>
							),
						},
						{
							key: 'view',
							label: __( 'Report', 'ohmylms' ),
							render: (
								<Link
									to={ `/account-hub/schools/${ school.id }?panel=report` }
								>
									{ __( 'Report', 'ohmylms' ) }
								</Link>
							),
						},
						{
							key: 'guardians',
							label: __( 'Guardians', 'ohmylms' ),
							render: (
								<Link
									to={ `/account-hub/schools/${ school.id }?panel=guardians` }
								>
									{ __( 'Guardians', 'ohmylms' ) }
								</Link>
							),
						},
					],
				} }
			/>
			{ open && (
				<Modal
					title={ __( 'Add school', 'ohmylms' ) }
					onRequestClose={ () =>
						! operation.busy && setOpen( false )
					}
					shouldCloseOnClickOutside={ false }
				>
					<form
						noValidate
						onSubmit={ ( event ) => {
							event.preventDefault();
							create();
						} }
					>
						{ operation.error && (
							<Notice status="error" isDismissible={ false }>
								{ operation.error }
							</Notice>
						) }
						<TextControl
							label={ __( 'School name', 'ohmylms' ) }
							value={ form.name }
							maxLength={ MAX_NAME }
							required
							disabled={ operation.busy }
							help={ errors.name }
							onChange={ ( name ) =>
								setForm( { ...form, name } )
							}
						/>
						<TextControl
							label={ __( 'Time zone', 'ohmylms' ) }
							value={ form.timezone }
							required
							disabled={ operation.busy }
							help={
								errors.timezone ??
								__(
									'IANA name such as Asia/Ulaanbaatar. Due dates use it.',
									'ohmylms'
								)
							}
							onChange={ ( timezone ) =>
								setForm( { ...form, timezone } )
							}
						/>
						<Button
							type="submit"
							variant="primary"
							disabled={ operation.busy }
							isBusy={ operation.busy }
						>
							{ __( 'Create school', 'ohmylms' ) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							disabled={ operation.busy }
							onClick={ () => setOpen( false ) }
						>
							{ __( 'Cancel', 'ohmylms' ) }
						</Button>
					</form>
				</Modal>
			) }
		</>
	);
}
