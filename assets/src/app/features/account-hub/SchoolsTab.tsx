import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { createSchool, listSchools } from './api';
import { HubListTable } from './ListControls';
import { MAX_NAME, validateSchool } from './model';
import type { FormErrors, SchoolInput } from './types';
import { usePagedList } from './usePagedList';

/** Schools tab: searchable list and a create dialog that opens the new school's editor. */
export function SchoolsTab() {
	const list = usePagedList( listSchools );
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
			<Button
				variant="primary"
				onClick={ () => {
					setForm( { name: '', timezone: 'UTC' } );
					setErrors( {} );
					operation.clearError();
					setOpen( true );
				} }
			>
				{ __( 'Add school', 'ohmylms' ) }
			</Button>
			<HubListTable
				caption={ __( 'Schools', 'ohmylms' ) }
				searchLabel={ __( 'Search schools', 'ohmylms' ) }
				list={ list }
				loadingText={ __( 'Loading schools…', 'ohmylms' ) }
				emptyText={ __( 'No schools found.', 'ohmylms' ) }
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
