import { createElement, Fragment } from '@wordpress/element';
import { gamificationExtraTabs } from './extraTabs.mjs';
import {
	activeGamificationTab,
	gamificationTabs,
	isStandaloneGamification,
} from './model.mjs';
import { StreakSettings } from './StreakSettings';
import { XpMasterySettings } from './XpMasterySettings';
import { MovableTabPanel } from '../navigation/MovableTabPanel';

export function createGamificationSettings( readRuntime ) {
	return function GamificationSettings() {
		const runtime = readRuntime();
		const { I: Controls, b: I18n, f: Router, z: Notifications } = runtime;
		const { contextHolder } = Notifications.A();
		const params = Router.g();
		const navigate = Router.Zp();
		// Settings → Gamification has the seven settings tabs; the standalone screen adds the tabs that
		// other screens moved here (Certificates).
		const extraTabs = isStandaloneGamification( params )
			? gamificationExtraTabs()
			: [];
		const activeTab = activeGamificationTab(
			params,
			extraTabs.map( ( tab ) => tab.key )
		);
		const tabContent = ( key, binding ) => {
			if ( key === 'streak-settings' ) {
				return createElement( StreakSettings, {
					BadgeEditor: runtime.o3,
				} );
			}
			if ( key === 'xp-settings' ) {
				return createElement( XpMasterySettings );
			}
			return createElement( runtime[ binding ] );
		};
		const tabLabels = {
			'point-settings': I18n.__( 'Bonus Point', 'ohmylms' ),
			'badge-settings': I18n.__( 'Achievement Badges', 'ohmylms' ),
			'level-settings': I18n.__( 'Learner Levels', 'ohmylms' ),
			'reward-settings': I18n.__( 'Reward', 'ohmylms' ),
			'leaderboard-settings': I18n.__( 'Leaderboard', 'ohmylms' ),
			'streak-settings': I18n.__( 'Streaks', 'ohmylms' ),
			'xp-settings': I18n.__( 'XP & mastery', 'ohmylms' ),
			certificates: I18n.__( 'Certificates', 'ohmylms' ),
		};
		const items = [
			...gamificationTabs.map( ( [ key, label, binding ] ) => ( {
				key,
				label: tabLabels[ key ] || label,
				children: tabContent( key, binding ),
			} ) ),
			...extraTabs.map( ( { key, label, Component } ) => ( {
				key,
				label: tabLabels[ key ] || label,
				children: createElement( Component ),
			} ) ),
		];
		return (
			<Fragment>
				{ contextHolder }
				<Controls.CardWP
					isBorderless
					variant="secondary"
					className="ohmylms-full-screen-height ohmylms-gamification-settings"
				>
					<Controls.SpacerWP
						padding={ 4 }
						paddingTop={ 1 }
						marginTop={ 4 }
						marginBottom={ 0 }
					>
						<MovableTabPanel
							scope={
								isStandaloneGamification( params )
									? 'gamification'
									: 'gamification-settings'
							}
							key={ activeTab }
							items={ items }
							activekey={ activeTab }
							onChange={ ( key ) => {
								const base =
									params.tab === 'gamification-settings'
										? '/settings/gamification-settings/'
										: '/gamification/';
								navigate( base + key );
							} }
						/>
					</Controls.SpacerWP>
				</Controls.CardWP>
			</Fragment>
		);
	};
}
