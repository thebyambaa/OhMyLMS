import { createMembershipsPage } from './MembershipsPage';
import { createMembershipList } from './MembershipList';
import { createMembershipEditor } from './MembershipEditor';
import { createMembershipDetails } from './MembershipDetails';
import { createMembershipPricing } from './MembershipPricing';
import { createMembershipSaleSchedule } from './MembershipSaleSchedule';
import { createMembershipCourses } from './MembershipCourses';
export const membershipComponents = {
	MembershipsPage: createMembershipsPage,
	MembershipList: createMembershipList,
	MembershipEditor: createMembershipEditor,
	MembershipDetails: createMembershipDetails,
	MembershipPricing: createMembershipPricing,
	MembershipSaleSchedule: createMembershipSaleSchedule,
	MembershipCourses: createMembershipCourses,
};
