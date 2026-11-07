import { Button, Modal, Notice } from '@wordpress/components';
import { lazy, Suspense, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { hasCapability } from '../../bootstrap/config';
import { BuilderSaveProvider } from '../../hooks/useBuilderSave';
import { CertificatePanel } from './CertificatePanel';
import { getCourse } from './api';
import { CourseForm } from './CourseForm';
import { CourseViewLink } from './CourseViewLink';
import type { CourseDetail } from './types';

const CourseOutline = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-course-structure" */ '../course-structure/public'
	).then( ( module ) => ( { default: module.CourseOutline } ) )
);
const PublicationPanel = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-course-structure" */ '../course-structure/public'
	).then( ( module ) => ( { default: module.PublicationPanel } ) )
);

type Tab = 'details' | 'outline' | 'settings';

const TABS: { id: Tab; label: () => string }[] = [
	{ id: 'details', label: () => __( 'Basics', 'ohmylms' ) },
	{ id: 'outline', label: () => __( 'Curriculum', 'ohmylms' ) },
	{ id: 'settings', label: () => __( 'Additional', 'ohmylms' ) },
];

/**
 * Address of one step of the editor.
 *
 * @param id   Course id.
 * @param step Step id.
 */
function stepLink( id: number, step: Tab ) {
	return 'details' === step
		? `/courses/${ id }`
		: `/courses/${ id }/${ step }`;
}

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

/**
 * View/Preview link for the builder bar. Loads the course once, and again when `refresh` changes, so the
 * label follows a publication.
 *
 * @param props         Component props.
 * @param props.id      Course id.
 * @param props.refresh Changes when the course may have changed status.
 */
function CourseViewAction( { id, refresh }: { id: number; refresh: number } ) {
	const [ course, setCourse ] = useState< CourseDetail | null >( null );
	useEffect( () => {
		const controller = new AbortController();
		getCourse( id, controller.signal ).then(
			( loaded ) => ! controller.signal.aborted && setCourse( loaded ),
			() => undefined
		);
		return () => controller.abort();
	}, [ id, refresh ] );
	return course ? <CourseViewLink course={ course } /> : null;
}

/**
 * Course editor at `#/courses/:id/:tab?`, shown as a builder with three steps: Basics (metadata and
 * thumbnail), Curriculum (units and activity placements) and Additional (membership guidance, reviews,
 * capacity and certificate). The bar has one Save, which runs the open step's own save, and Publish for
 * administrators, which opens the reviewed publication of the saved program. Publication and certificates
 * need `manage_options`, as on the server.
 */
export default function CourseEditorPage() {
	const params = useParams();
	const id = routeId( params.id );
	const [ publishing, setPublishing ] = useState( false );
	const [ published, setPublished ] = useState( 0 );
	// Full screen: while the editor is open the admin menu and the hub's own header are hidden (see editors.css).
	useEffect( () => {
		document.body.classList.add( 'oml-builder-fullscreen' );
		return () => document.body.classList.remove( 'oml-builder-fullscreen' );
	}, [] );
	const { user } = useBootstrap();
	const admin = hasCapability( user.capabilities, 'manage_options' );
	const tab = TABS.find( ( row ) => row.id === ( params.tab ?? 'details' ) );
	const at = tab ? TABS.indexOf( tab ) : -1;
	const previous = at > 0 ? TABS[ at - 1 ] : undefined;
	const next = at >= 0 ? TABS[ at + 1 ] : undefined;
	if ( null === id || ! tab ) {
		return (
			<>
				<h2>{ __( 'Course', 'ohmylms' ) }</h2>
				<Notice status="error" isDismissible={ false }>
					{ __( 'This course link is invalid.', 'ohmylms' ) }
				</Notice>
				<Link to="/courses">
					{ __( 'Back to courses', 'ohmylms' ) }
				</Link>
			</>
		);
	}
	return (
		<BuilderSaveProvider
			render={ ( step ) => (
				<div className="oml-editor" key={ id }>
					<header className="oml-builder-bar">
						<div className="oml-builder-lead">
							<p className="oml-editor-breadcrumb">
								<Link to="/courses">
									{ __( 'Back to courses', 'ohmylms' ) }
								</Link>
							</p>
							<strong className="oml-builder-title">
								{ __( 'Course builder', 'ohmylms' ) }
							</strong>
						</div>
						<nav
							className="oml-editor-tabs oml-editor-steps"
							aria-label={ __(
								'Course editor sections',
								'ohmylms'
							) }
						>
							{ TABS.map( ( row ) => (
								<Link
									key={ row.id }
									to={ stepLink( id, row.id ) }
									className={
										row.id === tab.id
											? 'oml-editor-tab is-active'
											: 'oml-editor-tab'
									}
									aria-current={
										row.id === tab.id ? 'page' : undefined
									}
								>
									{ row.label() }
								</Link>
							) ) }
						</nav>
						<div className="oml-builder-end">
							{ step?.course && 0 === published ? (
								<CourseViewLink
									course={ step.course as CourseDetail }
								/>
							) : null }
							{ ( 'outline' === tab.id || published > 0 ) && (
								<CourseViewAction
									key={ id }
									id={ id }
									refresh={ published }
								/>
							) }
							<Button
								variant="secondary"
								disabled={ ! step || ! step.dirty || step.busy }
								isBusy={ Boolean( step?.busy ) }
								onClick={ () => step?.save() }
							>
								{ __( 'Save', 'ohmylms' ) }
							</Button>
							{ admin && (
								<Button
									variant="primary"
									onClick={ () => setPublishing( true ) }
								>
									{ __( 'Publish', 'ohmylms' ) }
								</Button>
							) }
						</div>
					</header>
					{ 'details' === tab.id || 'settings' === tab.id ? (
						<CourseForm
							key={ tab.id }
							id={ id }
							section={ tab.id }
						/>
					) : null }
					{ 'settings' === tab.id && admin && (
						<CertificatePanel course={ id } />
					) }
					<Suspense
						fallback={
							<p role="status">{ __( 'Loading…', 'ohmylms' ) }</p>
						}
					>
						{ 'outline' === tab.id && <CourseOutline id={ id } /> }
					</Suspense>
					{ previous || next ? (
						<nav
							className="oml-editor-stepnav"
							aria-label={ __(
								'Course editor steps',
								'ohmylms'
							) }
						>
							{ previous ? (
								<Link to={ stepLink( id, previous.id ) }>
									{ sprintf(
										/* translators: %s: name of the previous step. */
										__( 'Previous: %s', 'ohmylms' ),
										previous.label()
									) }
								</Link>
							) : (
								<span />
							) }
							{ next && (
								<Link to={ stepLink( id, next.id ) }>
									{ sprintf(
										/* translators: %s: name of the next step. */
										__( 'Next: %s', 'ohmylms' ),
										next.label()
									) }
								</Link>
							) }
						</nav>
					) : null }
					{ publishing && admin && (
						<Modal
							title={ __( 'Preview and publish', 'ohmylms' ) }
							onRequestClose={ () => setPublishing( false ) }
							shouldCloseOnClickOutside={ false }
						>
							{ step?.dirty && (
								<Notice
									status="warning"
									isDismissible={ false }
								>
									{ __(
										'You have unsaved changes. Save them first; publishing uses the saved course.',
										'ohmylms'
									) }
								</Notice>
							) }
							<Suspense
								fallback={
									<p role="status">
										{ __( 'Loading…', 'ohmylms' ) }
									</p>
								}
							>
								<PublicationPanel
									course={ id }
									disabled={ false }
									onPublished={ () =>
										setPublished( ( count ) => count + 1 )
									}
								/>
							</Suspense>
						</Modal>
					) }
				</div>
			) }
		/>
	);
}
