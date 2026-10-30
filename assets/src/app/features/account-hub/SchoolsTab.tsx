import { Table } from '../../../ui/Table';
import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useNavigate } from 'react-router-dom';
import { Pagination } from '../../components/Pagination';
import { useOperation } from '../../hooks/useOperation';
import { createSchool, listSchools } from './api';
import { ListState, SearchForm } from './ListControls';
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
			<SearchForm
				label={ __( 'Search schools', 'ohmylms' ) }
				busy={ list.loading }
				onSearch={ list.setSearch }
			/>
			<ListState
				loading={ list.loading }
				error={ list.error }
				total={ list.data.total }
				loadingText={ __( 'Loading schools…', 'ohmylms' ) }
				emptyText={ __( 'No schools found.', 'ohmylms' ) }
				onRetry={ list.reload }
			>
				<Table caption={ __( 'Schools', 'ohmylms' ) } striped>
					<thead>
						<tr>
							{ [
								__( 'Name', 'ohmylms' ),
								__( 'Time zone', 'ohmylms' ),
								__( 'Classes', 'ohmylms' ),
								__( 'Students', 'ohmylms' ),
								__( 'Teachers', 'ohmylms' ),
							].map( ( title ) => (
								<th key={ title } scope="col">
									{ title }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ list.data.items.map( ( school ) => (
							<tr key={ school.id }>
								<th scope="row">
									<Link
										to={ `/account-hub/schools/${ school.id }` }
									>
										{ school.name }
									</Link>
								</th>
								<td>{ school.timezone }</td>
								<td>{ school.class_count }</td>
								<td>{ school.student_count }</td>
								<td>{ school.teacher_count }</td>
							</tr>
						) ) }
					</tbody>
				</Table>
				<Pagination
					page={ list.page }
					pages={ list.data.totalPages }
					busy={ list.loading }
					onChange={ list.setPage }
				/>
			</ListState>
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
