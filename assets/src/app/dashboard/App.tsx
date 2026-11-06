import { Suspense, lazy, useId, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { boot } from './api';

const Home = lazy( () =>
	import( /* webpackChunkName: "admin-app-dashboard-home" */ './Home' ).then(
		( mod ) => ( { default: mod.Home } )
	)
);
const People = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-people" */ './people'
	).then( ( mod ) => ( { default: mod.People } ) )
);
const Classes = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-classes" */ './classes'
	).then( ( mod ) => ( { default: mod.Classes } ) )
);
const Content = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-content" */ './content'
	).then( ( mod ) => ( { default: mod.Content } ) )
);
const CourseBuilder = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-courses" */ './course-builder'
	).then( ( mod ) => ( { default: mod.CourseBuilder } ) )
);
const QuizBuilder = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-quizzes" */ './quiz-builder'
	).then( ( mod ) => ( { default: mod.QuizBuilder } ) )
);
const SkillsScreen = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-skills" */ './skills-screen'
	).then( ( mod ) => ( { default: mod.SkillsScreen } ) )
);
const Gradebook = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-gradebook" */ './gradebook'
	).then( ( mod ) => ( { default: mod.Gradebook } ) )
);
const Reports = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-reports" */ './reports'
	).then( ( mod ) => ( { default: mod.Reports } ) )
);
const Commerce = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-commerce" */ './commerce'
	).then( ( mod ) => ( { default: mod.Commerce } ) )
);
const Schools = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-schools" */ './schools'
	).then( ( mod ) => ( { default: mod.Schools } ) )
);
const SettingsPanel = lazy( () =>
	import(
		/* webpackChunkName: "admin-app-dashboard-settings" */ './settings'
	).then( ( mod ) => ( { default: mod.SettingsPanel } ) )
);

function NavIcon( { name }: { name: string } ) {
	const common = {
		viewBox: '0 0 24 24',
		width: 18,
		height: 18,
		fill: 'none',
		stroke: 'currentColor',
		strokeWidth: 1.75,
		strokeLinecap: 'round' as const,
		strokeLinejoin: 'round' as const,
		'aria-hidden': true,
		className: 'oml-dash-ico',
	};
	if ( name === 'home' ) {
		return (
			<svg { ...common }>
				<path d="M4 10.5 12 4l8 6.5V20H4z" />
			</svg>
		);
	}
	if ( name === 'schools' ) {
		return (
			<svg { ...common }>
				<path d="M4 20V9l8-5 8 5v11" />
				<path d="M9 20v-6h6v6" />
			</svg>
		);
	}
	if ( name === 'classes' ) {
		return (
			<svg { ...common }>
				<path d="M4 6h16v12H4z" />
				<path d="M8 10h8M8 14h5" />
			</svg>
		);
	}
	if ( name === 'people' ) {
		return (
			<svg { ...common }>
				<circle cx="9" cy="8" r="3" />
				<path d="M3.5 19a5.5 5.5 0 0 1 11 0" />
				<circle cx="17" cy="9" r="2" />
				<path d="M16 19a4 4 0 0 1 4-4" />
			</svg>
		);
	}
	if ( name === 'content' ) {
		return (
			<svg { ...common }>
				<path d="M6 4h9l3 3v13H6z" />
				<path d="M9 12h6M9 16h6" />
			</svg>
		);
	}
	if ( name === 'gradebook' ) {
		return (
			<svg { ...common }>
				<path d="M5 4h14v16H5z" />
				<path d="M5 9h14M10 4v16" />
			</svg>
		);
	}
	if ( name === 'reports' ) {
		return (
			<svg { ...common }>
				<path d="M5 19V9M12 19V5M19 19v-7" />
			</svg>
		);
	}
	if ( name === 'commerce' ) {
		return (
			<svg { ...common }>
				<path d="M6 7h12l-1 13H7z" />
				<path d="M9 7a3 3 0 0 1 6 0" />
			</svg>
		);
	}
	return (
		<svg { ...common }>
			<circle cx="12" cy="12" r="3" />
			<path d="M12 3v2M12 19v2M3 12h2M19 12h2" />
		</svg>
	);
}

function sectionGroup( value: string ) {
	if (
		value === 'content' ||
		value === 'courses' ||
		value === 'quizzes' ||
		value === 'skills'
	) {
		return 'content';
	}
	return value;
}

const LABELS: Record< string, string > = {
	home: __( 'Home', 'ohmylms' ),
	schools: __( 'Schools', 'ohmylms' ),
	classes: __( 'Classes', 'ohmylms' ),
	people: __( 'People', 'ohmylms' ),
	content: __( 'Content', 'ohmylms' ),
	courses: __( 'Courses', 'ohmylms' ),
	quizzes: __( 'Quizzes & exams', 'ohmylms' ),
	skills: __( 'Skills', 'ohmylms' ),
	gradebook: __( 'Gradebook', 'ohmylms' ),
	reports: __( 'Reports', 'ohmylms' ),
	commerce: __( 'Commerce', 'ohmylms' ),
	settings: __( 'Settings', 'ohmylms' ),
};

function sectionFromUrl() {
	const value =
		new URLSearchParams( window.location.search ).get( 'section' ) ||
		'home';
	if (
		[ 'courses', 'quizzes', 'skills' ].includes( value ) &&
		boot().nav.includes( 'content' )
	) {
		return value;
	}
	return boot().nav.includes( value ) ? value : boot().nav[ 0 ] || 'home';
}

/** Dashboard shell: top bar, capability-gated nav, and one section at a time. */
export function App() {
	const data = boot();
	const [ section, setSection ] = useState( sectionFromUrl );
	const [ school, setSchool ] = useState( data.schools[ 0 ]?.id || 0 );
	const [ range, setRange ] = useState( '7' );
	const [ open, setOpen ] = useState( false );
	const [ peopleView, setPeopleView ] = useState( 'student' );
	const [ contentFilter, setContentFilter ] = useState( 'course' );
	const [ settingsPane, setSettingsPane ] = useState( 'branding' );
	const schoolId = useId();
	const rangeId = useId();

	function go( next: string ) {
		const url = new URL( window.location.href );
		url.searchParams.set( 'section', next );
		window.history.pushState( {}, '', url );
		setSection( next );
		setOpen( false );
	}

	return (
		<>
			<header className="oml-dash-top">
				<button
					type="button"
					className="oml-dash-btn oml-dash-menu"
					aria-expanded={ open }
					onClick={ () => setOpen( ! open ) }
				>
					{ __( 'Menu', 'ohmylms' ) }
				</button>
				<strong className="oml-dash-logo">
					{ __( 'OhMyLMS', 'ohmylms' ) }
				</strong>
				{ data.schools.length > 0 && (
					<label htmlFor={ schoolId }>
						<span className="screen-reader-text">
							{ __( 'School', 'ohmylms' ) }
						</span>
						<select
							id={ schoolId }
							value={ school }
							onChange={ ( event ) =>
								setSchool( Number( event.target.value ) )
							}
						>
							{ data.owner && (
								<option value={ 0 }>
									{ __( 'All schools', 'ohmylms' ) }
								</option>
							) }
							{ data.schools.map( ( item ) => (
								<option key={ item.id } value={ item.id }>
									{ item.name }
								</option>
							) ) }
						</select>
					</label>
				) }
				<label htmlFor={ rangeId }>
					<span className="screen-reader-text">
						{ __( 'Period', 'ohmylms' ) }
					</span>
					<select
						id={ rangeId }
						value={ range }
						onChange={ ( event ) => setRange( event.target.value ) }
					>
						<option value="7">
							{ __( 'Last 7 days', 'ohmylms' ) }
						</option>
						<option value="30">
							{ __( 'Last 30 days', 'ohmylms' ) }
						</option>
						<option value="term">
							{ __( 'This term', 'ohmylms' ) }
						</option>
					</select>
				</label>
				<span className="oml-dash-user">{ data.user.name }</span>
				{ data.adminUrl && (
					<a className="oml-dash-btn" href={ data.adminUrl }>
						{ __( 'wp-admin', 'ohmylms' ) }
					</a>
				) }
			</header>
			<div className="oml-dash-layout">
				<nav
					className={
						open ? 'oml-dash-side is-open' : 'oml-dash-side'
					}
					aria-label={ __( 'Dashboard', 'ohmylms' ) }
				>
					{ data.nav.map( ( item ) => (
						<div key={ item } className="oml-dash-group">
							<button
								type="button"
								aria-current={
									sectionGroup( section ) === item
										? 'page'
										: undefined
								}
								onClick={ () =>
									go( item === 'content' ? 'courses' : item )
								}
							>
								<NavIcon name={ item } />
								{ LABELS[ item ] || item }
							</button>
							{ item === 'people' && (
								<div className="oml-dash-sub">
									<button
										type="button"
										aria-current={
											section === 'people' &&
											peopleView === 'student'
												? 'page'
												: undefined
										}
										onClick={ () => {
											setPeopleView( 'student' );
											go( 'people' );
										} }
									>
										{ __( 'Students', 'ohmylms' ) }
									</button>
									<button
										type="button"
										aria-current={
											section === 'people' &&
											peopleView === 'teacher'
												? 'page'
												: undefined
										}
										onClick={ () => {
											setPeopleView( 'teacher' );
											go( 'people' );
										} }
									>
										{ __( 'Teachers', 'ohmylms' ) }
									</button>
									<button
										type="button"
										aria-current={
											section === 'people' &&
											peopleView === 'parent'
												? 'page'
												: undefined
										}
										onClick={ () => {
											setPeopleView( 'parent' );
											go( 'people' );
										} }
									>
										{ __( 'Parents', 'ohmylms' ) }
									</button>
									<button
										type="button"
										aria-current={
											section === 'people' &&
											peopleView === 'invitations'
												? 'page'
												: undefined
										}
										onClick={ () => {
											setPeopleView( 'invitations' );
											go( 'people' );
										} }
									>
										{ __( 'Invitations', 'ohmylms' ) }
									</button>
								</div>
							) }
							{ item === 'content' && (
								<div className="oml-dash-sub">
									<button
										type="button"
										aria-current={
											section === 'courses'
												? 'page'
												: undefined
										}
										onClick={ () => go( 'courses' ) }
									>
										{ __( 'Courses', 'ohmylms' ) }
									</button>
									<button
										type="button"
										aria-current={
											section === 'content'
												? 'page'
												: undefined
										}
										onClick={ () => {
											setContentFilter( 'lesson' );
											go( 'content' );
										} }
									>
										{ __( 'Lessons', 'ohmylms' ) }
									</button>
									<button
										type="button"
										aria-current={
											section === 'quizzes'
												? 'page'
												: undefined
										}
										onClick={ () => go( 'quizzes' ) }
									>
										{ __( 'Quizzes & exams', 'ohmylms' ) }
									</button>
									<button
										type="button"
										aria-current={
											section === 'skills'
												? 'page'
												: undefined
										}
										onClick={ () => go( 'skills' ) }
									>
										{ __( 'Skills', 'ohmylms' ) }
									</button>
								</div>
							) }
							{ item === 'settings' && (
								<div className="oml-dash-sub">
									<button
										type="button"
										aria-current={
											section === 'settings' &&
											settingsPane === 'general'
												? 'page'
												: undefined
										}
										onClick={ () => {
											setSettingsPane( 'general' );
											go( 'settings' );
										} }
									>
										{ __( 'General', 'ohmylms' ) }
									</button>
									<button
										type="button"
										aria-current={
											section === 'settings' &&
											settingsPane === 'branding'
												? 'page'
												: undefined
										}
										onClick={ () => {
											setSettingsPane( 'branding' );
											go( 'settings' );
										} }
									>
										{ __( 'Branding', 'ohmylms' ) }
									</button>
									<button
										type="button"
										aria-current={
											section === 'settings' &&
											settingsPane === 'mascot'
												? 'page'
												: undefined
										}
										onClick={ () => {
											setSettingsPane( 'mascot' );
											go( 'settings' );
										} }
									>
										{ __( 'Mascot', 'ohmylms' ) }
									</button>
									<button
										type="button"
										aria-current={
											section === 'settings' &&
											settingsPane === 'roles'
												? 'page'
												: undefined
										}
										onClick={ () => {
											setSettingsPane( 'roles' );
											go( 'settings' );
										} }
									>
										{ __(
											'Roles & permissions',
											'ohmylms'
										) }
									</button>
								</div>
							) }
						</div>
					) ) }
				</nav>
				<main className="oml-dash-main">
					<h1 className="oml-dash-title">
						{ LABELS[ section ] || section }
					</h1>
					<Suspense
						fallback={ <p>{ __( 'Loading…', 'ohmylms' ) }</p> }
					>
						{ section === 'home' && (
							<Home
								school={ school }
								range={ range }
								onPeople={ () => go( 'people' ) }
								onSchool={ setSchool }
							/>
						) }
						{ section === 'schools' && <Schools /> }
						{ section === 'classes' && (
							<Classes school={ school } />
						) }
						{ section === 'people' && (
							<People school={ school } view={ peopleView } />
						) }
						{ section === 'content' && (
							<Content filter={ contentFilter } onOpen={ go } />
						) }
						{ section === 'courses' && <CourseBuilder /> }
						{ section === 'quizzes' && <QuizBuilder /> }
						{ section === 'skills' && <SkillsScreen /> }
						{ section === 'gradebook' && <Gradebook /> }
						{ section === 'reports' && <Reports /> }
						{ section === 'commerce' && <Commerce /> }
						{ section === 'settings' && (
							<SettingsPanel
								school={ school }
								pane={ settingsPane }
							/>
						) }
					</Suspense>
				</main>
			</div>
		</>
	);
}
