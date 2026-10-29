import { createElement, createRoot, render } from '@wordpress/element';
import { createRegistry } from './registry.mjs';
import { lazyEmailComponent } from './LazyFeature';
import { registerBuiltinSlashCommands, slashGroups } from './slashCommands.mjs';
import { registerCurriculumLabels } from './curriculumLabels';
import { ExtensionSlot } from './ExtensionSlot';
import * as api from './api.mjs';
import { MembershipSettingsPanels } from './MembershipSettingsPanels';
import { QuestionEditor } from '../features/question-editor/QuestionEditor';
import { LessonEditor } from './LessonEditor';
import { wrapScreen, extensionPage } from './ScreenExtensions';
import { validateMembership } from '../features/memberships/validateMembership.mjs';
import { membershipScreen } from '../features/memberships/MembershipFrame';
import {
	MEMBERSHIP_SCREENS,
	membershipRoutes,
} from '../features/memberships/membershipRoutes.mjs';
import {
	quizComponents,
	quizEditorComponents,
	questionEditorComponents,
	courseComponents,
	studentComponents,
	gamificationComponents,
	quizReportComponents,
	learningComponents,
	membershipComponents,
	communityComponents,
	analyticsComponents,
	commerceComponents,
	certificateComponents,
	emailComponents,
	settingsComponents,
	integrationComponents,
	webhookComponents,
	taxonomyComponents,
	setupComponents,
	questionBankComponents,
	curriculumComponents,
	bundleComponents,
	contentHubComponents,
} from './lazyFeatures';
import { registerQuestionBankPages } from '../features/question-bank/registerPages';
import { registerCurriculumPages } from '../features/curriculum/registerPages';
import {
	contentHubScreen,
	redirectScreen,
	withContentHubMenu,
} from '../features/content-hub/ContentHub';
import {
	contentHubRoutes,
	HUB_APP_ROUTES,
	HUB_EXTENSION_TABS,
	HUB_MENU_ROUTES,
	SYLLABUS_ROUTE,
} from '../features/content-hub/hubRoutes.mjs';
import { registerGamificationTab } from '../features/gamification/extraTabs.mjs';
import {
	CertificatesMoved,
	certificatesTab,
	withGamificationMenu,
} from '../features/gamification/CertificatesTab';
import {
	CERTIFICATES_TAB,
	certificatesInGamification,
} from '../features/gamification/model.mjs';
const registry = createRegistry();
registerQuestionBankPages( registry, questionBankComponents );
registerCurriculumPages( registry, {
	...curriculumComponents,
	...bundleComponents,
} );
registerCurriculumLabels();
registerBuiltinSlashCommands( registry );
const roots = new WeakMap();
const publicApi = {
	lazyEmailComponent,
	...registry,
	api,
	validateMembership,
	quizReportComponents,
	quizComponents,
	quizEditorComponents,
	questionEditorComponents,
	courseComponents,
	studentComponents,
	gamificationComponents,
	learningComponents,
	membershipComponents,
	communityComponents,
	analyticsComponents,
	commerceComponents,
	certificateComponents,
	emailComponents,
	settingsComponents,
	integrationComponents,
	webhookComponents,
	taxonomyComponents,
	setupComponents,
	questionBankComponents,
	curriculumComponents,
	bundleComponents,
	contentHubComponents,
	extendRoutes( routes ) {
		// Course categories and tags were replaced by the curriculum and Course Bundles. Their old screens
		// could only report errors, so old links to them open the replacements, which are Content Hub tabs.
		const replacedBy = {
			'/categories': 'curriculum',
			'/tags': 'curriculum',
		};
		// With Gamification on, Certificates is one of its tabs (`#/gamification/certificates`); the old
		// `#/certificates` address opens it and the template editor keeps the Gamification entry highlighted.
		// With Gamification off there is no such screen, so Certificates keeps its own screen and menu entry.
		const certificates = routes.find(
			( route ) => route.path === '/certificates'
		);
		const certificatesMoved =
			Boolean( certificates ) &&
			certificatesInGamification( window.ohmylms_params );
		if ( certificatesMoved ) {
			registerGamificationTab( {
				key: CERTIFICATES_TAB,
				label: 'Certificates',
				Component: certificatesTab( certificates.element, () =>
					createElement( ExtensionSlot, {
						registry,
						kind: 'editor-panel',
						name: '/certificates',
						context: {
							route: '/certificates',
							hash: window.location.hash,
						},
					} )
				),
			} );
		}
		const coreRoutes = routes.map( ( route ) => {
			if ( MEMBERSHIP_SCREENS[ route.path ] ) {
				return {
					...route,
					element: membershipScreen(
						route.element,
						MEMBERSHIP_SCREENS[ route.path ]
					),
				};
			}
			if ( certificatesMoved && route.path === '/certificates' ) {
				return { ...route, element: CertificatesMoved };
			}
			if ( certificatesMoved && route.path === '/certificate-edit/:id' ) {
				return {
					...route,
					element: withGamificationMenu( route.element ),
				};
			}
			const replacement =
				replacedBy[ route.path ] &&
				registry.get( 'admin-page', replacedBy[ route.path ] );
			if ( replacement ) {
				return {
					...route,
					element: contentHubScreen(
						extensionPage( replacement ),
						HUB_EXTENSION_TABS[ replacement.id ]
					),
				};
			}
			// Courses, Quizzes and Assignments are tabs of the Content Hub, and the screens opened from them
			// keep its menu entry highlighted.
			if ( HUB_APP_ROUTES[ route.path ] ) {
				return {
					...route,
					element: contentHubScreen(
						route.element,
						HUB_APP_ROUTES[ route.path ]
					),
				};
			}
			if ( HUB_MENU_ROUTES.includes( route.path ) ) {
				return {
					...route,
					element: withContentHubMenu( route.element ),
				};
			}
			return route;
		} );
		// Skills, Question Bank, Curriculum and Course Bundles are SDK admin pages that the hub also shows
		// as tabs. Without the registered page the tab falls back to the component itself.
		const hubPage = ( id, fallback ) => {
			const entry = registry.get( 'admin-page', id );
			return entry ? extensionPage( entry ) : fallback;
		};
		const hubPages = {
			...contentHubComponents,
			SkillsPage: hubPage( 'skills', questionBankComponents.SkillsPage ),
			QuestionBankPage: hubPage(
				'question-bank',
				questionBankComponents.QuestionBankPage
			),
			CurriculumPage: hubPage(
				'curriculum',
				curriculumComponents.CurriculumPage
			),
			BundlesPage: hubPage( 'bundles', bundleComponents.BundlesPage ),
		};
		// The application's own list screens keep their extension slots when shown as hub tabs.
		const hubScreens = routes.map( ( route ) =>
			HUB_APP_ROUTES[ route.path ]
				? {
						...route,
						element: wrapScreen(
							route.element,
							route.path,
							registry
						),
					}
				: route
		);
		return [
			...coreRoutes.map( ( route ) =>
				route.path === '*'
					? route
					: {
							...route,
							element: wrapScreen(
								route.element,
								route.path,
								registry
							),
						}
			),
			...contentHubRoutes(
				hubScreens,
				hubPages,
				contentHubScreen,
				redirectScreen
			),
			// The syllabus editor is a full page opened from the Curriculum tab, like the course editor.
			{
				path: '/content-hub/curriculum/compare',
				element: withContentHubMenu(
					extensionPage( {
						id: 'syllabus-comparison',
						render: curriculumComponents.ComparisonWorkspace,
					} )
				),
			},
			{
				path: '/content-hub/curriculum/skill-map',
				element: withContentHubMenu(
					extensionPage( {
						id: 'skill-map',
						render: curriculumComponents.SkillMapPage,
					} )
				),
			},
			{
				path: '/content-hub/curriculum/subjects',
				element: withContentHubMenu(
					extensionPage( {
						id: 'subject-profiles',
						render: curriculumComponents.SubjectProfilesPage,
					} )
				),
			},
			{
				path: SYLLABUS_ROUTE,
				element: withContentHubMenu(
					extensionPage( {
						id: 'syllabus',
						render: curriculumComponents.SyllabusPage,
					} )
				),
			},
			...membershipRoutes(
				routes.map( ( route ) => ( {
					...route,
					element: wrapScreen( route.element, route.path, registry ),
				} ) ),
				membershipScreen
			),
			...registry.list( 'admin-page' ).map( ( entry ) => ( {
				path: `/extensions/${ entry.id }`,
				element: HUB_EXTENSION_TABS[ entry.id ]
					? contentHubScreen(
							extensionPage( entry ),
							HUB_EXTENSION_TABS[ entry.id ]
						)
					: extensionPage( entry ),
			} ) ),
		];
	},
	membershipPanels: ( props ) =>
		createElement( MembershipSettingsPanels, { registry, ...props } ),
	lessonEditor( editor, fallback ) {
		const entry = registry.get( 'lesson-editor', editor.lesson?.type );
		return entry?.enabled
			? createElement( LessonEditor, { entry, editor } )
			: fallback;
	},
	slashGroups: () => slashGroups( registry ),
	questionTypes( store ) {
		const manifest = window.ohmylmsExtensionManifest?.question || {};
		return registry
			.list( 'question-editor' )
			.filter( ( entry ) => manifest[ entry.id ] )
			.map( ( entry ) => ( {
				type: entry.id,
				name: entry.label,
				subTitle: entry.description || '',
				isPro: false,
				icon: () =>
					createElement( 'span', { 'aria-hidden': true }, '?' ),
				thumbIcon: () => null,
				edit: () => createElement( QuestionEditor, { entry, store } ),
				settings: { required: true, score: true },
			} ) );
	},
	lessonTypes( onSelect ) {
		const manifest = window.ohmylmsExtensionManifest?.lesson || {};
		return registry
			.list( 'lesson-editor' )
			.filter( ( entry ) => manifest[ entry.id ] )
			.map( ( entry ) => ( {
				key: `extension-${ entry.id }`,
				lessonType: entry.id,
				type: 'lesson',
				title: entry.label,
				description: entry.description || '',
				icon: createElement( 'span', null, '+' ),
				onClick: () => onSelect( entry.id ),
			} ) );
	},
	renderSlot: ( name, context = {}, kind = 'slot' ) =>
		createElement( ExtensionSlot, { registry, name, context, kind } ),
	renderEditor: ( kind, id, context ) => {
		const entry = registry.get( kind, id );
		return entry && entry.enabled
			? createElement( ExtensionSlot, {
					registry,
					name: id,
					context: { ...context, type: id },
					kind,
					onlyId: id,
				} )
			: null;
	},
	mount( element, name, context = {}, kind = 'slot' ) {
		const view = createElement( ExtensionSlot, {
			registry,
			name,
			context,
			kind,
		} );
		if ( createRoot ) {
			if ( ! roots.has( element ) ) {
				roots.set( element, createRoot( element ) );
			}
			roots.get( element ).render( view );
		} else {
			render( view, element );
		}
	},
};
window.ohmylms = {
	...( window.ohmylms || {} ),
	extensions: Object.freeze( publicApi ),
};
window.dispatchEvent(
	new CustomEvent( 'ohmylms:extensions-ready', { detail: publicApi } )
);
function mountSlots() {
	const assistant = document.getElementById( 'ohmylms-admin-assistant-root' );
	if ( assistant ) {
		import(
			/* webpackChunkName: "admin-assistant" */ '../features/assistant/launcher'
		)
			.then( ( { mountAssistant } ) => mountAssistant( assistant ) )
			.catch( () => {
				assistant.textContent =
					'The administrator assistant could not load. Refresh to retry.';
			} );
	}
	if ( document.querySelector( '[data-ohmylms-registration-fields]' ) ) {
		import(
			/* webpackChunkName: "registration" */ '../features/students/mountRegistration'
		)
			.then( ( { mountRegistration } ) => mountRegistration() )
			.catch( ( error ) =>
				window.dispatchEvent(
					new CustomEvent( 'ohmylms:extension-error', {
						detail: { id: 'registration', message: error.message },
					} )
				)
			);
	}
	document.querySelectorAll( '[data-ohmylms-slot]' ).forEach( ( element ) => {
		let context = {};
		try {
			context = JSON.parse( element.dataset.ohmylmsContext || '{}' );
		} catch {
			return;
		}
		publicApi.mount(
			element,
			element.dataset.ohmylmsSlot,
			context,
			element.dataset.ohmylmsKind || 'slot'
		);
	} );
}
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mountSlots, { once: true } );
} else {
	mountSlots();
}
export default publicApi;
