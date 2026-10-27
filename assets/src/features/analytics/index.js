import { createDashboard } from './Dashboard';
import { createDashboardOverview } from './DashboardOverview';
import { createEarningsSummaryCards } from './EarningsSummaryCards';
import { createDashboardStats } from './DashboardStats';
import { createTopCoursePerformance } from './TopCoursePerformance';
import { createRecentCourses } from './RecentCourses';
import { createAnalyticsLinkIcon } from './AnalyticsLinkIcon';
import { createCourseImportDialog } from './CourseImportDialog';
import { createCourseReport } from './CourseReport';
import { createReportMetricCard } from './ReportMetricCard';
import { createEarningsChart } from './EarningsChart';
import { createCourseStudentsReport } from './CourseStudentsReport';
import { createReportStudentCell } from './ReportStudentCell';
import { createStudentReminderDialog } from './StudentReminderDialog';
import { createAnalyticsDateFilter } from './AnalyticsDateFilter';
import { createEarningsReportPage } from './EarningsReportPage';
import { createEarningsReport } from './EarningsReport';
import { createTransactionHistory } from './TransactionHistory';
export const analyticsComponents = {
	Dashboard: createDashboard,
	DashboardOverview: createDashboardOverview,
	EarningsSummaryCards: createEarningsSummaryCards,
	DashboardStats: createDashboardStats,
	TopCoursePerformance: createTopCoursePerformance,
	RecentCourses: createRecentCourses,
	AnalyticsLinkIcon: createAnalyticsLinkIcon,
	CourseImportDialog: createCourseImportDialog,
	CourseReport: createCourseReport,
	ReportMetricCard: createReportMetricCard,
	EarningsChart: createEarningsChart,
	CourseStudentsReport: createCourseStudentsReport,
	ReportStudentCell: createReportStudentCell,
	StudentReminderDialog: createStudentReminderDialog,
	AnalyticsDateFilter: createAnalyticsDateFilter,
	EarningsReportPage: createEarningsReportPage,
	EarningsReport: createEarningsReport,
	TransactionHistory: createTransactionHistory,
};
