import { createCommunitiesPage } from './CommunitiesPage';
import { createCommunityList } from './CommunityList';
import { createCommunityEditor } from './CommunityEditor';
import { createCommunityDetails } from './CommunityDetails';
import { createCommunityCourses } from './CommunityCourses';
export const communityComponents = {
	CommunitiesPage: createCommunitiesPage,
	CommunityList: createCommunityList,
	CommunityEditor: createCommunityEditor,
	CommunityDetails: createCommunityDetails,
	CommunityCourses: createCommunityCourses,
};
