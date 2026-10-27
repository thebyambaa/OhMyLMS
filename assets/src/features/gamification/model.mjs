export const gamificationTabs = [
	[ 'point-settings', 'Bonus Point', 'N2' ],
	[ 'badge-settings', 'Achievement Badges', 'E3' ],
	[ 'level-settings', 'Learner Levels', 'c5' ],
	[ 'reward-settings', 'Reward', 'w5' ],
	[ 'leaderboard-settings', 'Leaderboard', 'S2' ],
	[ 'streak-settings', 'Streaks', null ],
	[ 'xp-settings', 'XP & mastery', null ],
];
/**
 * The Gamification screen also exists as Settings → Gamification, which has only the tabs above.
 * Tabs that other screens move into Gamification (Certificates) belong to the standalone screen.
 * @param params
 */
export function isStandaloneGamification( params ) {
	return params.tab !== 'gamification-settings';
}
/**
 * `extraKeys` are the keys of the tabs added to the standalone screen; they are not tabs elsewhere.
 * @param params
 * @param extraKeys
 */
export function activeGamificationTab( params, extraKeys = [] ) {
	const candidate = params.subTab || params.tab;
	const keys = [
		...gamificationTabs.map( ( [ key ] ) => key ),
		...extraKeys,
	];
	return keys.includes( candidate ) ? candidate : 'point-settings';
}
/** Certificates are a Gamification tab when Gamification is on; otherwise they keep their own menu entry. */
export const CERTIFICATES_TAB = 'certificates';
export function certificatesInGamification( appParams ) {
	return Boolean( appParams?.is_gamification_enabled );
}
export function updateAchievementRule( rules, index, field, value ) {
	return rules.map( ( rule, position ) =>
		position === index ? { ...rule, [ field ]: value } : rule
	);
}
export function removeAchievementRule( rules, index ) {
	return rules.length > 1
		? rules.filter( ( _, position ) => position !== index )
		: rules;
}
export function newAchievementRule() {
	return {
		dataLabel: 'Points',
		dataValue: 'points',
		dataFieldType: 'select',
		compareSign: '>=',
		compareData: 0,
		compareDataFieldType: 'input',
	};
}
