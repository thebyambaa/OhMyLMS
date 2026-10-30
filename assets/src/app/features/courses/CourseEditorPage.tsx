import { Notice } from '@wordpress/components';
import { lazy, Suspense } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link, useParams } from 'react-router-dom';
import { useBootstrap } from '../../bootstrap/BootstrapContext';
import { hasCapability } from '../../bootstrap/config';
import { CertificatePanel } from './CertificatePanel';
import { CourseForm } from './CourseForm';

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

type Tab = 'details' | 'outline' | 'settings' | 'publish';

const TABS: { id: Tab; label: () => string; admin: boolean }[] = [
	{ id: 'details', label: () => __( 'Details', 'ohmylms' ), admin: false },
	{ id: 'outline', label: () => __( 'Outline', 'ohmylms' ), admin: false },
	{ id: 'settings', label: () => __( 'Settings', 'ohmylms' ), admin: false },
	{ id: 'publish', label: () => __( 'Publish', 'ohmylms' ), admin: true },
];

function routeId( value: string | undefined ): number | null {
	return value && /^[1-9]\d*$/.test( value ) ? Number( value ) : null;
}

/**
 * Course editor at `#/courses/:id/:tab?`: Details (metadata and thumbnail), Outline (units and activity
 * placements), Settings (pricing, access, availability, capacity and certificate) and Publish (reviewed
 * publication of the saved program). Publication and certificates need `manage_options`, as on the server.
 */
export default function CourseEditorPage() {
	const params = useParams();
	const id = routeId( params.id );
	const { user } = useBootstrap();
	const admin = hasCapability( user.capabilities, 'manage_options' );
	const tabs = TABS.filter( ( tab ) => admin || ! tab.admin );
	const tab = tabs.find( ( row ) => row.id === ( params.tab ?? 'details' ) );
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
		<>
			<p>
				<Link to="/courses">
					{ __( 'Back to courses', 'ohmylms' ) }
				</Link>
			</p>
			<nav
				aria-label={ __( 'Course editor sections', 'ohmylms' ) }
				className="ohmylms-app-nav"
			>
				<ul>
					{ tabs.map( ( row ) => (
						<li key={ row.id }>
							<Link
								to={
									'details' === row.id
										? `/courses/${ id }`
										: `/courses/${ id }/${ row.id }`
								}
								aria-current={
									row.id === tab.id ? 'page' : undefined
								}
							>
								{ row.label() }
							</Link>
						</li>
					) ) }
				</ul>
			</nav>
			{ ( 'details' === tab.id || 'settings' === tab.id ) && (
				<CourseForm key={ tab.id } id={ id } section={ tab.id } />
			) }
			{ 'settings' === tab.id && admin && (
				<CertificatePanel course={ id } />
			) }
			<Suspense
				fallback={
					<p role="status">{ __( 'Loading…', 'ohmylms' ) }</p>
				}
			>
				{ 'outline' === tab.id && <CourseOutline id={ id } /> }
				{ 'publish' === tab.id && (
					<PublicationPanel
						course={ id }
						disabled={ false }
						onPublished={ () => undefined }
					/>
				) }
			</Suspense>
		</>
	);
}
