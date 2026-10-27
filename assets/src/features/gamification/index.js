import { createGamificationPage } from './GamificationPage';
import { createGamificationSettings } from './GamificationSettings';
import { createLeaderboardSettings } from './LeaderboardSettings';
import { createBonusPointSettings } from './BonusPointSettings';
import { createBadgeImageField } from './BadgeImageField';
import { createAchievementRules } from './AchievementRules';
import { createBadgeEditor } from './BadgeEditor';
import { createBadgeList } from './BadgeList';
import { createBadgeSettings } from './BadgeSettings';
import { createLevelEditor } from './LevelEditor';
import { createLevelList } from './LevelList';
import { createLevelSettings } from './LevelSettings';
import { createRewardSettings } from './RewardSettings';
export const gamificationComponents = {
	GamificationPage: createGamificationPage,
	GamificationSettings: createGamificationSettings,
	LeaderboardSettings: createLeaderboardSettings,
	BonusPointSettings: createBonusPointSettings,
	BadgeImageField: createBadgeImageField,
	AchievementRules: createAchievementRules,
	BadgeEditor: createBadgeEditor,
	BadgeList: createBadgeList,
	BadgeSettings: createBadgeSettings,
	LevelEditor: createLevelEditor,
	LevelList: createLevelList,
	LevelSettings: createLevelSettings,
	RewardSettings: createRewardSettings,
};
