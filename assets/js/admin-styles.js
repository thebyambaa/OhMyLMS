/* Ordered stylesheet slots are printed by WordPress before the design system. */
( function () {
	'use strict';
	const pending = new Map();
	const featureGroups = {
		courseComponents: [
			'courses',
			'quizzes',
			'skills',
			'curriculum',
			'block-editor',
		],
		learningComponents: [ 'courses', 'quizzes', 'block-editor' ],
		quizEditorComponents: [ 'quizzes', 'block-editor' ],
		questionEditorComponents: [ 'quizzes', 'block-editor' ],
		questionBankComponents: [
			'quizzes',
			'skills',
			'curriculum',
			'block-editor',
		],
		curriculumComponents: [ 'curriculum', 'skills' ],
		bundleComponents: [ 'curriculum', 'skills' ],
		contentHubComponents: [ 'curriculum', 'skills' ],
		communityComponents: [ 'communities', 'block-editor' ],
		studentComponents: [ 'students' ],
		commerceComponents: [ 'commerce' ],
		membershipComponents: [ 'commerce', 'courses' ],
		settingsComponents: [ 'settings', 'commerce', 'integrations' ],
		integrationComponents: [ 'integrations' ],
		gamificationComponents: [ 'gamification' ],
		certificateComponents: [ 'gamification', 'block-editor' ],
		setupComponents: [ 'setup', 'settings', 'commerce' ],
		emailComponents: [ 'emails', 'email-shell' ],
		EmailEditorPage: [
			'emails',
			'email-shell',
			'email-editor',
			'block-editor',
		],
		EmailEditor: [
			'emails',
			'email-shell',
			'email-editor',
			'block-editor',
		],
		EmailTemplate: [
			'emails',
			'email-shell',
			'email-editor',
			'automations',
			'block-editor',
		],
	};
	function load( link ) {
		if ( pending.has( link ) ) {
			return pending.get( link );
		}
		const promise = new Promise( ( resolve, reject ) => {
			link.onload = () => resolve();
			link.onerror = () => {
				pending.delete( link );
				link.removeAttribute( 'href' );
				reject( new Error( 'Could not load OhMyLMS styles.' ) );
			};
			link.href = link.dataset.omlHref;
		} );
		pending.set( link, promise );
		return promise;
	}
	function ensureGroups( groups ) {
		return Promise.all(
			Array.from( document.querySelectorAll( 'link[data-oml-group]' ) )
				.filter( ( link ) => groups.includes( link.dataset.omlGroup ) )
				.map( load )
		);
	}
	window.ohmylmsStyles = {
		ensure: ( feature ) => ensureGroups( featureGroups[ feature ] || [] ),
	};
	// Support portals, synchronous extension screens and back/forward navigation.
	const selectors = {
		gamification:
			'[class*="ohmylms-gamification"], [class*="ohmylms-streak"], [class*="ohmylms-badge"], [class*="ohmylms-certificate"]',
		skills: '[class*="ohmylms-skill"], [class*="ohmylms-ai-"], [class*="ohmylms-mx"], [class*="ohmylms-bkt"]',
		curriculum:
			'[class*="ohmylms-library"], [class*="ohmylms-curriculum"], [class*="ohmylms-syllabus"], [class*="ohmylms-subject"], [class*="ohmylms-workspace"], [class*="ohmylms-ws-"], [class*="ohmylms-unified-workspace"], [class*="ohmylms-content-hub"]',
		courses:
			'[class*="ohmylms-course"], [class*="ohmylms-chapter"], [class*="ohmylms-lesson"], [class*="ohmylms-rec-"], [class*="ohmylms-lcm"]',
		quizzes:
			'[class*="ohmylms-quiz"], [class*="ohmylms-question"], [class*="ohmylms-option"], [class*="ohmylms-answer"], [class*="ohmylms-bank"], [class*="ohmylms-numerical"], [class*="ohmylms-structured"], [class*="ohmylms-practice"], [class*="ohmylms-expression"]',
		communities: '[class*="ohmylms-community"]',
		students: '[class*="ohmylms-student"], [class*="ohmylms-enrollment"]',
		commerce:
			'[class*="ohmylms-tax"], [class*="ohmylms-order"], [class*="ohmylms-coupon"], [class*="ohmylms-subscription"], [class*="ohmylms-membership"]',
		integrations: '[class*="ohmylms-integration"]',
		settings:
			'[class*="ohmylms-settings"], [class*="ohmylms-page-feature"], [class*="ohmylms-feature-settings"], [class*="ohmylms-design"]',
		setup: '[class*="ohmylms-setup"], [class*="ohmylms-wizard"]',
		'block-editor':
			'[class*="block-editor-"], [class*="interface-interface-"]',
		'email-shell': '.mintmrm, [class*="mrm-"], [class*="mintmrm-"]',
		'email-editor':
			'[class*="arco-"], .CodeMirror, .email-builder-editor, .mrm-email-editor',
		automations: '[class*="automation-"]',
		emails: '[class*="ohmylms-email"], [class*="ohmylms-automation"]',
	};
	let scheduled = false;
	function scan() {
		scheduled = false;
		const groups = Object.keys( selectors ).filter( ( group ) =>
			document.querySelector( selectors[ group ] )
		);
		ensureGroups( groups ).catch( () => {
			// A later render or explicit feature load can retry a failed request.
		} );
	}
	function observe() {
		scan();
		new MutationObserver( () => {
			if ( ! scheduled ) {
				scheduled = true;
				window.requestAnimationFrame( scan );
			}
		} ).observe( document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'class' ],
		} );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', observe, {
			once: true,
		} );
	} else {
		observe();
	}
} )();
