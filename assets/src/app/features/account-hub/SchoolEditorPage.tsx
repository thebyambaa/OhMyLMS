import { ListTable } from '../../../ui/ListTable';
import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { lazy, Suspense, useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Link,
	useNavigate,
	useParams,
	useSearchParams,
} from 'react-router-dom';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import {
	createClass,
	createYear,
	getSchool,
	listClasses,
	updateSchool,
} from './api';
import { ConflictNotice, LeaveNotice } from './EditorNotices';
import { ListState } from './ListControls';
import {
	classStatusLabel,
	isConflict,
	MAX_NAME,
	validateClass,
	validateSchool,
	validateYear,
} from './model';
import { panelFromParam } from './operationsModel';
import { SchoolRemovalSection } from './SchoolRemovalSection';
import type {
	ClassInput,
	FormErrors,
	SchoolDetail,
	SchoolInput,
	SchoolPanel,
	YearInput,
} from './types';
import { usePagedList } from './usePagedList';

const SchoolGuardians = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-account-hub-guardians" */ './SchoolGuardians'
	).then( ( module ) => ( { default: module.SchoolGuardians } ) )
);
const SchoolReportPanel = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-account-hub-report" */ './SchoolReportPanel'
	).then( ( module ) => ( { default: module.SchoolReportPanel } ) )
);
const SchoolRollover = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-account-hub-rollover" */ './SchoolRollover'
	).then( ( module ) => ( { default: module.SchoolRollover } ) )
);

const PANEL_LABELS: Record< SchoolPanel, string > = {
	details: __( 'Details and classes', 'ohmylms' ),
	guardians: __( 'Guardians', 'ohmylms' ),
	report: __( 'Report', 'ohmylms' ),
	rollover: __( 'Year rollover', 'ohmylms' ),
};

/**
 * Links between the school's sub-screens (`?panel=`), as a WordPress `.subsubsub` row.
 *
 * @param props        Component props.
 * @param props.school School id.
 * @param props.panel  Current panel.
 */
function PanelLinks( {
	school,
	panel,
}: {
	school: number;
	panel: SchoolPanel;
} ) {
	const ids = Object.keys( PANEL_LABELS ) as SchoolPanel[];
	return (
		<nav aria-label={ __( 'School sections', 'ohmylms' ) }>
			<ul className="subsubsub">
				{ ids.map( ( id, index ) => (
					<li key={ id }>
						<Link
							to={
								'details' === id
									? `/account-hub/schools/${ school }`
									: `/account-hub/schools/${ school }?panel=${ id }`
							}
							className={ id === panel ? 'current' : undefined }
							aria-current={ id === panel ? 'page' : undefined }
						>
							{ PANEL_LABELS[ id ] }
						</Link>
						{ index < ids.length - 1 ? ' |' : '' }
					</li>
				) ) }
			</ul>
			<br className="clear" />
		</nav>
	);
}

/** School editor at `#/account-hub/schools/<id>`: details, academic years and classes. */
export default function SchoolEditorPage() {
	const id = Number( useParams().id );
	const valid = Number.isSafeInteger( id ) && id > 0;
	const request = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				valid
					? getSchool( id, signal )
					: Promise.reject(
							new Error( __( 'School not found.', 'ohmylms' ) )
						),
			[ id, valid ]
		)
	);

	return (
		<>
			<p>
				<Link to="/account-hub?tab=schools">
					{ __( '← All schools', 'ohmylms' ) }
				</Link>
			</p>
			{ request.loading && (
				<p role="status">{ __( 'Loading school…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }{ ' ' }
					<Button variant="link" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.data && (
				<SchoolEditor
					key={ request.data.version }
					initial={ request.data }
					onReload={ request.retry }
				/>
			) }
		</>
	);
}

function SchoolEditor( {
	initial,
	onReload,
}: {
	initial: SchoolDetail;
	onReload: () => void;
} ) {
	const [ params ] = useSearchParams();
	const panel = panelFromParam( params.get( 'panel' ) );
	const [ school, setSchool ] = useState( initial );
	const refresh = useOperation();
	const [ form, setForm ] = useState< SchoolInput >( {
		name: initial.name,
		timezone: initial.timezone,
	} );
	const [ errors, setErrors ] = useState< FormErrors< SchoolInput > >( {} );
	const [ conflict, setConflict ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const save = useOperation();
	const dirty =
		form.name !== school.name || form.timezone !== school.timezone;
	const guard = useUnsavedChangesGuard( dirty );

	function submit() {
		const found = validateSchool( form );
		setErrors( found );
		setSaved( false );
		if ( Object.keys( found ).length ) {
			return;
		}
		setConflict( false );
		void save.run(
			async ( signal ) => {
				try {
					return await updateSchool(
						school.id,
						form,
						school.version,
						signal
					);
				} catch ( failure ) {
					setConflict( isConflict( failure ) );
					throw failure;
				}
			},
			( result ) => {
				setSchool( result );
				setForm( { name: result.name, timezone: result.timezone } );
				setSaved( true );
			}
		);
	}

	const archived = 'archived' === school.status;
	const header = (
		<>
			<h2>
				{ school.name }
				{ school.sample && (
					<span className="ohmylms-account-hub-badge">
						{ ' ' }
						{ __( '(sample school)', 'ohmylms' ) }
					</span>
				) }
			</h2>
			<PanelLinks school={ school.id } panel={ panel } />
			{ archived && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This school is archived. It is read only and closed to its staff, students and families until it is restored.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ school.sample && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'This is the sample school with made-up classes and learners. Remove it from the Schools list when you are done exploring.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ refresh.error && (
				<Notice status="error" isDismissible={ false }>
					{ refresh.error }
				</Notice>
			) }
		</>
	);
	const loading = <p role="status">{ __( 'Loading…', 'ohmylms' ) }</p>;
	if ( 'guardians' === panel ) {
		return (
			<>
				{ header }
				<Suspense fallback={ loading }>
					<SchoolGuardians
						school={ school }
						initialClass={ Number( params.get( 'class_id' ) ) || 0 }
					/>
				</Suspense>
			</>
		);
	}
	if ( 'report' === panel ) {
		return (
			<>
				{ header }
				<Suspense fallback={ loading }>
					<SchoolReportPanel school={ school } />
				</Suspense>
			</>
		);
	}
	if ( 'rollover' === panel ) {
		return (
			<>
				{ header }
				<Suspense fallback={ loading }>
					<SchoolRollover
						key={ school.years
							.map( ( year ) => year.id )
							.join( '-' ) }
						school={ school }
						onChange={ () =>
							void refresh.run(
								( signal ) => getSchool( school.id, signal ),
								setSchool
							)
						}
					/>
				</Suspense>
			</>
		);
	}

	return (
		<>
			{ header }
			<LeaveNotice guard={ guard } />
			{ conflict && <ConflictNotice onReload={ onReload } /> }
			{ saved && (
				<Notice status="success" onRemove={ () => setSaved( false ) }>
					{ __( 'School saved.', 'ohmylms' ) }
				</Notice>
			) }
			{ save.error && ! conflict && (
				<Notice status="error" isDismissible={ false }>
					{ save.error }
				</Notice>
			) }
			<form
				noValidate
				aria-label={ __( 'School details', 'ohmylms' ) }
				onSubmit={ ( event ) => {
					event.preventDefault();
					submit();
				} }
			>
				<TextControl
					label={ __( 'School name', 'ohmylms' ) }
					value={ form.name }
					maxLength={ MAX_NAME }
					required
					disabled={ save.busy || archived }
					help={ errors.name }
					onChange={ ( name ) => setForm( { ...form, name } ) }
				/>
				<TextControl
					label={ __( 'Time zone', 'ohmylms' ) }
					value={ form.timezone }
					required
					disabled={ save.busy || archived }
					help={
						errors.timezone ??
						__( 'IANA name such as Asia/Ulaanbaatar.', 'ohmylms' )
					}
					onChange={ ( timezone ) =>
						setForm( { ...form, timezone } )
					}
				/>
				<Button
					type="submit"
					variant="primary"
					disabled={ save.busy || ! dirty || archived }
					isBusy={ save.busy }
				>
					{ __( 'Save school', 'ohmylms' ) }
				</Button>
			</form>
			<p>
				{ sprintf(
					/* translators: 1: students, 2: teachers. */
					__(
						'%1$d students and %2$d teachers on the roster.',
						'ohmylms'
					),
					school.student_count,
					school.teacher_count
				) }{ ' ' }
				<Link to={ `/account-hub/schools/${ school.id }/roster` }>
					{ __(
						'Invitations, managed students and CSV import',
						'ohmylms'
					) }
				</Link>
			</p>
			<Years
				school={ school }
				onChange={ ( result ) =>
					setSchool( { ...school, years: result.years } )
				}
			/>
			<SchoolClasses school={ school } />
			<SchoolRemovalSection
				school={ school }
				onChange={ ( result ) => setSchool( result ) }
			/>
		</>
	);
}

function Years( {
	school,
	onChange,
}: {
	school: SchoolDetail;
	onChange: ( result: SchoolDetail ) => void;
} ) {
	const empty: YearInput = { label: '', starts_at: '', ends_at: '' };
	const [ form, setForm ] = useState< YearInput >( empty );
	const [ errors, setErrors ] = useState< FormErrors< YearInput > >( {} );
	const operation = useOperation();

	function submit() {
		const found = validateYear( form );
		setErrors( found );
		if ( Object.keys( found ).length ) {
			return;
		}
		void operation.run(
			( signal ) => createYear( school.id, form, signal ),
			( result ) => {
				setForm( empty );
				onChange( result );
			}
		);
	}

	return (
		<section aria-label={ __( 'Academic years', 'ohmylms' ) }>
			<h3>{ __( 'Academic years', 'ohmylms' ) }</h3>
			{ school.years.length ? (
				<ul>
					{ school.years.map( ( year ) => (
						<li key={ year.id }>
							{ sprintf(
								/* translators: 1: label, 2: start date, 3: end date, 4: status. */
								__( '%1$s: %2$s to %3$s (%4$s)', 'ohmylms' ),
								year.label,
								year.starts_at,
								year.ends_at,
								'active' === year.status
									? __( 'active', 'ohmylms' )
									: __( 'archived', 'ohmylms' )
							) }
						</li>
					) ) }
				</ul>
			) : (
				<p>
					{ __(
						'No academic years yet. Add one before creating classes.',
						'ohmylms'
					) }
				</p>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ 'active' === school.status && (
				<form
					noValidate
					aria-label={ __( 'Add academic year', 'ohmylms' ) }
					onSubmit={ ( event ) => {
						event.preventDefault();
						submit();
					} }
				>
					<TextControl
						label={ __( 'Year label', 'ohmylms' ) }
						value={ form.label }
						maxLength={ 100 }
						help={ errors.label }
						onChange={ ( label ) => setForm( { ...form, label } ) }
					/>
					<TextControl
						label={ __( 'Starts on', 'ohmylms' ) }
						type="date"
						value={ form.starts_at }
						help={ errors.starts_at }
						onChange={ ( value ) =>
							setForm( { ...form, starts_at: value } )
						}
					/>
					<TextControl
						label={ __( 'Ends on', 'ohmylms' ) }
						type="date"
						value={ form.ends_at }
						help={ errors.ends_at }
						onChange={ ( value ) =>
							setForm( { ...form, ends_at: value } )
						}
					/>
					<Button
						type="submit"
						variant="secondary"
						disabled={ operation.busy }
						isBusy={ operation.busy }
					>
						{ __( 'Add academic year', 'ohmylms' ) }
					</Button>
				</form>
			) }
		</section>
	);
}

function SchoolClasses( { school }: { school: SchoolDetail } ) {
	const navigate = useNavigate();
	const load = useCallback(
		( page: number, search: string, signal: AbortSignal ) =>
			listClasses( page, search, 'all', school.id, signal ),
		[ school.id ]
	);
	const list = usePagedList( load );
	const years = school.years.filter( ( year ) => 'active' === year.status );
	const empty: ClassInput = { name: '', subject: '', grade: '' };
	const [ form, setForm ] = useState< ClassInput >( empty );
	const [ yearId, setYearId ] = useState( 0 );
	const [ errors, setErrors ] = useState< FormErrors< ClassInput > >( {} );
	const operation = useOperation();
	const selectedYear = yearId || ( years[ 0 ]?.id ?? 0 );

	function submit() {
		const found = validateClass( form );
		setErrors( found );
		if ( Object.keys( found ).length || ! selectedYear ) {
			return;
		}
		void operation.run(
			( signal ) =>
				createClass(
					school.id,
					{ ...form, academic_year_id: selectedYear },
					signal
				),
			( created ) => navigate( `/account-hub/classes/${ created.id }` )
		);
	}

	return (
		<section aria-label={ __( 'Classes of this school', 'ohmylms' ) }>
			<h3>{ __( 'Classes', 'ohmylms' ) }</h3>
			<ListState
				loading={ list.loading }
				error={ list.error }
				total={ list.data.total }
				loadingText={ __( 'Loading classes…', 'ohmylms' ) }
				emptyText={ __( 'No classes yet.', 'ohmylms' ) }
				onRetry={ list.reload }
			>
				<ListTable
					caption={ __( 'Classes of this school', 'ohmylms' ) }
					rows={ list.data.items }
					rowKey={ ( item ) => item.id }
					pagination={ {
						page: list.page,
						totalPages: list.data.totalPages,
						onChange: list.setPage,
					} }
					columns={ [
						{
							id: 'name',
							label: __( 'Name', 'ohmylms' ),
							primary: true,
							render: ( item ) => (
								<strong>
									<Link
										className="row-title"
										to={ `/account-hub/classes/${ item.id }` }
									>
										{ item.name }
									</Link>
								</strong>
							),
						},
						{
							id: 'year',
							label: __( 'Academic year', 'ohmylms' ),
							render: ( item ) => item.academic_year_label ?? '—',
						},
						{
							id: 'students',
							label: __( 'Students', 'ohmylms' ),
							className: 'num',
							render: ( item ) => item.student_count,
						},
						{
							id: 'teachers',
							label: __( 'Teachers', 'ohmylms' ),
							className: 'num',
							render: ( item ) => item.teacher_count,
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( item ) => classStatusLabel( item.status ),
						},
					] }
				/>
			</ListState>
			{ 'active' === school.status && (
				<h4>{ __( 'Add class', 'ohmylms' ) }</h4>
			) }
			{ 'active' === school.status &&
				( ! years.length ? (
					<p>
						{ __(
							'Add an active academic year to create classes.',
							'ohmylms'
						) }
					</p>
				) : (
					<form
						noValidate
						aria-label={ __( 'Add class', 'ohmylms' ) }
						onSubmit={ ( event ) => {
							event.preventDefault();
							submit();
						} }
					>
						{ operation.error && (
							<Notice status="error" isDismissible={ false }>
								{ operation.error }
							</Notice>
						) }
						<TextControl
							label={ __( 'Class name', 'ohmylms' ) }
							value={ form.name }
							maxLength={ MAX_NAME }
							help={ errors.name }
							onChange={ ( name ) =>
								setForm( { ...form, name } )
							}
						/>
						<TextControl
							label={ __( 'Subject', 'ohmylms' ) }
							value={ form.subject }
							maxLength={ 100 }
							help={ errors.subject }
							onChange={ ( subject ) =>
								setForm( { ...form, subject } )
							}
						/>
						<TextControl
							label={ __( 'Grade', 'ohmylms' ) }
							value={ form.grade }
							maxLength={ 50 }
							help={ errors.grade }
							onChange={ ( grade ) =>
								setForm( { ...form, grade } )
							}
						/>
						<SelectControl
							label={ __( 'Academic year', 'ohmylms' ) }
							value={ String( selectedYear ) }
							options={ years.map( ( year ) => ( {
								value: String( year.id ),
								label: year.label,
							} ) ) }
							onChange={ ( value ) =>
								setYearId( Number( value ) )
							}
						/>
						<Button
							type="submit"
							variant="secondary"
							disabled={ operation.busy }
							isBusy={ operation.busy }
						>
							{ __( 'Create class', 'ohmylms' ) }
						</Button>
					</form>
				) ) }
		</section>
	);
}
