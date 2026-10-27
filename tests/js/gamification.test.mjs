import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { codeForAssertions } from './sourceAssertions.mjs';
import {
	activeGamificationTab,
	certificatesInGamification,
	CERTIFICATES_TAB,
	gamificationTabs,
	isStandaloneGamification,
	newAchievementRule,
	updateAchievementRule,
	removeAchievementRule,
} from '../../assets/src/features/gamification/model.mjs';
import {
	gamificationExtraTabs,
	registerGamificationTab,
} from '../../assets/src/features/gamification/extraTabs.mjs';

test( 'all gamification tabs resolve through both supported route families', () => {
	for ( const [ key ] of gamificationTabs ) {
		assert.equal( activeGamificationTab( { tab: key } ), key );
		assert.equal(
			activeGamificationTab( {
				tab: 'gamification-settings',
				subTab: key,
			} ),
			key
		);
	}
	assert.equal(
		activeGamificationTab( { tab: 'unknown' } ),
		'point-settings'
	);
} );

test( 'Certificates is a tab of the standalone Gamification screen only, and only while Gamification is on', () => {
	assert.equal( CERTIFICATES_TAB, 'certificates' );
	assert.equal(
		certificatesInGamification( { is_gamification_enabled: true } ),
		true
	);
	assert.equal(
		certificatesInGamification( { is_gamification_enabled: false } ),
		false
	);
	assert.equal( certificatesInGamification( undefined ), false );
	// The address resolves to the tab when it was registered, and falls back to the first tab otherwise.
	assert.equal(
		activeGamificationTab( { tab: 'certificates' }, [ 'certificates' ] ),
		'certificates'
	);
	assert.equal(
		activeGamificationTab( { tab: 'certificates' } ),
		'point-settings'
	);
	// Settings → Gamification keeps its settings tabs (the XP & mastery tab is one of them).
	assert.equal( isStandaloneGamification( { tab: 'point-settings' } ), true );
	assert.equal(
		isStandaloneGamification( {
			tab: 'gamification-settings',
			subTab: 'level-settings',
		} ),
		false
	);
	assert.ok(
		! gamificationTabs.some( ( [ key ] ) => key === 'certificates' ),
		'the settings tabs do not include it'
	);
	// Registering twice keeps one tab.
	const Component = () => null;
	registerGamificationTab( {
		key: 'certificates',
		label: 'Certificates',
		Component,
	} );
	registerGamificationTab( {
		key: 'certificates',
		label: 'Certificates',
		Component,
	} );
	assert.deepEqual(
		gamificationExtraTabs().map( ( tab ) => tab.key ),
		[ 'certificates' ]
	);
} );

test( 'the Certificates screen moves into Gamification, with the old address, menu and fallback kept', () => {
	const entry = codeForAssertions(
		fs.readFileSync( 'assets/src/extensions/index.jsx', 'utf8' )
	);
	assert.match(
		entry,
		/certificatesInGamification\(window\.ohmylms_params\)/
	);
	assert.match(
		entry,
		/registerGamificationTab\(\{\s*key: CERTIFICATES_TAB,\s*label: 'Certificates'/
	);
	// `#/certificates` opens the tab and the template editor keeps the Gamification entry highlighted.
	assert.match(
		entry,
		/route\.path === '\/certificates'\)\s*return \{ \.\.\.route, element: CertificatesMoved \}/
	);
	assert.match(
		entry,
		/route\.path === '\/certificate-edit\/:id'\)\s*return \{ \.\.\.route, element: withGamificationMenu\(route\.element\) \}/
	);
	const settings = codeForAssertions(
		fs.readFileSync(
			'assets/src/features/gamification/GamificationSettings.jsx',
			'utf8'
		)
	);
	assert.match(
		settings,
		/isStandaloneGamification\(params\) \? gamificationExtraTabs\(\) : \[\]/
	);
	// The menu offers Certificates only when Gamification is off.
	const menu = fs.readFileSync( 'includes/Admin/Menu.php', 'utf8' );
	assert.match(
		menu,
		/\$gamification_enabled = apply_filters\( 'ohmylms_show_gamification_menu', false \);\s*if \( ! \$gamification_enabled \) \{\s*\$submenu\[ \$slug \]\[\] = array\( esc_attr__\( 'Certificates'/
	);
	assert.match(
		menu,
		/if \( \$gamification_enabled \) \{\s*\$submenu\[ \$slug \]\[\] = array\( esc_attr__\( 'Gamification'/
	);
} );

test( 'rule edits and deletion preserve previous React state and retain one condition', () => {
	const first = Object.freeze( newAchievementRule() );
	const second = Object.freeze( {
		...newAchievementRule(),
		compareData: 50,
	} );
	const rules = Object.freeze( [ first, second ] );
	const changed = updateAchievementRule( rules, 0, 'compareData', 25 );
	assert.equal( changed[ 0 ].compareData, 25 );
	assert.equal( first.compareData, 0 );
	assert.equal( changed[ 1 ], second );
	const remaining = removeAchievementRule( rules, 0 );
	assert.deepEqual( remaining, [ second ] );
	assert.equal( rules.length, 2 );
	assert.deepEqual( removeAchievementRule( remaining, 0 ), [ second ] );
	assert.notEqual( newAchievementRule(), newAchievementRule() );
} );

test( 'XP & mastery is a settings tab with its own component', () => {
	assert.ok( gamificationTabs.some( ( [ key ] ) => key === 'xp-settings' ) );
	const source = fs.readFileSync(
		new URL(
			'../../assets/src/features/gamification/GamificationSettings.jsx',
			import.meta.url
		),
		'utf8'
	);
	assert.match( source, /xp-settings[\s\S]*XpMasterySettings/ );
} );

test( 'goal choices are parsed from text and bad entries are dropped', async () => {
	const source = fs.readFileSync(
		new URL(
			'../../assets/src/features/gamification/XpMasterySettings.jsx',
			import.meta.url
		),
		'utf8'
	);
	const match = source.match(
		/export function parseGoals\( text \) \{([\s\S]*?)\n\}/
	);
	assert.ok( match, 'parseGoals is exported' );
	const parseGoals = new Function( 'text', match[ 1 ] );
	assert.deepEqual( parseGoals( '10, 20;30  abc 0 -5' ), [ 10, 20, 30 ] );
	assert.deepEqual( parseGoals( '' ), [] );
} );
